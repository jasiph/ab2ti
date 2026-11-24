<?php

namespace Tests\Unit\Cooperative;

use App\Support\Cooperative\FinancialInvariants;
use InvalidArgumentException;
use Tests\TestCase;

class FinancialInvariantsTest extends TestCase
{
    public function test_opening_amount_must_equal_units_times_unit_value(): void
    {
        FinancialInvariants::assertOpeningBalance(50, 1_000_000, 50_000_000);

        $this->expectException(InvalidArgumentException::class);
        FinancialInvariants::assertOpeningBalance(50, 1_000_000, 49_000_000);
    }

    public function test_resulting_units_must_not_become_negative(): void
    {
        FinancialInvariants::assertNonNegativeResultingUnits(2, -2);

        $this->expectException(InvalidArgumentException::class);
        FinancialInvariants::assertNonNegativeResultingUnits(1, -2);
    }

    public function test_adjustments_require_reason_and_preserve_amount_identity(): void
    {
        FinancialInvariants::assertAdjustment(-2, 1_000_000, -2_000_000, 'reversal');

        $this->expectException(InvalidArgumentException::class);
        FinancialInvariants::assertAdjustment(-2, 1_000_000, -2_000_000, '  ');
    }
}
