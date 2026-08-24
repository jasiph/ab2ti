<?php

namespace Database\Factories;

use App\Enums\MembershipApplicationStatus;
use App\Models\MembershipApplication;
use App\Support\Membership\MembershipReferenceCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipApplication>
 */
class MembershipApplicationFactory extends Factory
{
    protected $model = MembershipApplication::class;

    public function definition(): array
    {
        return [
            'reference_code' => MembershipReferenceCode::generate(),
            'legal_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->address(),
            'applicant_notes' => fake()->optional()->sentence(),
            'status' => MembershipApplicationStatus::Submitted,
            'submitted_at' => now(),
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn (): array => [
            'status' => MembershipApplicationStatus::UnderReview,
            'review_started_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => MembershipApplicationStatus::Approved,
            'review_started_at' => now()->subHour(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => MembershipApplicationStatus::Rejected,
            'review_started_at' => now()->subHour(),
            'rejected_at' => now(),
            'rejection_reason' => 'Tidak memenuhi persyaratan yang diketahui.',
        ]);
    }

    public function revisionRequested(): static
    {
        return $this->state(fn (): array => [
            'status' => MembershipApplicationStatus::RevisionRequested,
            'review_started_at' => now()->subHour(),
            'revision_requested_at' => now(),
            'revision_reason' => 'Lengkapi dokumen yang diminta.',
        ]);
    }
}
