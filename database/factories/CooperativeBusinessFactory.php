<?php

namespace Database\Factories;

use App\Enums\CooperativeBusinessStatus;
use App\Models\CooperativeBusiness;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CooperativeBusiness>
 */
class CooperativeBusinessFactory extends Factory
{
    protected $model = CooperativeBusiness::class;

    public function definition(): array
    {
        return [
            'code' => 'TST'.fake()->unique()->numerify('###'),
            'name' => fake()->words(2, true),
            'status' => CooperativeBusinessStatus::Active,
            'participation_unit_name' => 'Saham uji',
            'current_unit_value' => 1_000_000,
        ];
    }
}
