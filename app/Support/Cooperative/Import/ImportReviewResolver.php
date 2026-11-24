<?php

namespace App\Support\Cooperative\Import;

use App\Actions\Membership\UpdateMemberContact;
use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewOutcome;
use App\Enums\CooperativeImportReviewStatus;
use App\Models\CooperativeImportReviewAction;
use App\Models\CooperativeImportReviewItem;
use App\Models\CooperativeImportRow;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ImportReviewResolver
{
    public function __construct(
        private readonly UpdateMemberContact $updateContact = new UpdateMemberContact,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolve(
        CooperativeImportReviewItem $item,
        User $operator,
        CooperativeImportReviewOutcome $outcome,
        array $payload = [],
    ): CooperativeImportReviewItem {
        return DB::transaction(function () use ($item, $operator, $outcome, $payload) {
            $item = CooperativeImportReviewItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $row = CooperativeImportRow::query()->whereKey($item->import_row_id)->lockForUpdate()->firstOrFail();

            if ($item->batch_id !== $row->batch_id) {
                throw new RuntimeException('Review item does not belong to this import row.');
            }

            $auditMetadata = ['outcome' => $outcome->value];
            $note = isset($payload['administrative_note']) ? trim((string) $payload['administrative_note']) : null;

            if ($item->category === CooperativeImportReviewCategory::ProfileIncomplete) {
                $this->resolveProfile($item, $row, $operator, $outcome, $payload, $auditMetadata);
            }

            $status = $outcome === CooperativeImportReviewOutcome::AcceptedAsIs
                ? CooperativeImportReviewStatus::AcceptedAsIs
                : CooperativeImportReviewStatus::Resolved;

            $this->recordAction($item, $operator, 'resolve_review', $outcome, $note, $auditMetadata);

            $item->forceFill([
                'status' => $status,
                'outcome' => $outcome,
                'resolved_by' => $operator->id,
                'resolved_at' => now(),
                'administrative_note' => $note ?: $item->administrative_note,
                'audit_metadata' => array_merge($item->audit_metadata ?? [], $auditMetadata),
            ])->save();

            return $item->fresh(['resolvedByUser', 'importRow.matchedMember']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $auditMetadata
     */
    private function resolveProfile(
        CooperativeImportReviewItem $item,
        CooperativeImportRow $row,
        User $operator,
        CooperativeImportReviewOutcome $outcome,
        array $payload,
        array &$auditMetadata,
    ): void {
        if ($outcome === CooperativeImportReviewOutcome::AcceptedAsIs) {
            $auditMetadata['field'] = $item->warning_code;
            $auditMetadata['prior_presence'] = 'absent_or_invalid';
            $auditMetadata['decision'] = 'accepted_as_is';

            return;
        }

        if ($outcome !== CooperativeImportReviewOutcome::Corrected) {
            return;
        }

        $member = $this->memberForRow($row);
        $auditMetadata['field'] = $item->warning_code;
        $auditMetadata['prior_presence'] = $this->fieldPresence($member, $item->warning_code);

        $contactPayload = [
            'legal_name' => $member->legal_name,
            'email' => $member->email ?? '',
            'phone' => $member->phone ?? '',
            'address' => $member->address ?? '',
        ];

        if ($item->warning_code === 'missing_email' || $item->warning_code === 'unparseable_email') {
            $contactPayload['email'] = trim((string) ($payload['email'] ?? ''));
        }

        if ($item->warning_code === 'missing_phone' || $item->warning_code === 'unparseable_phone') {
            $contactPayload['phone'] = trim((string) ($payload['phone'] ?? ''));
        }

        if ($item->warning_code === 'missing_address') {
            $contactPayload['address'] = trim((string) ($payload['address'] ?? ''));
        }

        $this->updateContact->handle($member, $contactPayload);
        $auditMetadata['new_value_presence'] = $this->fieldPresence($member->fresh() ?? $member, $item->warning_code);
        $auditMetadata['operator_id'] = $operator->id;
    }

    private function memberForRow(CooperativeImportRow $row): Member
    {
        $memberId = $row->created_member_id ?? $row->matched_member_id;

        if ($memberId === null) {
            throw new RuntimeException('No canonical Member is linked to this import row.');
        }

        return Member::query()->findOrFail($memberId);
    }

    private function fieldPresence(Member $member, string $warningCode): string
    {
        return match ($warningCode) {
            'missing_email', 'unparseable_email' => ($member->email ?? '') !== '' ? 'present' : 'absent',
            'missing_phone', 'unparseable_phone' => ($member->phone ?? '') !== '' ? 'present' : 'absent',
            'missing_address' => ($member->address ?? '') !== '' ? 'present' : 'absent',
            default => 'unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $auditMetadata
     */
    private function recordAction(
        CooperativeImportReviewItem $item,
        User $operator,
        string $action,
        CooperativeImportReviewOutcome $outcome,
        ?string $note,
        array $auditMetadata,
    ): void {
        CooperativeImportReviewAction::query()->create([
            'review_item_id' => $item->id,
            'batch_id' => $item->batch_id,
            'import_row_id' => $item->import_row_id,
            'category' => $item->category,
            'warning_code' => $item->warning_code,
            'action' => $action,
            'outcome' => $outcome,
            'operator_id' => $operator->id,
            'administrative_note' => $note,
            'audit_metadata' => $auditMetadata,
            'created_at' => now(),
        ]);
    }
}
