<?php

namespace App\Enums;

enum CooperativeImportReviewCategory: string
{
    case ProfileIncomplete = 'profile_incomplete';
    case SharedEmail = 'shared_email';
    case SharedPhone = 'shared_phone';
    case DuplicateNik = 'duplicate_nik';
    case DuplicatePerson = 'duplicate_person';
    case LegacyIdentity = 'legacy_identity';
    case PsnUnconfirmed = 'psn_unconfirmed';
    case OtherLegacy = 'other_legacy';

    /**
     * @return list<string>
     */
    public static function profileWarningCodes(): array
    {
        return [
            'missing_email',
            'missing_phone',
            'missing_address',
            'unparseable_email',
            'unparseable_phone',
        ];
    }

    /**
     * @return list<string>
     */
    public static function duplicateNikCodes(): array
    {
        return [
            'possible_duplicate_nik',
            'source_duplicate_nik',
        ];
    }

    /**
     * @return list<string>
     */
    public static function duplicatePersonCodes(): array
    {
        return [
            'possible_same_person',
            'source_duplicate_identifier',
            'source_duplicate_email',
            'source_duplicate_phone',
        ];
    }

    /**
     * @return list<string>
     */
    public static function legacyIdentityCodes(): array
    {
        return [
            'ambiguous_identifier',
            'ambiguous_member',
            'name_collision',
            'unmatched_member',
        ];
    }

    /**
     * @return list<string>
     */
    public static function psnShareCodes(): array
    {
        return [
            'amount_not_divisible_by_unit_value',
            'non_integral_legacy_total',
            'share_unit_mismatch',
            'unknown_share_format',
            'total_mismatch',
            'existing_psn_participation',
        ];
    }

    /**
     * @return list<string>
     */
    public static function otherLegacyCodes(): array
    {
        return [
            'malformed_date',
            'malformed_principal',
            'malformed_voluntary',
            'malformed_total',
            'existing_fund_opening',
        ];
    }
}
