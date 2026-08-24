<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Support\Membership\MembershipNumberGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'membership_number' => MembershipNumberGenerator::generate(),
            'source_application_id' => MembershipApplication::factory()->approved(),
            'status' => MemberStatus::Active,
            'legal_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->address(),
            'recognized_at' => now(),
            'card_issued' => false,
            'user_id' => null,
            'linked_at' => null,
        ];
    }

    public function linked(?User $user = null): static
    {
        return $this->state(function () use ($user) {
            $account = $user ?? User::factory()->create();

            return [
                'user_id' => $account->id,
                'linked_at' => now(),
            ];
        });
    }
}
