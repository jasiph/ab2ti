<?php

namespace Tests\Feature\Cooperative;

use App\Enums\CooperativeBusinessStatus;
use App\Enums\ParticipationEntryType;
use App\Enums\ParticipationUnitsOrigin;
use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeMembership;
use App\Models\ParticipationEntry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CooperativePsnDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_psn_business_is_seeded(): void
    {
        $psn = CooperativeBusiness::psn();

        $this->assertSame('PSN', $psn->code);
        $this->assertSame('Penggilingan Padi', $psn->name);
        $this->assertSame(CooperativeBusinessStatus::Active, $psn->status);
        $this->assertSame('Saham PSN', $psn->participation_unit_name);
        $this->assertSame(1_000_000, (int) $psn->current_unit_value);
        $this->assertSame('PSN — Penggilingan Padi', $psn->publicLabel());
    }

    public function test_participation_is_unique_per_membership_and_business(): void
    {
        $membership = CooperativeMembership::factory()->create();
        $psn = CooperativeBusiness::psn();

        BusinessParticipation::factory()->create([
            'cooperative_membership_id' => $membership->id,
            'cooperative_business_id' => $psn->id,
        ]);

        $this->expectException(QueryException::class);

        BusinessParticipation::factory()->create([
            'cooperative_membership_id' => $membership->id,
            'cooperative_business_id' => $psn->id,
        ]);
    }

    public function test_current_units_are_derived_from_the_ledger(): void
    {
        $participation = BusinessParticipation::factory()->create();
        $unitValue = (int) CooperativeBusiness::psn()->current_unit_value;

        ParticipationEntry::factory()->create([
            'business_participation_id' => $participation->id,
            'entry_type' => ParticipationEntryType::OpeningBalance,
            'units' => 50,
            'unit_value' => $unitValue,
            'amount' => 50 * $unitValue,
            'share_reference' => '32-1-000181',
            'units_origin' => ParticipationUnitsOrigin::Explicit,
        ]);

        ParticipationEntry::factory()->create([
            'business_participation_id' => $participation->id,
            'entry_type' => ParticipationEntryType::Adjustment,
            'units' => -2,
            'unit_value' => $unitValue,
            'amount' => -2 * $unitValue,
            'units_origin' => ParticipationUnitsOrigin::Explicit,
        ]);

        $this->assertSame(48, $participation->currentUnits());
        $this->assertSame('32-1-000181', $participation->latestShareReference());
        $this->assertSame($unitValue, (int) $participation->entries()->first()->unit_value);
    }

    public function test_historical_unit_value_is_preserved_when_current_value_changes(): void
    {
        $participation = BusinessParticipation::factory()->create();
        $business = CooperativeBusiness::psn();

        ParticipationEntry::factory()->create([
            'business_participation_id' => $participation->id,
            'units' => 1,
            'unit_value' => 1_000_000,
            'amount' => 1_000_000,
        ]);

        $business->forceFill(['current_unit_value' => 2_000_000])->save();

        $this->assertSame(1_000_000, (int) $participation->entries()->first()->unit_value);
        $this->assertSame(2_000_000, (int) $business->fresh()->current_unit_value);
        $this->assertSame(2_000_000, $participation->fresh()->currentValue());
    }
}
