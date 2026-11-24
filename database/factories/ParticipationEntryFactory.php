<?php

namespace Database\Factories;

use App\Enums\CooperativeSourceType;
use App\Enums\ParticipationEntryType;
use App\Enums\ParticipationUnitsOrigin;
use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Models\ParticipationEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParticipationEntry>
 */
class ParticipationEntryFactory extends Factory
{
    protected $model = ParticipationEntry::class;

    public function definition(): array
    {
        $units = 1;
        $unitValue = (int) CooperativeBusiness::psn()->current_unit_value;

        return [
            'business_participation_id' => BusinessParticipation::factory(),
            'entry_type' => ParticipationEntryType::OpeningBalance,
            'units' => $units,
            'unit_value' => $unitValue,
            'amount' => $units * $unitValue,
            'effective_date_start' => null,
            'effective_date_end' => null,
            'share_reference' => null,
            'units_origin' => ParticipationUnitsOrigin::Inferred,
            'source_type' => CooperativeSourceType::Staff,
            'import_batch_id' => null,
            'legacy_source_row_id' => null,
            'notes' => null,
            'provenance' => null,
            'confirmation_token' => null,
        ];
    }
}
