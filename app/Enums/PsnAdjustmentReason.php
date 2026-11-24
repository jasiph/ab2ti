<?php

namespace App\Enums;

enum PsnAdjustmentReason: string
{
    case CorrectionFromDocument = 'correction_from_document';
    case AdministrativeCorrection = 'administrative_correction';
    case AdditionalVerifiedParticipation = 'additional_verified_participation';
    case ReductionVerified = 'reduction_verified';
    case ReconciliationFollowup = 'reconciliation_followup';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
