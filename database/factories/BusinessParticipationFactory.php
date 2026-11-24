<?php

namespace Database\Factories;

use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessParticipation>
 */
class BusinessParticipationFactory extends Factory
{
    protected $model = BusinessParticipation::class;

    public function definition(): array
    {
        return [
            'cooperative_membership_id' => CooperativeMembership::factory(),
            'cooperative_business_id' => fn () => CooperativeBusiness::psn()->id,
        ];
    }
}
