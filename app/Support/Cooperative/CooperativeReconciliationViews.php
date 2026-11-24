<?php

namespace App\Support\Cooperative;

use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewOutcome;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportReviewItem;
use App\Models\CooperativeImportRow;
use App\Support\Cooperative\Import\ImportReviewInventory;
use App\Support\Cooperative\Import\PsnReconciliationReadiness;

final class CooperativeReconciliationViews
{
    /**
     * @return array<string, mixed>
     */
    public static function dashboard(
        CooperativeImportBatch $batch,
        ImportReviewInventory $inventory,
        PsnReconciliationReadiness $psnReadiness,
    ): array {
        return [
            'batch' => CooperativeImportViews::adminDetail($batch->load('creator')),
            'summary' => $inventory->summary($batch),
            'psn_readiness' => $psnReadiness->assess($batch),
            'category_labels' => self::categoryLabels(),
            'outcome_labels' => self::outcomeLabels(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function listItem(CooperativeImportReviewItem $item): array
    {
        $row = $item->importRow;
        $data = $row?->normalized_data ?? [];
        $member = $row?->createdMember ?? $row?->matchedMember;

        return [
            'id' => $item->id,
            'category' => $item->category->value,
            'category_label' => self::categoryLabels()[$item->category->value] ?? $item->category->value,
            'warning_code' => $item->warning_code,
            'warning_label' => CooperativeImportViews::adminRowSummary($row ?? new CooperativeImportRow)['validation_error_labels']
                ? collect(CooperativeImportViews::adminRowSummary($row ?? new CooperativeImportRow)['validation_error_labels'])
                    ->firstWhere('code', $item->warning_code)['label'] ?? $item->warning_code
                : $item->warning_code,
            'status' => $item->status->value,
            'outcome' => $item->outcome?->value,
            'outcome_label' => $item->outcome ? (self::outcomeLabels()[$item->outcome->value] ?? $item->outcome->value) : null,
            'resolved_at' => $item->resolved_at?->toIso8601String(),
            'administrative_note' => $item->administrative_note,
            'import_row_id' => $item->import_row_id,
            'source_row_number' => $row?->source_row_number,
            'member' => $member === null ? null : [
                'id' => $member->id,
                'legal_name' => $member->legal_name,
                'membership_number' => $member->membership_number,
            ],
            'psn_review' => ($data['components']['psn']['status'] ?? null) === 'needs_review',
            'profile_warning' => in_array($item->warning_code, CooperativeImportReviewCategory::profileWarningCodes(), true),
            'duplicate_warning' => in_array($item->category, [
                CooperativeImportReviewCategory::DuplicateNik,
                CooperativeImportReviewCategory::DuplicatePerson,
                CooperativeImportReviewCategory::SharedEmail,
                CooperativeImportReviewCategory::SharedPhone,
            ], true),
            'available_actions' => self::availableActions($item),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function psnInspect(CooperativeImportRow $row): array
    {
        $data = $row->normalized_data ?? [];
        $member = $row->createdMember ?? $row->matchedMember;
        $shareCodes = array_values(array_unique([
            ...($data['review_categories']['share'] ?? []),
            ...array_intersect($row->validation_errors ?? [], CooperativeImportReviewCategory::psnShareCodes()),
        ]));

        return [
            'row' => CooperativeImportViews::adminRowDetail($row),
            'member' => $member === null ? null : [
                'id' => $member->id,
                'legal_name' => $member->legal_name,
                'membership_number' => $member->membership_number,
            ],
            'psn_context' => [
                'share_raw' => $data['share_raw'] ?? null,
                'share_reference' => $data['share_reference'] ?? null,
                'principal_amount' => $data['principal_amount'] ?? null,
                'voluntary_amount' => $data['voluntary_amount'] ?? null,
                'source_total' => $data['source_total'] ?? null,
                'computed_total' => $data['computed_total'] ?? null,
                'unit_value' => $data['unit_value'] ?? null,
                'units' => $data['units'] ?? null,
                'units_origin' => $data['units_origin'] ?? null,
                'legacy_notes' => $data['legacy_notes'] ?? null,
                'failure_codes' => $shareCodes,
                'failure_labels' => array_map(
                    fn (string $code) => $code,
                    $shareCodes,
                ),
                'psn_committed_at' => $row->psn_committed_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return list<array{value: string, label_id: string, label_en: string}>
     */
    public static function availableActions(CooperativeImportReviewItem $item): array
    {
        return match ($item->category) {
            CooperativeImportReviewCategory::ProfileIncomplete => [
                ['value' => CooperativeImportReviewOutcome::Corrected->value, 'label_id' => 'Perbarui data', 'label_en' => 'Update data'],
                ['value' => CooperativeImportReviewOutcome::AcceptedAsIs->value, 'label_id' => 'Terima tidak lengkap', 'label_en' => 'Accept as incomplete'],
            ],
            CooperativeImportReviewCategory::SharedEmail,
            CooperativeImportReviewCategory::SharedPhone => [
                ['value' => CooperativeImportReviewOutcome::ConfirmedDistinct->value, 'label_id' => 'Konfirmasi anggota berbeda', 'label_en' => 'Confirm distinct members'],
                ['value' => CooperativeImportReviewOutcome::AcceptedSharedContact->value, 'label_id' => 'Terima kontak bersama', 'label_en' => 'Accept shared contact'],
                ['value' => CooperativeImportReviewOutcome::UnresolvedRequiresFollowup->value, 'label_id' => 'Perlu tindak lanjut', 'label_en' => 'Requires follow-up'],
            ],
            CooperativeImportReviewCategory::DuplicateNik,
            CooperativeImportReviewCategory::DuplicatePerson => [
                ['value' => CooperativeImportReviewOutcome::ConfirmedDistinct->value, 'label_id' => 'Konfirmasi berbeda', 'label_en' => 'Confirm distinct'],
                ['value' => CooperativeImportReviewOutcome::ConfirmedSamePerson->value, 'label_id' => 'Tandai kemungkinan orang sama', 'label_en' => 'Mark possible same person'],
                ['value' => CooperativeImportReviewOutcome::UnresolvedRequiresFollowup->value, 'label_id' => 'Perlu tindak lanjut', 'label_en' => 'Requires follow-up'],
            ],
            CooperativeImportReviewCategory::PsnUnconfirmed => [
                ['value' => CooperativeImportReviewOutcome::ManualReconciliation->value, 'label_id' => 'Konfirmasi unit', 'label_en' => 'Confirm units'],
                ['value' => CooperativeImportReviewOutcome::ConfirmedNoPsnParticipation->value, 'label_id' => 'Konfirmasi tanpa partisipasi', 'label_en' => 'Confirm no participation'],
                ['value' => CooperativeImportReviewOutcome::UnresolvedRequiresFollowup->value, 'label_id' => 'Perlu tindak lanjut', 'label_en' => 'Requires follow-up'],
            ],
            CooperativeImportReviewCategory::OtherLegacy => [
                ['value' => CooperativeImportReviewOutcome::SourceVerified->value, 'label_id' => 'Verifikasi sumber', 'label_en' => 'Source verified'],
                ['value' => CooperativeImportReviewOutcome::UnresolvedRequiresFollowup->value, 'label_id' => 'Perlu tindak lanjut', 'label_en' => 'Requires follow-up'],
            ],
            default => [
                ['value' => CooperativeImportReviewOutcome::SourceVerified->value, 'label_id' => 'Verifikasi sumber', 'label_en' => 'Source verified'],
                ['value' => CooperativeImportReviewOutcome::UnresolvedRequiresFollowup->value, 'label_id' => 'Perlu tindak lanjut', 'label_en' => 'Requires follow-up'],
            ],
        };
    }

    /**
     * @return array<string, array{label_id: string, label_en: string}>
     */
    public static function categoryLabels(): array
    {
        return [
            CooperativeImportReviewCategory::ProfileIncomplete->value => [
                'label_id' => 'Profil belum lengkap',
                'label_en' => 'Incomplete profile',
            ],
            CooperativeImportReviewCategory::SharedEmail->value => [
                'label_id' => 'Email digunakan bersama',
                'label_en' => 'Shared email',
            ],
            CooperativeImportReviewCategory::SharedPhone->value => [
                'label_id' => 'Nomor telepon digunakan bersama',
                'label_en' => 'Shared phone',
            ],
            CooperativeImportReviewCategory::DuplicateNik->value => [
                'label_id' => 'Dugaan NIK ganda',
                'label_en' => 'Possible duplicate NIK',
            ],
            CooperativeImportReviewCategory::DuplicatePerson->value => [
                'label_id' => 'Dugaan orang yang sama',
                'label_en' => 'Possible same person',
            ],
            CooperativeImportReviewCategory::LegacyIdentity->value => [
                'label_id' => 'Identitas legacy',
                'label_en' => 'Legacy identity',
            ],
            CooperativeImportReviewCategory::PsnUnconfirmed->value => [
                'label_id' => 'PSN belum terkonfirmasi',
                'label_en' => 'PSN unconfirmed',
            ],
            CooperativeImportReviewCategory::OtherLegacy->value => [
                'label_id' => 'Data lain perlu diperiksa',
                'label_en' => 'Other data needs review',
            ],
        ];
    }

    /**
     * @return array<string, array{label_id: string, label_en: string}>
     */
    public static function outcomeLabels(): array
    {
        return [
            CooperativeImportReviewOutcome::Corrected->value => [
                'label_id' => 'Diperbaiki',
                'label_en' => 'Corrected',
            ],
            CooperativeImportReviewOutcome::AcceptedAsIs->value => [
                'label_id' => 'Diterima apa adanya',
                'label_en' => 'Accepted as-is',
            ],
            CooperativeImportReviewOutcome::ConfirmedDistinct->value => [
                'label_id' => 'Dikonfirmasi berbeda',
                'label_en' => 'Confirmed distinct',
            ],
            CooperativeImportReviewOutcome::ConfirmedSamePerson->value => [
                'label_id' => 'Kemungkinan orang sama',
                'label_en' => 'Possible same person',
            ],
            CooperativeImportReviewOutcome::AcceptedSharedContact->value => [
                'label_id' => 'Kontak bersama diterima',
                'label_en' => 'Shared contact accepted',
            ],
            CooperativeImportReviewOutcome::SourceVerified->value => [
                'label_id' => 'Sumber diverifikasi',
                'label_en' => 'Source verified',
            ],
            CooperativeImportReviewOutcome::UnresolvedRequiresFollowup->value => [
                'label_id' => 'Perlu tindak lanjut',
                'label_en' => 'Requires follow-up',
            ],
            CooperativeImportReviewOutcome::ConfirmedNoPsnParticipation->value => [
                'label_id' => 'Tanpa partisipasi PSN',
                'label_en' => 'No PSN participation',
            ],
            CooperativeImportReviewOutcome::ManualReconciliation->value => [
                'label_id' => 'Rekonsiliasi manual',
                'label_en' => 'Manual reconciliation',
            ],
        ];
    }
}
