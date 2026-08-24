<?php

namespace Tests\Feature\Cms;

use App\Enums\PublicationType;
use App\Models\KnowledgeArticle;
use App\Models\Page;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyPresbiCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_sermon_and_event_aliases_are_not_registered(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('sermons'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('events'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.sermons.index'));

        $permissions = collect(config('acl.permissions'))->flatten();
        $this->assertFalse($permissions->contains(fn ($name) => str_contains((string) $name, 'sermon')));
        $this->assertFalse($permissions->contains(fn ($name) => str_contains((string) $name, 'khotbah')));
    }

    public function test_homepage_does_not_expose_church_leftover_urls_or_sections(): void
    {
        $html = $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Landing/Welcome'))
            ->getContent();

        $this->assertStringNotContainsString('/sermons', $html);
        $this->assertStringNotContainsString('href="/events"', $html);
        $this->assertStringNotContainsString('ServiceEvents', $html);
        $this->assertStringNotContainsString('Khotbah', $html);
    }

    public function test_ab2ti_public_families_remain_after_presbi_cleanup(): void
    {
        Page::query()->create([
            'title' => 'About',
            'slug' => 'about',
            'is_active' => true,
        ]);
        Page::query()->create([
            'title' => 'Contact',
            'slug' => 'contact',
            'is_active' => true,
        ]);
        Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'slug' => 'tetap-berita',
            'title' => 'Tetap berita',
        ]);
        KnowledgeArticle::factory()->published()->create([
            'slug' => 'tetap-tani',
            'title' => 'Tetap tani',
        ]);

        $this->get('/about')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Page/Show'));
        $this->get('/publikasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Publishing/Index'));
        $this->get('/berita/tetap-berita')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Publishing/Show'));
        $this->get('/teknologi-tani/tetap-tani')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Knowledge/Show'));
        $this->get('/keanggotaan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Membership/Index'));
        $this->get('/contact')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Page/Show'));
    }
}
