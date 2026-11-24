<?php

namespace App\Support\Cooperative;

use App\Enums\CooperativeIdentityClass;
use App\Enums\CooperativeImportMode;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportRow;
use App\Support\Cooperative\Import\LegacyImportReplacer;

final class CooperativeImportViews
{
    /**
     * @return array<string, mixed>
     */
    public static function adminListItem(CooperativeImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'original_filename' => $batch->original_filename,
            'file_hash' => $batch->file_hash,
            'status' => $batch->status->value,
            'created_at' => $batch->created_at?->toIso8601String(),
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'duplicate_of_batch_id' => $batch->duplicate_of_batch_id,
            'worksheet_name' => $batch->worksheet_name,
            'summary' => $batch->summary_json,
            'creator' => $batch->creator === null ? null : [
                'id' => $batch->creator->id,
                'name' => $batch->creator->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminDetail(CooperativeImportBatch $batch): array
    {
        return [
            ...self::adminListItem($batch),
            'reversed_at' => $batch->reversed_at?->toIso8601String(),
            'import_mode' => $batch->import_mode?->value ?? CooperativeImportMode::Append->value,
            'replaces_batch_id' => $batch->replaces_batch_id,
            'replaced_by_batch_id' => $batch->replaced_by_batch_id,
            'can_commit' => $batch->canCommit(CooperativeImportMode::Append),
            'can_commit_replace' => $batch->canCommit(CooperativeImportMode::ReplaceLegacy),
            'can_reverse' => $batch->canReverse(),
            'preview_does_not_change_canonical' => true,
            'nik_not_a_match_key' => true,
            'historical_funds_are_source_of_participation' => true,
            'needs_sheet_selection' => $batch->needsSheetSelection(),
            'worksheet_name' => $batch->worksheet_name,
            'worksheet_visible' => $batch->worksheet_visible,
            'header_row_number' => $batch->header_row_number,
            'worksheets' => $batch->worksheets_json ?? [],
            'creates_user_accounts' => false,
            'creates_membership_applications' => false,
            'legacy_member_bootstrap' => true,
            'component_commit' => true,
            'replacement_impact' => (new LegacyImportReplacer)->impactSummary(),
            'default_import_mode' => CooperativeImportMode::Append->value,
        ];
    }

    /**
     * List payload: enough to review status, without raw spreadsheet JSON or NIK.
     *
     * @return array<string, mixed>
     */
    public static function adminRowSummary(CooperativeImportRow $row): array
    {
        $data = $row->normalized_data ?? [];
        $errors = $row->validation_errors ?? [];
        $member = $row->matchedMember;

        return [
            'id' => $row->id,
            'source_row_number' => $row->source_row_number,
            'validation_status' => $row->validation_status->value,
            'validation_errors' => $errors,
            'validation_error_labels' => self::errorLabels($errors),
            'review_notes' => $row->review_notes,
            'committed_at' => $row->committed_at?->toIso8601String(),
            'member_committed_at' => $row->member_committed_at?->toIso8601String(),
            'membership_committed_at' => $row->membership_committed_at?->toIso8601String(),
            'funds_committed_at' => $row->funds_committed_at?->toIso8601String(),
            'psn_committed_at' => $row->psn_committed_at?->toIso8601String(),
            'match_method' => $row->match_method?->value,
            'matched_member_id' => $row->matched_member_id,
            'created_member_id' => $row->created_member_id,
            'matched_at' => $row->matched_at?->toIso8601String(),
            'matched_by' => $row->matched_by,
            'matched_member' => $member === null ? null : [
                'id' => $member->id,
                'legal_name' => $member->legal_name,
                'membership_number' => $member->membership_number,
                'email' => $member->email,
            ],
            'highlights' => self::highlights($errors, $data),
            'source' => [
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone_normalized'] ?? $data['phone_raw'] ?? null,
                'identity_class' => $data['identity_class'] ?? null,
                'identity_present' => ($data['identity_raw'] ?? null) !== null && ($data['identity_raw'] ?? '') !== '',
                'nik_present_not_used_for_match' => (bool) ($data['nik_not_used_for_match'] ?? false),
                'share_raw' => $data['share_raw'] ?? null,
                'date_raw' => $data['date_raw'] ?? null,
                'principal_amount' => $data['principal_amount'] ?? null,
                'voluntary_amount' => $data['voluntary_amount'] ?? null,
                'source_total' => $data['source_total'] ?? null,
            ],
            'normalized' => [
                'units' => $data['units'] ?? null,
                'units_origin' => $data['units_origin'] ?? null,
                'explicit_units' => $data['explicit_units'] ?? null,
                'inferred_units' => $data['inferred_units'] ?? null,
                'share_reference' => $data['share_reference'] ?? null,
                'computed_total' => $data['computed_total'] ?? null,
                'total_matches' => $data['total_matches'] ?? null,
                'share_reconciled' => $data['share_reconciled'] ?? null,
                'propose_create_member' => $data['propose_create_member'] ?? false,
                'propose_create_membership' => $data['propose_create_membership'] ?? false,
                'member_resolution' => $data['member_resolution'] ?? null,
                'profile_warnings' => $data['profile_warnings'] ?? [],
                'components' => $data['components'] ?? null,
                'review_categories' => $data['review_categories'] ?? [
                    'identity' => [],
                    'financial' => [],
                    'date' => [],
                    'share' => [],
                    'profile' => [],
                ],
            ],
            'reconciliation' => [
                'total_matches' => (bool) ($data['total_matches'] ?? true),
                'share_reconciled' => (bool) ($data['share_reconciled'] ?? true),
                'units_origin' => $data['units_origin'] ?? null,
                'historical_funds_are_source_of_participation' => true,
            ],
        ];
    }

    /**
     * Authorized inspect payload. Includes decrypted staging JSON for one row.
     *
     * @return array<string, mixed>
     */
    public static function adminRowDetail(CooperativeImportRow $row): array
    {
        $data = $row->normalized_data ?? [];

        return [
            ...self::adminRowSummary($row),
            'raw_data' => self::redactIdentityForDisplay($row->raw_data ?? []),
            'normalized_data' => [
                ...$data,
                'identity_value' => ($data['identity_class'] ?? null) === CooperativeIdentityClass::ValidNik->value
                    ? null
                    : ($data['identity_value'] ?? null),
                'identity_raw_present' => ($data['identity_raw'] ?? null) !== null,
            ],
        ];
    }

    /**
     * @param  list<string>  $errors
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function highlights(array $errors, array $data): array
    {
        $map = [
            'unmatched_member' => 'unmatched_identity',
            'name_collision' => 'unmatched_identity',
            'source_duplicate_nik' => 'conflicting_identifier',
            'source_duplicate_identifier' => 'conflicting_identifier',
            'source_duplicate_email' => 'conflicting_identifier',
            'source_duplicate_phone' => 'conflicting_identifier',
            'ambiguous_member' => 'conflicting_identifier',
            'ambiguous_identifier' => 'conflicting_identifier',
            'total_mismatch' => 'total_mismatch',
            'share_unit_mismatch' => 'share_mismatch',
            'amount_not_divisible_by_unit_value' => 'ambiguous_units',
            'non_integral_legacy_total' => 'ambiguous_units',
            'malformed_date' => 'invalid_date',
            'unknown_share_format' => 'ambiguous_units',
            'duplicate_committed_source' => 'duplicate_source',
            'nik_not_used_for_match' => 'nik_not_a_match_key',
        ];

        $highlights = [];

        foreach ($errors as $error) {
            if (isset($map[$error])) {
                $highlights[] = $map[$error];
            }
        }

        if (($data['nik_not_used_for_match'] ?? false) === true) {
            $highlights[] = 'nik_not_a_match_key';
        }

        return array_values(array_unique($highlights));
    }

    /**
     * @param  list<string>  $errors
     * @return list<array{code: string, label: string}>
     */
    private static function errorLabels(array $errors): array
    {
        $labels = [
            'missing_name' => 'Name is missing',
            'malformed_principal' => 'Principal amount is not a safe rupiah value',
            'malformed_voluntary' => 'Voluntary amount is not a safe rupiah value',
            'malformed_total' => 'Total amount is not a safe rupiah value',
            'negative_principal' => 'Principal opening cannot be negative',
            'negative_voluntary' => 'Voluntary opening cannot be negative',
            'negative_total' => 'Total opening cannot be negative',
            'unknown_share_format' => 'Share reference is invalid or ambiguous',
            'malformed_date' => 'Date could not be parsed without guessing',
            'malformed_phone' => 'Phone number is not a recognized Indonesian form',
            'total_mismatch' => 'Pokok + sukarela does not equal Total',
            'amount_not_divisible_by_unit_value' => 'Amount is not an exact number of PSN shares',
            'non_integral_legacy_total' => 'Historical Cooperative funds are not an exact multiple of the PSN unit value and the source does not explicitly state the share count.',
            'share_unit_mismatch' => 'Explicit share count does not match the monetary amount',
            'ambiguous_identifier' => 'Identifier is not a known AB2TI number (NIK is not a match key)',
            'unmatched_member' => 'No unique Member match (name alone never binds)',
            'name_collision' => 'A Member with this name already exists; name alone never binds',
            'source_duplicate_nik' => 'Duplicate NIK appears more than once in this worksheet (staging only; not stored on Member)',
            'source_duplicate_identifier' => 'Duplicate AB2TI historical identifier appears more than once in this worksheet',
            'source_duplicate_email' => 'Duplicate email appears more than once in this worksheet',
            'source_duplicate_phone' => 'Duplicate phone appears more than once in this worksheet',
            'possible_duplicate_nik' => 'Possible duplicate NIK inside the authorized source (staging only; not stored on Member)',
            'possible_duplicate_identifier' => 'Possible duplicate AB2TI historical identifier inside the authorized source',
            'shared_email' => 'Shared email appears on more than one source row',
            'shared_phone' => 'Shared phone appears on more than one source row',
            'possible_same_person' => 'Source evidence suggests the same person may appear more than once',
            'missing_email' => 'Profile warning: email is missing',
            'missing_phone' => 'Profile warning: phone is missing',
            'missing_address' => 'Profile warning: address is missing',
            'unparseable_email' => 'Profile warning: email is present but not a valid address',
            'unparseable_phone' => 'Profile warning: phone is present but not a recognized Indonesian form',
            'ambiguous_member' => 'Matching signals conflict or match more than one Member',
            'existing_psn_participation' => 'This Member already has PSN participation',
            'existing_fund_opening' => 'This Member already has a historical fund opening',
            'duplicate_committed_source' => 'This source row was already committed',
            'empty_row' => 'Row has no usable financial data',
            'commit_failed' => 'Commit failed; the row was not recorded',
        ];

        return array_map(
            fn (string $code) => [
                'code' => $code,
                'label' => $labels[$code] ?? $code,
            ],
            $errors,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function redactIdentityForDisplay(array $raw): array
    {
        foreach (['identity', 'nik', 'NIK'] as $key) {
            if (isset($raw[$key]) && is_string($raw[$key]) && strlen($raw[$key]) >= 16) {
                $raw[$key] = '[classified]';
            }
        }

        if (isset($raw['_source']) && is_array($raw['_source'])) {
            foreach ($raw['_source'] as $header => $value) {
                if (is_string($header) && preg_match('/nik|identitas|ktp/i', $header) === 1) {
                    $raw['_source'][$header] = '[classified]';
                }
            }
        }

        return $raw;
    }
}
