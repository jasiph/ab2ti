<?php

namespace Tests\Feature\PublicDelivery;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class PublicMetadataHttpTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://ab2ti.test',
            'app.name' => 'AB2TI 2.0',
            'public_delivery.canonical_origin' => 'https://ab2ti.test',
            'public_delivery.site_name' => 'AB2TI 2.0',
            'public_delivery.default_title' => 'AB2TI 2.0',
            'public_delivery.default_description' => 'Asosiasi Bank Benih dan Teknologi Tani Indonesia (AB2TI).',
            'public_delivery.default_og_image_path' => '/images/og/ab2ti-og-default-v1.jpg',
        ]);
    }

    public function test_welcome_page_html_contains_crawler_visible_metadata(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame('https://ab2ti.test/', $this->linkHref($html, 'canonical'));
        $this->assertNotSame('', $this->metaContent($html, 'name', 'description'));
        $this->assertSame('website', $this->metaContent($html, 'property', 'og:type'));
        $this->assertNotSame('', $this->metaContent($html, 'property', 'og:title'));
        $this->assertNotSame('', $this->metaContent($html, 'property', 'og:description'));
        $this->assertSame('https://ab2ti.test/', $this->metaContent($html, 'property', 'og:url'));
        $this->assertSame(
            'https://ab2ti.test/images/og/ab2ti-og-default-v1.jpg',
            $this->metaContent($html, 'property', 'og:image'),
        );
        $this->assertSame('summary_large_image', $this->metaContent($html, 'name', 'twitter:card'));
        $this->assertSame('index, follow', $this->metaContent($html, 'name', 'robots'));
    }

    public function test_cms_page_uses_editorial_overrides_and_absolute_canonical(): void
    {
        Page::query()->create([
            'title' => 'Tentang Kami',
            'slug' => 'tentang',
            'content' => '<p>Should not appear raw</p>',
            'meta_title' => 'SEO Tentang',
            'meta_description' => 'Deskripsi editorial.',
            'is_active' => true,
        ]);

        $html = $this->get('/tentang')->assertOk()->getContent();

        $this->assertSame('https://ab2ti.test/tentang', $this->linkHref($html, 'canonical'));
        $this->assertSame('SEO Tentang', $this->metaContent($html, 'property', 'og:title'));
        $this->assertSame('Deskripsi editorial.', $this->metaContent($html, 'property', 'og:description'));
        $this->assertSame('Deskripsi editorial.', $this->metaContent($html, 'name', 'description'));
        $this->assertStringNotContainsString('Should not appear raw', $this->metaContent($html, 'property', 'og:description'));
    }

    public function test_canonical_ignores_incoming_host_header(): void
    {
        Page::query()->create([
            'title' => 'Profil',
            'slug' => 'profil',
            'is_active' => true,
        ]);

        $html = $this->get('/profil', ['Host' => 'evil.example'])->assertOk()->getContent();

        $this->assertSame('https://ab2ti.test/profil', $this->linkHref($html, 'canonical'));
        $this->assertSame('https://ab2ti.test/profil', $this->metaContent($html, 'property', 'og:url'));
        $this->assertStringNotContainsString('evil.example', $this->linkHref($html, 'canonical'));
    }

    public function test_featured_cover_image_is_used_before_site_default(): void
    {
        Storage::fake('public');

        $page = Page::query()->create([
            'title' => 'Berita',
            'slug' => 'berita-cover',
            'is_active' => true,
        ]);

        $page->addMedia(UploadedFile::fake()->image('cover.jpg', 1400, 800))
            ->toMediaCollection('page-cover');

        $html = $this->get('/berita-cover')->assertOk()->getContent();
        $image = $this->metaContent($html, 'property', 'og:image');

        $this->assertNotNull($image);
        $this->assertStringStartsWith('https://ab2ti.test/', $image);
        $this->assertStringNotContainsString('ab2ti-og-default-v1.jpg', $image);
    }

    public function test_explicit_social_og_media_wins_over_page_cover(): void
    {
        Storage::fake('public');

        $page = Page::query()->create([
            'title' => 'Berita',
            'slug' => 'berita-og',
            'is_active' => true,
        ]);

        $page->addMedia(UploadedFile::fake()->image('cover.jpg', 1400, 800))
            ->toMediaCollection('page-cover');
        $page->addMedia(UploadedFile::fake()->image('og.jpg', 1200, 630))
            ->toMediaCollection('social-og');

        $page->refresh();

        $html = $this->get('/berita-og')->assertOk()->getContent();
        $image = $this->metaContent($html, 'property', 'og:image');
        $explicitPath = parse_url($page->getFirstMediaUrl('social-og', 'social-og'), PHP_URL_PATH);
        $coverPath = parse_url($page->getFirstMediaUrl('page-cover', 'social-og'), PHP_URL_PATH);

        $this->assertNotNull($image);
        $this->assertNotNull($explicitPath);
        $this->assertSame('https://ab2ti.test'.$explicitPath, $image);
        $this->assertNotSame('https://ab2ti.test'.$coverPath, $image);
    }

    public function test_inactive_cms_page_is_not_publicly_shareable(): void
    {
        Page::query()->create([
            'title' => 'Draft',
            'slug' => 'draft-rahasia',
            'meta_title' => 'Secret draft',
            'is_active' => false,
        ]);

        $this->get('/draft-rahasia')->assertNotFound();
    }

    public function test_admin_surface_is_noindex_and_does_not_use_cms_copy(): void
    {
        $user = $this->userWithPermissions(['cms.view']);

        $html = $this->actingAs($user)->get('/admin/pages')->assertOk()->getContent();

        $this->assertSame('noindex, nofollow', $this->metaContent($html, 'name', 'robots'));
        $this->assertSame(
            'https://ab2ti.test/images/og/ab2ti-og-default-v1.jpg',
            $this->metaContent($html, 'property', 'og:image'),
        );
        $this->assertSame('AB2TI 2.0', $this->metaContent($html, 'property', 'og:title'));
    }

    public function test_default_og_asset_exists_at_configured_size(): void
    {
        $path = public_path('images/og/ab2ti-og-default-v1.jpg');

        $this->assertFileExists($path);

        $info = getimagesize($path);

        $this->assertSame(1200, $info[0]);
        $this->assertSame(630, $info[1]);
        $this->assertSame('image/jpeg', $info['mime']);
    }

    private function metaContent(string $html, string $attribute, string $name): ?string
    {
        $pattern = '/<meta '.$attribute.'="'.preg_quote($name, '/').'" content="([^"]*)"/';

        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function linkHref(string $html, string $rel): ?string
    {
        $pattern = '/<link rel="'.preg_quote($rel, '/').'" href="([^"]*)"/';

        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
