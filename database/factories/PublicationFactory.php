<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Enums\PublicationType;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Publication>
 */
class PublicationFactory extends Factory
{
    protected $model = Publication::class;

    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'type' => PublicationType::Berita,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'excerpt' => fake()->optional()->sentence(),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'status' => PublicationStatus::Draft,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function type(PublicationType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
        ]);
    }
}
