<?php

namespace App\Exceptions\Cooperative;

use RuntimeException;

class PsnAdjustmentRejectedException extends RuntimeException
{
    public const NO_CHANGE = 'no_change';

    public const INACTIVE_MEMBERSHIP = 'inactive_membership';

    public const UNRESOLVED_PSN = 'unresolved_psn';

    public const RESOURCE_MISMATCH = 'resource_mismatch';

    public const TOKEN_REUSED = 'token_reused';

    public const INVALID_CORRECTION_TARGET = 'invalid_correction_target';

    public const FUTURE_EFFECTIVE_DATE = 'future_effective_date';

    public function __construct(
        public readonly string $rejectionCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function noChange(): self
    {
        return new self(self::NO_CHANGE, __('No PSN share change.'));
    }

    public static function inactiveMembership(): self
    {
        return new self(
            self::INACTIVE_MEMBERSHIP,
            __('Ordinary PSN share adjustment is unavailable while Cooperative membership is inactive.'),
        );
    }

    public static function unresolvedPsn(): self
    {
        return new self(
            self::UNRESOLVED_PSN,
            __('PSN needs reconciliation before ordinary share adjustment.'),
        );
    }

    public static function resourceMismatch(): self
    {
        return new self(
            self::RESOURCE_MISMATCH,
            __('The submitted PSN resources do not match this Cooperative membership.'),
        );
    }

    public static function tokenReused(): self
    {
        return new self(
            self::TOKEN_REUSED,
            __('This confirmation request was already used.'),
        );
    }

    public static function invalidCorrectionTarget(): self
    {
        return new self(
            self::INVALID_CORRECTION_TARGET,
            __('The previous entry selected for correction does not belong to this PSN ledger.'),
        );
    }

    public static function futureEffectiveDate(): self
    {
        return new self(
            self::FUTURE_EFFECTIVE_DATE,
            __('The effective date cannot be in the future for ordinary administrative PSN adjustments.'),
        );
    }
}
