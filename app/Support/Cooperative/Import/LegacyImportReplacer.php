<?php

namespace App\Support\Cooperative\Import;

use App\Enums\CooperativeImportBatchStatus;
use App\Enums\CooperativeImportComponentStatus;
use App\Enums\CooperativeImportRowStatus;
use App\Enums\CooperativeMemberResolution;
use App\Enums\CooperativeSourceType;
use App\Enums\MemberSourceType;
use App\Enums\ParticipationEntryType;
use App\Models\BusinessParticipation;
use App\Models\CooperativeFundEntry;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportRow;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\ParticipationEntry;
use App\Models\User;
use RuntimeException;

/**
 * Controlled replacement of prior Cooperative legacy-import canonical data.
 * Provenance-scoped only — never truncates application tables.
 */
final class LegacyImportReplacer
{
    /**
     * @return array{
     *     eligible_legacy_members: int,
     *     preserved_legacy_members: int,
     *     cooperative_memberships: int,
     *     historical_fund_entries: int,
     *     psn_participation_entries: int,
     *     import_batches_affected: int,
     *     affected_batch_ids: list<int>
     * }
     */
    public function impactSummary(): array
    {
        $legacyMemberIds = Member::query()
            ->where('source_type', MemberSourceType::CooperativeLegacyImport)
            ->pluck('id');

        $eligible = 0;
        $preserved = 0;

        foreach (Member::query()->whereIn('id', $legacyMemberIds)->get() as $member) {
            if ($this->memberIsSafeToRemove($member)) {
                $eligible++;
            } else {
                $preserved++;
            }
        }

        $legacyFundCount = CooperativeFundEntry::query()
            ->where('source_type', CooperativeSourceType::LegacyImport)
            ->count();

        $legacyPsnCount = ParticipationEntry::query()
            ->where('source_type', CooperativeSourceType::LegacyImport)
            ->where('entry_type', ParticipationEntryType::OpeningBalance)
            ->count();

        $batchIds = $this->legacyImportBatchIds();

        return [
            'eligible_legacy_members' => $eligible,
            'preserved_legacy_members' => $preserved,
            'cooperative_memberships' => CooperativeMembership::query()->whereIn('member_id', $legacyMemberIds)->count(),
            'historical_fund_entries' => $legacyFundCount,
            'psn_participation_entries' => $legacyPsnCount,
            'import_batches_affected' => count($batchIds),
            'affected_batch_ids' => $batchIds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(CooperativeImportBatch $incoming, User $actor): array
    {
        $report = [
            'old_legacy_members_removed' => 0,
            'old_legacy_members_preserved' => 0,
            'old_cooperative_memberships_replaced' => 0,
            'old_fund_entries_replaced' => 0,
            'old_psn_entries_replaced' => 0,
            'batches_replaced' => [],
            'preserved_member_ids' => [],
        ];

        $batchIds = $this->legacyImportBatchIds();

        $openings = ParticipationEntry::query()
            ->where('source_type', CooperativeSourceType::LegacyImport)
            ->where('entry_type', ParticipationEntryType::OpeningBalance)
            ->lockForUpdate()
            ->get();

        foreach ($openings as $entry) {
            $laterIndependent = ParticipationEntry::query()
                ->where('business_participation_id', $entry->business_participation_id)
                ->where('id', '>', $entry->id)
                ->whereNotIn('source_type', [
                    CooperativeSourceType::LegacyImport->value,
                    CooperativeSourceType::ImportReversal->value,
                ])
                ->exists();

            if ($laterIndependent) {
                throw new RuntimeException(
                    'Replace blocked: independent PSN entries exist after prior legacy import openings.'
                );
            }
        }

        ParticipationEntry::query()
            ->where('source_type', CooperativeSourceType::ImportReversal)
            ->whereIn('import_batch_id', $batchIds)
            ->delete();

        $report['old_psn_entries_replaced'] = ParticipationEntry::query()
            ->where('source_type', CooperativeSourceType::LegacyImport)
            ->delete();

        BusinessParticipation::query()
            ->whereDoesntHave('entries')
            ->whereHas('membership.member', fn ($query) => $query->where('source_type', MemberSourceType::CooperativeLegacyImport))
            ->delete();

        $report['old_fund_entries_replaced'] = CooperativeFundEntry::query()
            ->where('source_type', CooperativeSourceType::LegacyImport)
            ->delete();

        $legacyMemberIds = Member::query()
            ->where('source_type', MemberSourceType::CooperativeLegacyImport)
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        foreach (CooperativeMembership::query()->whereIn('member_id', $legacyMemberIds)->lockForUpdate()->get() as $membership) {
            $hasIndependentFunds = CooperativeFundEntry::query()
                ->where('cooperative_membership_id', $membership->id)
                ->exists();

            $hasIndependentPsn = ParticipationEntry::query()
                ->whereHas('participation', fn ($query) => $query->where('cooperative_membership_id', $membership->id))
                ->exists();

            if ($hasIndependentFunds || $hasIndependentPsn) {
                continue;
            }

            BusinessParticipation::query()
                ->where('cooperative_membership_id', $membership->id)
                ->whereDoesntHave('entries')
                ->delete();

            $membership->delete();
            $report['old_cooperative_memberships_replaced']++;
        }

        foreach (Member::query()->whereIn('id', $legacyMemberIds)->lockForUpdate()->get() as $member) {
            if (! $this->memberIsSafeToRemove($member)) {
                $report['old_legacy_members_preserved']++;
                $report['preserved_member_ids'][] = $member->id;

                continue;
            }

            CooperativeImportRow::query()
                ->where('created_member_id', $member->id)
                ->update(['created_member_id' => null, 'matched_member_id' => null]);

            $member->delete();
            $report['old_legacy_members_removed']++;
        }

        $batches = CooperativeImportBatch::query()
            ->whereIn('id', $batchIds)
            ->whereNull('replaced_by_batch_id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            $summary = $batch->summary_json ?? [];
            $summary['replaced_at'] = now()->toIso8601String();
            $summary['replaced_by_batch_id'] = $incoming->id;
            $summary['replaced_by'] = $actor->id;

            $batch->forceFill([
                'status' => CooperativeImportBatchStatus::Replaced,
                'replaced_by_batch_id' => $incoming->id,
                'summary_json' => $summary,
            ])->save();

            $report['batches_replaced'][] = $batch->id;
        }

        if ($report['batches_replaced'] !== []) {
            $incoming->forceFill([
                'replaces_batch_id' => $report['batches_replaced'][0],
            ])->save();
        }

        return $report;
    }

    /**
     * Transform append-mode identity blocks into preserve-with-warning readiness
     * for replace_legacy commits.
     */
    public function prepareIncomingRowsForReplace(CooperativeImportBatch $batch): void
    {
        $rows = $batch->rows()->orderBy('source_row_number')->get();
        $auditor = new SourceDuplicateAuditor;
        $duplicateMap = $auditor->duplicateCodesByRowId($rows);

        foreach ($rows as $row) {
            if ($row->validation_status === CooperativeImportRowStatus::Invalid) {
                continue;
            }

            $data = $row->normalized_data ?? [];
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $warnings = array_values(array_unique([
                ...($data['profile_warnings'] ?? []),
                ...($data['review_categories']['profile'] ?? []),
            ]));

            $codes = $duplicateMap[$row->id] ?? [];
            foreach ($codes as $code) {
                $warnings[] = match ($code) {
                    'source_duplicate_nik' => 'possible_duplicate_nik',
                    'source_duplicate_identifier' => 'possible_duplicate_identifier',
                    'source_duplicate_email' => 'shared_email',
                    'source_duplicate_phone' => 'shared_phone',
                    default => $code,
                };
            }

            if ($codes !== []) {
                $data['imported_with_review'] = true;
            }

            // Replace mode preserves every authorized source row as its own Member.
            $data['preserve_source_duplicate'] = true;
            $data['propose_create_member'] = true;
            $data['propose_create_membership'] = true;
            $data['member_resolution'] = CooperativeMemberResolution::ProposedLegacyCreate->value;
            $matchedId = null;

            // Prior legacy openings / fingerprints must not block after cleanup.
            $share = array_values(array_filter(
                $data['review_categories']['share'] ?? [],
                fn (string $code): bool => ! in_array($code, ['existing_psn_participation'], true),
            ));
            $financial = array_values(array_filter(
                $data['review_categories']['financial'] ?? [],
                fn (string $code): bool => ! in_array($code, ['existing_fund_opening'], true),
            ));
            $identity = array_values(array_filter(
                $data['review_categories']['identity'] ?? [],
                fn (string $code): bool => ! str_starts_with($code, 'source_duplicate')
                    && $code !== 'duplicate_committed_source'
                    && $code !== 'ambiguous_member'
                    && $code !== 'name_collision',
            ));

            // Prior legacy openings / fingerprints must not block after cleanup.
            $data['components']['member']['status'] = CooperativeImportComponentStatus::Ready->value;
            $data['components']['cooperative_membership']['status'] = CooperativeImportComponentStatus::Ready->value;

            if (($data['components']['funds']['status'] ?? null) === CooperativeImportComponentStatus::NeedsReview->value
                && $financial === []
                && (((int) ($data['principal_amount'] ?? 0) > 0) || ((int) ($data['voluntary_amount'] ?? 0) > 0))) {
                $data['components']['funds']['status'] = CooperativeImportComponentStatus::Ready->value;
            }

            // Keep unresolved share math as PSN review; restore readiness when only
            // prior-legacy existence flags were blocking.
            if ($share !== [] || ($data['share_reconciled'] ?? true) !== true || ((int) ($data['units'] ?? 0) <= 0)) {
                $data['components']['psn']['status'] = CooperativeImportComponentStatus::NeedsReview->value;
            } elseif ((int) ($data['units'] ?? 0) > 0 && ($data['share_reconciled'] ?? true) === true) {
                $data['components']['psn']['status'] = CooperativeImportComponentStatus::Ready->value;
            }

            $data['review_categories']['identity'] = $identity;
            $data['review_categories']['financial'] = $financial;
            $data['review_categories']['share'] = $share;
            $data['review_categories']['profile'] = array_values(array_unique($warnings));
            $data['profile_warnings'] = array_values(array_unique($warnings));

            $blocking = array_values(array_unique([...$financial, ...$share]));
            $status = $warnings !== [] || $blocking !== [] || ($data['imported_with_review'] ?? false)
                ? CooperativeImportRowStatus::NeedsReview
                : CooperativeImportRowStatus::Ready;

            $errors = array_values(array_unique([
                ...$warnings,
                ...$blocking,
                ...($data['review_categories']['date'] ?? []),
            ]));

            $row->forceFill([
                'normalized_data' => $data,
                'matched_member_id' => $matchedId,
                'validation_status' => $status,
                'validation_errors' => $errors === [] ? null : $errors,
                'committed_at' => null,
                'member_committed_at' => null,
                'membership_committed_at' => null,
                'funds_committed_at' => null,
                'psn_committed_at' => null,
            ])->save();
        }
    }

    public function memberIsSafeToRemove(Member $member): bool
    {
        if ($member->source_type !== MemberSourceType::CooperativeLegacyImport) {
            return false;
        }

        if ($member->user_id !== null) {
            return false;
        }

        if ($member->lapak()->exists()) {
            return false;
        }

        if ($member->source_application_id !== null) {
            return false;
        }

        $membership = $member->cooperativeMembership;
        if ($membership === null) {
            return true;
        }

        $hasIndependentPsn = ParticipationEntry::query()
            ->whereHas('participation', fn ($query) => $query->where('cooperative_membership_id', $membership->id))
            ->whereNotIn('source_type', [
                CooperativeSourceType::LegacyImport->value,
                CooperativeSourceType::ImportReversal->value,
            ])
            ->exists();

        $hasIndependentFunds = CooperativeFundEntry::query()
            ->where('cooperative_membership_id', $membership->id)
            ->where(function ($query): void {
                $query->whereNull('source_type')
                    ->orWhere('source_type', '!=', CooperativeSourceType::LegacyImport);
            })
            ->exists();

        return ! $hasIndependentPsn && ! $hasIndependentFunds;
    }

    /**
     * @return list<int>
     */
    private function legacyImportBatchIds(): array
    {
        return collect([
            ...CooperativeFundEntry::query()
                ->where('source_type', CooperativeSourceType::LegacyImport)
                ->whereNotNull('import_batch_id')
                ->pluck('import_batch_id'),
            ...ParticipationEntry::query()
                ->where('source_type', CooperativeSourceType::LegacyImport)
                ->whereNotNull('import_batch_id')
                ->pluck('import_batch_id'),
            ...CooperativeImportRow::query()
                ->whereNotNull('member_committed_at')
                ->pluck('batch_id'),
        ])->unique()->filter()->values()->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<CooperativeImportBatch>
     */
    private function replaceableBatches()
    {
        return CooperativeImportBatch::query()
            ->whereIn('status', [
                CooperativeImportBatchStatus::Committed,
                CooperativeImportBatchStatus::CompletedWithReview,
                CooperativeImportBatchStatus::Reversed,
            ])
            ->whereNull('replaced_by_batch_id');
    }
}
