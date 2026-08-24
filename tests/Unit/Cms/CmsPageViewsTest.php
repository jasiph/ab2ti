<?php

namespace Tests\Unit\Cms;

use App\Models\Page;
use App\Support\Cms\CmsPageViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPageViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_detail_keeps_safe_related_links_and_drops_unsafe_urls(): void
    {
        $page = Page::query()->create([
            'title' => 'Profil',
            'slug' => 'profil-tautan',
            'is_active' => true,
            'content' => '<p>Isi</p>',
            'settings' => [
                'related_links' => [
                    ['label' => 'Publikasi', 'url' => '/publikasi'],
                    ['label' => 'Eksternal', 'url' => 'https://example.com/info'],
                    ['label' => 'Skrip', 'url' => 'javascript:alert(1)'],
                    ['label' => 'Protokol relatif', 'url' => '//evil.example/phish'],
                    ['label' => '', 'url' => '/keanggotaan'],
                ],
            ],
        ]);

        $view = CmsPageViews::publicDetail($page);

        $this->assertSame('Profil', $view['title']);
        $this->assertSame('profil-tautan', $view['slug']);
        $this->assertSame([
            ['label' => 'Publikasi', 'url' => '/publikasi'],
            ['label' => 'Eksternal', 'url' => 'https://example.com/info'],
        ], $view['related_links']);
        $this->assertArrayNotHasKey('media', $view);
        $this->assertArrayNotHasKey('is_active', $view);
        $this->assertArrayNotHasKey('settings', $view);
    }
}
