<?php

namespace Database\Factories;

use App\Enums\CooperativeImportBatchStatus;
use App\Enums\CooperativeImportMode;
use App\Models\CooperativeImportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CooperativeImportBatch>
 */
class CooperativeImportBatchFactory extends Factory
{
    protected $model = CooperativeImportBatch::class;

    public function definition(): array
    {
        return [
            'original_filename' => 'legacy-'.fake()->numerify('####').'.csv',
            'stored_path' => 'cooperative-imports/'.Str::uuid().'.csv',
            'file_hash' => hash('sha256', Str::uuid()->toString()),
            'worksheet_name' => 'CSV',
            'worksheet_visible' => true,
            'header_row_number' => 1,
            'status' => CooperativeImportBatchStatus::Uploaded,
            'import_mode' => CooperativeImportMode::Append,
            'created_by' => User::factory(),
            'started_at' => now(),
            'completed_at' => null,
            'summary_json' => null,
        ];
    }
}
