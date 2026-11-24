<?php

namespace App\Support\Cooperative\Import;

use App\Actions\Membership\BootstrapLegacyMember;
use App\Enums\CooperativeFundEntryType;
use App\Enums\CooperativeFundType;
use App\Enums\CooperativeImportBatchStatus;
use App\Enums\CooperativeImportComponentStatus;
use App\Enums\CooperativeImportMode;
use App\Enums\CooperativeImportRowStatus;
use App\Enums\CooperativeMatchMethod;
use App\Enums\CooperativeMembershipStatus;
use App\Enums\CooperativeSourceType;
use App\Enums\ParticipationEntryType;
use App\Enums\ParticipationUnitsOrigin;
use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeFundEntry;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportRow;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\ParticipationEntry;
use App\Models\User;
use App\Support\Cooperative\FinancialInvariants;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ImportCommitter
{
    public function __construct(
        private readonly BootstrapLegacyMember $legacyMembers = new BootstrapLegacyMember,
        private readonly ImportBatchService $batches = new ImportBatchService,
        private readonly LegacyImportReplacer $replacer = new LegacyImportReplacer,
    ) {}

    public function commit(
        CooperativeImportBatch $batch,
        User $actor,
        CooperativeImportMode $mode = CooperativeImportMode::Append,
        bool $replaceAcknowledged = false,
    ): CooperativeImportBatch {
        if ($mode === CooperativeImportMode::ReplaceLegacy && ! $replaceAcknowledged) {
            throw new RuntimeException('Replace legacy mode requires explicit backup acknowledgement.');
        }

        return DB::transaction(function () use ($batch, $actor, $mode) {
            $locked = CooperativeImportBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isReversed() || $locked->isReplaced()) {
                throw new RuntimeException('This import batch can no longer be committed.');
            }

            if (! $locked->canCommit($mode)) {
                throw new RuntimeException('This import batch is not ready to commit.');
            }

            if ($mode === CooperativeImportMode::ReplaceLegacy && $locked->isCommitted()) {
                throw new RuntimeException('This import batch has already been committed. Upload a new batch to replace legacy data.');
            }

            $replacementReport = null;

            if ($mode === CooperativeImportMode::ReplaceLegacy) {
                $replacementReport = $this->replacer->execute($locked, $actor);
                $this->replacer->prepareIncomingRowsForReplace($locked->fresh() ?? $locked);
                $locked = $locked->fresh() ?? $locked;
            } else {
                $duplicateCommitted = CooperativeImportBatch::query()
                    ->where('file_hash', $locked->file_hash)
                    ->where('worksheet_name', $locked->worksheet_name)
                    ->where('id', '!=', $locked->id)
                    ->whereIn('status', [
                        CooperativeImportBatchStatus::Committed,
                        CooperativeImportBatchStatus::CompletedWithReview,
                    ])
                    ->whereNull('replaced_by_batch_id')
                    ->exists();

                if ($duplicateCommitted) {
                    throw new RuntimeException('An identical worksheet from this workbook has already been committed.');
                }
            }

            $locked->forceFill([
                'import_mode' => $mode,
                'replace_acknowledged_at' => $mode === CooperativeImportMode::ReplaceLegacy ? now() : null,
                'replace_acknowledged_by' => $mode === CooperativeImportMode::ReplaceLegacy ? $actor->id : null,
            ])->save();

            $business = CooperativeBusiness::psn();
            $committed = 0;
            $failed = 0;

            $rows = CooperativeImportRow::query()
                ->where('batch_id', $locked->id)
                ->where('validation_status', '!=', CooperativeImportRowStatus::Invalid)
                ->orderBy('source_row_number')
                ->lockForUpdate()
                ->get()
                ->filter(fn (CooperativeImportRow $row) => $row->hasReadyUncommittedComponents());

            foreach ($rows as $row) {
                try {
                    DB::transaction(function () use ($row, $locked, $business, $actor): void {
                        $this->commitRow($row, $locked, $business, $actor);
                    });
                    $committed++;
                } catch (Throwable) {
                    $failed++;
                    $fresh = $row->fresh() ?? $row;
                    $fresh->forceFill([
                        'validation_errors' => array_values(array_unique([
                            ...($fresh->validation_errors ?? []),
                            'commit_failed',
                        ])),
                        'review_notes' => trim((string) $fresh->review_notes."\nCommit failed."),
                    ])->save();
                }
            }

            if ($mode === CooperativeImportMode::ReplaceLegacy && $failed > 0) {
                throw new RuntimeException(
                    'Replace legacy import failed during canonical write. The database transaction was rolled back.'
                );
            }

            $extra = [
                'committed_rows' => ($locked->summary_json['committed_rows'] ?? 0) + $committed,
                'commit_failures' => $failed,
                'committed_at' => now()->toIso8601String(),
                'committed_by' => $actor->id,
                'import_mode' => $mode->value,
            ];

            if ($replacementReport !== null) {
                $extra['replacement_report'] = $replacementReport;
                $extra['replaced_batch_ids'] = $replacementReport['batches_replaced'] ?? [];
            }

            $this->batches->refreshBatchStatus($locked->fresh() ?? $locked, $extra);

            return $locked->fresh() ?? $locked;
        });
    }

    private function commitRow(
        CooperativeImportRow $row,
        CooperativeImportBatch $batch,
        CooperativeBusiness $business,
        User $actor,
    ): void {
        $row = CooperativeImportRow::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
        $data = $row->normalized_data ?? [];
        $components = $data['components'] ?? [];

        if ($row->isFullyCommitted()) {
            return;
        }

        $member = $this->commitMember($row, $batch, $actor, $data, $components);
        $membership = $this->commitMembership($row, $batch, $member, $data, $components);

        try {
            $this->commitFunds($row, $batch, $membership, $member, $actor, $data, $components);
        } catch (Throwable) {
            $this->markComponentFailed($row, 'funds');
        }

        try {
            $this->commitPsn($row, $batch, $business, $membership, $member, $actor, $data, $components);
        } catch (Throwable) {
            $this->markComponentFailed($row, 'psn');
        }

        $row = $row->fresh() ?? $row;
        $data = $row->normalized_data ?? [];
        $this->finalizeRow($row, $data);
    }

    private function markComponentFailed(CooperativeImportRow $row, string $component): void
    {
        $row = $row->fresh() ?? $row;
        $data = $row->normalized_data ?? [];
        $data['components'][$component]['status'] = CooperativeImportComponentStatus::NeedsReview->value;
        $errors = array_values(array_unique([
            ...($row->validation_errors ?? []),
            'commit_failed',
        ]));
        $row->forceFill([
            'normalized_data' => $data,
            'validation_errors' => $errors,
            'validation_status' => CooperativeImportRowStatus::NeedsReview,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $components
     */
    private function commitMember(
        CooperativeImportRow $row,
        CooperativeImportBatch $batch,
        User $actor,
        array $data,
        array $components,
    ): Member {
        if ($row->member_committed_at !== null && $row->matched_member_id !== null) {
            return Member::query()->whereKey($row->matched_member_id)->lockForUpdate()->firstOrFail();
        }

        if (($components['member']['status'] ?? null) !== CooperativeImportComponentStatus::Ready->value) {
            throw new RuntimeException('Member component is not ready.');
        }

        $member = $row->matched_member_id === null
            ? null
            : Member::query()->whereKey($row->matched_member_id)->lockForUpdate()->first();

        if ($member === null && ($data['propose_create_member'] ?? false) === true) {
            $member = $this->legacyMembers->handle($row, $batch, $actor, $data);
            $row->forceFill([
                'matched_member_id' => $member->id,
                'created_member_id' => $member->wasRecentlyCreated ? $member->id : $row->created_member_id,
                'match_method' => $member->wasRecentlyCreated
                    ? CooperativeMatchMethod::ProposedLegacyCreate
                    : ($row->match_method ?? CooperativeMatchMethod::EmailExact),
            ]);
        }

        if ($member === null) {
            throw new RuntimeException('Member could not be resolved for this import row.');
        }

        $data = $row->normalized_data ?? [];
        $data['components']['member']['status'] = CooperativeImportComponentStatus::Committed->value;
        $row->forceFill([
            'normalized_data' => $data,
            'matched_member_id' => $member->id,
            'member_committed_at' => now(),
        ])->save();

        return $member;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $components
     */
    private function commitMembership(
        CooperativeImportRow $row,
        CooperativeImportBatch $batch,
        Member $member,
        array $data,
        array $components,
    ): CooperativeMembership {
        if ($row->membership_committed_at !== null) {
            return CooperativeMembership::query()
                ->where('member_id', $member->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        if (($components['cooperative_membership']['status'] ?? null) !== CooperativeImportComponentStatus::Ready->value) {
            throw new RuntimeException('Cooperative membership component is not ready.');
        }

        $membership = CooperativeMembership::query()
            ->where('member_id', $member->id)
            ->lockForUpdate()
            ->first();

        if ($membership === null) {
            $membership = new CooperativeMembership;
            $membership->member_id = $member->id;
            $membership->status = CooperativeMembershipStatus::Active;
            $membership->joined_at = $data['effective_date_start'] ?? now();
            $membership->notes = 'Created from Cooperative legacy import batch #'.$batch->id;
            $membership->applyStatusSideEffects();
            $membership->save();
            $membership = CooperativeMembership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
        }

        $stored = $row->normalized_data ?? [];
        $stored['components']['cooperative_membership']['status'] = CooperativeImportComponentStatus::Committed->value;
        $row->forceFill([
            'normalized_data' => $stored,
            'membership_committed_at' => now(),
        ])->save();

        return $membership;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $components
     */
    private function commitFunds(
        CooperativeImportRow $row,
        CooperativeImportBatch $batch,
        CooperativeMembership $membership,
        Member $member,
        User $actor,
        array $data,
        array $components,
    ): void {
        $status = $components['funds']['status'] ?? CooperativeImportComponentStatus::None->value;

        if ($row->funds_committed_at !== null || $status === CooperativeImportComponentStatus::None->value) {
            return;
        }

        if ($status !== CooperativeImportComponentStatus::Ready->value) {
            return;
        }

        $this->writeFundOpening(
            $membership,
            $batch,
            $row,
            CooperativeFundType::PrincipalSaving,
            (int) ($data['principal_amount'] ?? 0),
            $data,
            $actor,
            $member,
        );
        $this->writeFundOpening(
            $membership,
            $batch,
            $row,
            CooperativeFundType::VoluntarySaving,
            (int) ($data['voluntary_amount'] ?? 0),
            $data,
            $actor,
            $member,
        );

        $stored = $row->normalized_data ?? [];
        $stored['components']['funds']['status'] = CooperativeImportComponentStatus::Committed->value;
        $row->forceFill([
            'normalized_data' => $stored,
            'funds_committed_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $components
     */
    private function commitPsn(
        CooperativeImportRow $row,
        CooperativeImportBatch $batch,
        CooperativeBusiness $business,
        CooperativeMembership $membership,
        Member $member,
        User $actor,
        array $data,
        array $components,
    ): void {
        $status = $components['psn']['status'] ?? CooperativeImportComponentStatus::None->value;

        if ($row->psn_committed_at !== null || $status !== CooperativeImportComponentStatus::Ready->value) {
            return;
        }

        $units = (int) ($data['units'] ?? 0);
        if ($units <= 0) {
            return;
        }

        try {
            $participation = BusinessParticipation::query()->firstOrCreate([
                'cooperative_membership_id' => $membership->id,
                'cooperative_business_id' => $business->id,
            ]);
        } catch (Throwable) {
            $participation = BusinessParticipation::query()
                ->where('cooperative_membership_id', $membership->id)
                ->where('cooperative_business_id', $business->id)
                ->firstOrFail();
        }

        $participation = BusinessParticipation::query()
            ->whereKey($participation->id)
            ->lockForUpdate()
            ->firstOrFail();

        $alreadyOpen = $participation->entries()
            ->where('entry_type', ParticipationEntryType::OpeningBalance)
            ->exists();

        if ($alreadyOpen) {
            $stored = $row->normalized_data ?? [];
            $stored['components']['psn']['status'] = CooperativeImportComponentStatus::Committed->value;
            $row->forceFill([
                'normalized_data' => $stored,
                'psn_committed_at' => now(),
            ])->save();

            return;
        }

        $unitValue = (int) ($data['unit_value'] ?? $business->current_unit_value);
        $amount = $units * $unitValue;
        FinancialInvariants::assertOpeningBalance($units, $unitValue, $amount);
        FinancialInvariants::assertNonNegativeResultingUnits($participation->currentUnits(), $units);

        $origin = ParticipationUnitsOrigin::from((string) ($data['units_origin'] ?? ParticipationUnitsOrigin::Inferred->value));

        ParticipationEntry::query()->create([
            'business_participation_id' => $participation->id,
            'entry_type' => ParticipationEntryType::OpeningBalance,
            'units' => $units,
            'unit_value' => $unitValue,
            'amount' => $amount,
            'effective_date_start' => $data['effective_date_start'] ?? null,
            'effective_date_end' => $data['effective_date_end'] ?? null,
            'share_reference' => $data['share_reference'] ?? null,
            'units_origin' => $origin,
            'source_type' => CooperativeSourceType::LegacyImport,
            'import_batch_id' => $batch->id,
            'legacy_source_row_id' => $row->id,
            'notes' => null,
            'provenance' => $this->provenance($batch, $row, $actor, $data, $member),
        ]);

        $stored = $row->normalized_data ?? [];
        $stored['components']['psn']['status'] = CooperativeImportComponentStatus::Committed->value;
        $row->forceFill([
            'normalized_data' => $stored,
            'psn_committed_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function finalizeRow(CooperativeImportRow $row, array $data): void
    {
        $components = $data['components'] ?? [];
        $pendingReview = false;
        $pendingReady = false;

        foreach (['member', 'cooperative_membership', 'funds', 'psn'] as $key) {
            $status = $components[$key]['status'] ?? CooperativeImportComponentStatus::None->value;
            if ($status === CooperativeImportComponentStatus::NeedsReview->value) {
                $pendingReview = true;
            }
            if ($status === CooperativeImportComponentStatus::Ready->value) {
                $pendingReady = true;
            }
        }

        if (! $pendingReview && ! $pendingReady) {
            $row->forceFill([
                'validation_status' => CooperativeImportRowStatus::Committed,
                'committed_at' => now(),
            ])->save();

            return;
        }

        $row->forceFill([
            'validation_status' => $pendingReview
                ? CooperativeImportRowStatus::NeedsReview
                : CooperativeImportRowStatus::Ready,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeFundOpening(
        CooperativeMembership $membership,
        CooperativeImportBatch $batch,
        CooperativeImportRow $row,
        CooperativeFundType $type,
        int $amount,
        array $data,
        User $actor,
        Member $member,
    ): void {
        if ($amount <= 0) {
            return;
        }

        FinancialInvariants::assertFundOpening($amount);

        $exists = CooperativeFundEntry::query()
            ->where('cooperative_membership_id', $membership->id)
            ->where('fund_type', $type)
            ->where('entry_type', CooperativeFundEntryType::OpeningBalance)
            ->notReversed()
            ->exists();

        if ($exists) {
            return;
        }

        CooperativeFundEntry::query()->create([
            'cooperative_membership_id' => $membership->id,
            'fund_type' => $type,
            'entry_type' => CooperativeFundEntryType::OpeningBalance,
            'amount' => $amount,
            'effective_date_start' => $data['effective_date_start'] ?? null,
            'effective_date_end' => $data['effective_date_end'] ?? null,
            'source_type' => CooperativeSourceType::LegacyImport,
            'import_batch_id' => $batch->id,
            'legacy_source_row_id' => $row->id,
            'notes' => null,
            'provenance' => $this->provenance($batch, $row, $actor, $data, $member),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function provenance(
        CooperativeImportBatch $batch,
        CooperativeImportRow $row,
        User $actor,
        array $data,
        Member $member,
    ): array {
        return [
            'import_batch_id' => $batch->id,
            'legacy_source_row_id' => $row->id,
            'source_row_number' => $row->source_row_number,
            'source_fingerprint' => $row->source_fingerprint,
            'file_hash' => $batch->file_hash,
            'original_filename' => $batch->original_filename,
            'worksheet_name' => $batch->worksheet_name,
            'worksheet_visible' => $batch->worksheet_visible,
            'header_row_number' => $batch->header_row_number,
            'imported_by' => $actor->id,
            'imported_at' => now()->toIso8601String(),
            'units_origin' => $data['units_origin'] ?? null,
            'explicit_units' => $data['explicit_units'] ?? null,
            'inferred_units' => $data['inferred_units'] ?? null,
            'source_total' => $data['source_total'] ?? null,
            'principal_amount' => $data['principal_amount'] ?? null,
            'voluntary_amount' => $data['voluntary_amount'] ?? null,
            'share_raw' => $data['share_raw'] ?? null,
            'share_reference' => $data['share_reference'] ?? null,
            'identity_class' => $data['identity_class'] ?? null,
            'match_method' => $row->match_method?->value ?? CooperativeMatchMethod::Manual->value,
            'member_resolution' => $data['member_resolution'] ?? null,
            'member_source_type' => $member->source_type?->value ?? null,
            'created_member_id' => $row->created_member_id,
            'source_type' => 'cooperative_legacy_import',
            'historical_funds_are_source_of_participation' => true,
        ];
    }
}
