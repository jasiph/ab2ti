<?php

namespace App\Support\Cooperative;

use InvalidArgumentException;

final class FinancialInvariants
{
    public static function assertOpeningBalance(int $units, int $unitValue, int $amount): void
    {
        if ($units <= 0) {
            throw new InvalidArgumentException('Opening participation units must be positive.');
        }

        if ($unitValue <= 0) {
            throw new InvalidArgumentException('Unit value must be a positive integer IDR amount.');
        }

        if ($amount !== $units * $unitValue) {
            throw new InvalidArgumentException('Opening amount must equal units × unit value.');
        }
    }

    public static function assertAdjustment(int $units, int $unitValue, int $amount, ?string $reason): void
    {
        if ($units === 0) {
            throw new InvalidArgumentException('Adjustment units must not be zero.');
        }

        if ($unitValue <= 0) {
            throw new InvalidArgumentException('Unit value must be a positive integer IDR amount.');
        }

        if ($amount !== $units * $unitValue) {
            throw new InvalidArgumentException('Adjustment amount must equal units × unit value.');
        }

        if ($reason === null || trim($reason) === '') {
            throw new InvalidArgumentException('Adjustments require a provenance reason.');
        }
    }

    public static function assertNonNegativeResultingUnits(int $currentUnits, int $delta): void
    {
        if (($currentUnits + $delta) < 0) {
            throw new InvalidArgumentException('Resulting PSN units must not be negative.');
        }
    }

    public static function assertFundOpening(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Historical fund openings must be a positive integer IDR amount.');
        }
    }
}
