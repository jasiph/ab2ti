<?php

namespace Database\Factories;

use App\Enums\LapakListingAvailability;
use App\Enums\LapakModerationStatus;
use App\Enums\LapakPriceMode;
use App\Models\Lapak;
use App\Models\LapakCategory;
use App\Models\LapakListing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LapakListing>
 */
class LapakListingFactory extends Factory
{
    protected $model = LapakListing::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'lapak_id' => Lapak::factory(),
            'category_id' => LapakCategory::factory(),
            'title' => Str::title($title),
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'price_mode' => LapakPriceMode::Fixed,
            'price_amount' => fake()->randomFloat(0, 10000, 250000),
            'price_unit' => 'kg',
            'availability' => LapakListingAvailability::Available,
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

    public function unavailable(): static
    {
        return $this->state(fn () => [
            'availability' => LapakListingAvailability::Unavailable,
        ]);
    }

    public function negotiable(): static
    {
        return $this->state(fn () => [
            'price_mode' => LapakPriceMode::Negotiable,
            'price_amount' => null,
            'price_unit' => null,
        ]);
    }

    public function publiclyVisible(): static
    {
        return $this->published()->state(fn () => [
            'lapak_id' => Lapak::factory()->published(),
            'availability' => LapakListingAvailability::Available,
            'category_id' => LapakCategory::factory()->state(['is_active' => true]),
        ]);
    }
}
