<?php

namespace App\Support\Cooperative\Import;

use App\Enums\CooperativeImportComponentStatus;
use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewOutcome;
use App\Enums\CooperativeImportReviewStatus;
use App\Enums\CooperativeSourceType;
use App\Enums\ParticipationEntryType;
use App\Enums\ParticipationUnitsOrigin;
use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportReviewAction;
use App\Models\CooperativeImportReviewItem;
use App\Models\CooperativeImportRow;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\ParticipationEntry;
use App\Models\User;
use App\Support\Cooperative\FinancialInvariants;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PsnManualReconciler
{
    /**
     * @param  array{
     *     units?: int,
     *     share_reference?: ?string,
     *     administrative_note?: ?string
     * }  $payload
     */
    public function resolve(
        CooperativeImportBatch $batch,
        CooperativeImportRow $row,
        User $operator,
        CooperativeImportReviewOutcome $outcome,
        array $payload = [],
    ): CooperativeImportRow {
        return DB::transaction(function () use ($batch, $row, $operator, $outcome, $payload) {
            $row = CooperativeImportRow::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->batch_id === $batch->id, 404);

            $data = $row->normalized_data ?? [];
            if (($data['components']['psn']['status'] ?? null) !== CooperativeImportComponentStatus::NeedsReview->value) {
                throw new RuntimeException('PSN component is not awaiting reconciliation.');
            }

            $note = isset($payload['administrative_note']) ? trim((string) $payload['administrative_note']) : null;

            if ($outcome === CooperativeImportReviewOutcome::UnresolvedRequiresFollowup) {
                $this->recordPsnFollowup($batch, $row, $operator, $note);

                return $row->fresh(['matchedMember', 'createdMember']);
            }

            if ($outcome === CooperativeImportReviewOutcome::ConfirmedNoPsnParticipation) {
                $this->markPsnResolvedWithoutEntry($row, $data, $note);
                $this->resolvePsnReviewItems($batch, $row, $operator, $outcome, $note, [
                    'decision' => 'confirmed_no_psn_participation',
                ]);

                return $row->fresh(['matchedMember', 'createdMember']);
            }

            if ($outcome !== CooperativeImportReviewOutcome::ManualReconciliation) {
                throw new RuntimeException('Unsupported PSN reconciliation outcome.');
            }

            $units = (int) ($payload['units'] ?? -1);
            if ($units < 0 || (string) $units !== (string) ($payload['units'] ?? '')) {
                throw new RuntimeException('Resolved unit count must be a non-negative integer.');
            }

            if ($units === 0) {
                throw new RuntimeException('Use confirmed no participation when units are zero.');
            }

            $member = $this->memberForRow($row);
            $membership = CooperativeMembership::query()
                ->where('member_id', $member->id)
                ->firstOrFail();
            $business = CooperativeBusiness::psn();

            $participation = BusinessParticipation::query()->firstOrCreate([
                'cooperative_membership_id' => $membership->id,
                'cooperative_business_id' => $business->id,
            ]);

            $participation = BusinessParticipation::query()
                ->whereKey($participation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingForRow = $participation->entries()
                ->where('entry_type', ParticipationEntryType::OpeningBalance)
                ->where('legacy_source_row_id', $row->id)
                ->exists();

            if ($existingForRow) {
                throw new RuntimeException('PSN opening entry already exists for this source row.');
            }

            $unitValue = (int) ($data['unit_value'] ?? $business->current_unit_value);
            $amount = $units * $unitValue;
            FinancialInvariants::assertOpeningBalance($units, $unitValue, $amount);
            FinancialInvariants::assertNonNegativeResultingUnits($participation->currentUnits(), $units);

            $shareReference = isset($payload['share_reference'])
                ? trim((string) $payload['share_reference'])
                : ($data['share_reference'] ?? null);

            ParticipationEntry::query()->create([
                'business_participation_id' => $participation->id,
                'entry_type' => ParticipationEntryType::OpeningBalance,
                'units' => $units,
                'unit_value' => $unitValue,
                'amount' => $amount,
                'effective_date_start' => $data['effective_date_start'] ?? null,
                'effective_date_end' => $data['effective_date_end'] ?? null,
                'share_reference' => $shareReference !== '' ? $shareReference : null,
                'units_origin' => ParticipationUnitsOrigin::ManualReconciliation,
                'source_type' => CooperativeSourceType::LegacyImport,
                'import_batch_id' => $batch->id,
                'legacy_source_row_id' => $row->id,
                'notes' => $note,
                'provenance' => [
                    'reconciliation' => 'manual_reconciliation',
                    'import_batch_id' => $batch->id,
                    'legacy_source_row_id' => $row->id,
                    'source_row_number' => $row->source_row_number,
                    'operator_id' => $operator->id,
                    'resolved_units' => $units,
                    'unit_value' => $unitValue,
                    'share_reference' => $shareReference,
                    'reconciliation_note' => $note,
                    'reconciled_at' => now()->toIso8601String(),
                ],
            ]);

            $data['components']['psn']['status'] = CooperativeImportComponentStatus::Committed->value;
            $data['units'] = $units;
            $data['units_origin'] = ParticipationUnitsOrigin::ManualReconciliation->value;
            if ($shareReference !== '') {
                $data['share_reference'] = $shareReference;
            }

            $row->forceFill([
                'normalized_data' => $data,
                'psn_committed_at' => now(),
            ])->save();

            $this->finalizeRowIfComplete($row, $data);

            $this->resolvePsnReviewItems($batch, $row, $operator, $outcome, $note, [
                'resolved_units' => $units,
                'unit_value' => $unitValue,
                'share_reference' => $shareReference,
                'reconciliation' => 'manual_reconciliation',
            ]);

            return $row->fresh(['matchedMember', 'createdMember']);
        });
    }

    private function recordPsnFollowup(
        CooperativeImportBatch $batch,
        CooperativeImportRow $row,
        User $operator,
        ?string $note,
    ): void {
        $this->resolvePsnReviewItems($batch, $row, $operator, CooperativeImportReviewOutcome::UnresolvedRequiresFollowup, $note, [
            'decision' => 'requires_followup',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function markPsnResolvedWithoutEntry(CooperativeImportRow $row, array $data, ?string $note): void
    {
        $data['components']['psn']['status'] = CooperativeImportComponentStatus::None->value;
        $data['units_origin'] = ParticipationUnitsOrigin::None->value;

        $row->forceFill([
            'normalized_data' => $data,
            'psn_committed_at' => now(),
            'review_notes' => trim(((string) $row->review_notes)."\n".($note ?? 'Confirmed no PSN participation.')),
        ])->save();

        $this->finalizeRowIfComplete($row, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function finalizeRowIfComplete(CooperativeImportRow $row, array $data): void
    {
        $components = $data['components'] ?? [];
        $pendingReview = collect($components)
            ->contains(fn ($component) => ($component['status'] ?? null) === CooperativeImportComponentStatus::NeedsReview->value);

        if (! $pendingReview) {
            $row->forceFill([
                'validation_status' => \App\Enums\CooperativeImportRowStatus::Committed,
                'committed_at' => $row->committed_at ?? now(),
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $auditMetadata
     */
    private function resolvePsnReviewItems(
        CooperativeImportBatch $batch,
        CooperativeImportRow $row,
        User $operator,
        CooperativeImportReviewOutcome $outcome,
        ?string $note,
        array $auditMetadata,
    ): void {
        $items = CooperativeImportReviewItem::query()
            ->where('batch_id', $batch->id)
            ->where('import_row_id', $row->id)
            ->where('category', CooperativeImportReviewCategory::PsnUnconfirmed)
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            CooperativeImportReviewAction::query()->create([
                'review_item_id' => $item->id,
                'batch_id' => $batch->id,
                'import_row_id' => $row->id,
                'category' => $item->category,
                'warning_code' => $item->warning_code,
                'action' => 'psn_reconciliation',
                'outcome' => $outcome,
                'operator_id' => $operator->id,
                'administrative_note' => $note,
                'audit_metadata' => $auditMetadata,
                'created_at' => now(),
            ]);

            if ($outcome === CooperativeImportReviewOutcome::UnresolvedRequiresFollowup) {
                continue;
            }

            $status = $outcome === CooperativeImportReviewOutcome::AcceptedAsIs
                ? CooperativeImportReviewStatus::AcceptedAsIs
                : CooperativeImportReviewStatus::Resolved;

            $item->forceFill([
                'status' => $status,
                'outcome' => $outcome,
                'resolved_by' => $operator->id,
                'resolved_at' => now(),
                'administrative_note' => $note,
                'audit_metadata' => array_merge($item->audit_metadata ?? [], $auditMetadata),
            ])->save();
        }
    }

    private function memberForRow(CooperativeImportRow $row): Member
    {
        $memberId = $row->created_member_id ?? $row->matched_member_id;

        if ($memberId === null) {
            throw new RuntimeException('No canonical Member is linked to this import row.');
        }

        return Member::query()->findOrFail($memberId);
    }
}
