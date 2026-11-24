<?php

namespace Database\Factories;

use App\Enums\CooperativeFundEntryType;
use App\Enums\CooperativeFundType;
use App\Enums\CooperativeSourceType;
use App\Models\CooperativeFundEntry;
use App\Models\CooperativeMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CooperativeFundEntry>
 */
class CooperativeFundEntryFactory extends Factory
{
    protected $model = CooperativeFundEntry::class;

    public function definition(): array
    {
        return [
            'cooperative_membership_id' => CooperativeMembership::factory(),
            'fund_type' => CooperativeFundType::PrincipalSaving,
            'entry_type' => CooperativeFundEntryType::OpeningBalance,
            'amount' => 1_000_000,
            'effective_date_start' => null,
            'effective_date_end' => null,
            'source_type' => CooperativeSourceType::LegacyImport,
            'import_batch_id' => null,
            'legacy_source_row_id' => null,
            'notes' => null,
            'provenance' => null,
        ];
    }

    public function voluntary(): static
    {
        return $this->state(fn (): array => [
            'fund_type' => CooperativeFundType::VoluntarySaving,
        ]);
    }
}
