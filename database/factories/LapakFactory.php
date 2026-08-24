<?php

namespace Database\Factories;

use App\Enums\LapakModerationStatus;
use App\Models\Lapak;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lapak>
 */
class LapakFactory extends Factory
{
    protected $model = Lapak::class;

    public function definition(): array
    {
        $name = 'Lapak '.fake()->unique()->company();

        return [
            'member_id' => Member::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'location_text' => fake()->city(),
            'contact_phone' => fake()->numerify('08##########'),
            'contact_whatsapp' => fake()->numerify('08##########'),
            'contact_email' => fake()->safeEmail(),
            'moderation_status' => LapakModerationStatus::Draft,
            'moderation_note' => null,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'moderation_status' => LapakModerationStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn () => [
            'moderation_status' => LapakModerationStatus::PendingReview,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'moderation_status' => LapakModerationStatus::Rejected,
            'moderation_note' => 'Tidak lengkap',
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'moderation_status' => LapakModerationStatus::Suspended,
            'moderation_note' => 'Ditangguhkan staf',
            'published_at' => now()->subDay(),
        ]);
    }
}
