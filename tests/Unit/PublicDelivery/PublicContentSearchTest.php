<?php

namespace Tests\Unit\PublicDelivery;

use App\Models\Publication;
use App\Support\PublicDelivery\PublicContentSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PublicContentSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_from_request_trims_and_caps_length(): void
    {
        $this->assertSame('', PublicContentSearch::fromRequest(Request::create('/publikasi', 'GET', ['q' => '  '])));
        $this->assertSame('jagung', PublicContentSearch::fromRequest(Request::create('/publikasi', 'GET', ['q' => ' jagung '])));
        $this->assertSame(
            100,
            mb_strlen(PublicContentSearch::fromRequest(Request::create('/publikasi', 'GET', ['q' => str_repeat('a', 140)]))),
        );
    }

    public function test_constrain_matches_title_or_excerpt(): void
    {
        Publication::factory()->published()->create([
            'title' => 'Judul unik xyz',
            'slug' => 'judul-unik',
            'excerpt' => 'Biasa',
        ]);
        Publication::factory()->published()->create([
            'title' => 'Lain',
            'slug' => 'lain',
            'excerpt' => 'Kutipan xyz ada',
        ]);
        Publication::factory()->published()->create([
            'title' => 'Tidak',
            'slug' => 'tidak',
            'excerpt' => 'Kosong',
        ]);

        $ids = PublicContentSearch::constrain(Publication::query(), 'xyz')->pluck('slug')->sort()->values();

        $this->assertSame(['judul-unik', 'lain'], $ids->all());
        $this->assertSame(3, PublicContentSearch::constrain(Publication::query(), '')->count());
    }
}
