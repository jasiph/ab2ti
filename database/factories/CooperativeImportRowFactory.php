<?php

namespace Database\Factories;

use App\Enums\CooperativeImportRowStatus;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CooperativeImportRow>
 */
class CooperativeImportRowFactory extends Factory
{
    protected $model = CooperativeImportRow::class;

    public function definition(): array
    {
        return [
            'batch_id' => CooperativeImportBatch::factory(),
            'source_row_number' => fake()->unique()->numberBetween(2, 5000),
            'raw_data' => ['Nama' => fake()->name()],
            'normalized_data' => null,
            'matched_member_id' => null,
            'match_method' => null,
            'validation_status' => CooperativeImportRowStatus::Pending,
            'validation_errors' => null,
            'review_notes' => null,
            'committed_at' => null,
        ];
    }
}
