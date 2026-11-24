<?php

namespace Database\Factories;

use App\Enums\CooperativeMembershipStatus;
use App\Models\CooperativeMembership;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CooperativeMembership>
 */
class CooperativeMembershipFactory extends Factory
{
    protected $model = CooperativeMembership::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'status' => CooperativeMembershipStatus::Active,
            'joined_at' => now(),
            'ended_at' => null,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => CooperativeMembershipStatus::Inactive,
            'ended_at' => now(),
        ]);
    }
}
