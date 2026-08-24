<?php

namespace Database\Factories;

use App\Enums\KnowledgeArticleStatus;
use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeArticle>
 */
class KnowledgeArticleFactory extends Factory
{
    protected $model = KnowledgeArticle::class;

    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'excerpt' => fake()->optional()->sentence(),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'status' => KnowledgeArticleStatus::Draft,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => KnowledgeArticleStatus::Published,
            'published_at' => now(),
        ]);
    }
}
