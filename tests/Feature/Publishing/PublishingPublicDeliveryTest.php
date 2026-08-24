<?php

namespace Tests\Feature\Publishing;

use App\Enums\PublicationType;
use App\Models\Page;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class PublishingPublicDeliveryTest extends TestCase
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

    public function test_published_publication_is_publicly_accessible_with_canonical_metadata(): void
    {
        $publication = Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'title' => 'Panen benih',
            'slug' => 'panen-benih',
            'excerpt' => 'Ringkasan publik.',
            'meta_title' => 'SEO Panen',
            'meta_description' => 'Deskripsi SEO publikasi.',
            'content' => '<p>Isi aman.</p>',
        ]);

        $html = $this->get('/berita/panen-benih')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Publishing/Show')
                ->where('publication.title', 'Panen benih')
                ->where('publication.content', '<p>Isi aman.</p>')
                ->missing('publication.created_by')
                ->missing('publication.updated_by')
                ->missing('publication.status')
                ->missing('publication.meta_title'))
            ->getContent();

        $this->assertSame('https://ab2ti.test/berita/panen-benih', $this->linkHref($html, 'canonical'));
        $this->assertSame('SEO Panen', $this->metaContent($html, 'property', 'og:title'));
        $this->assertSame('Deskripsi SEO publikasi.', $this->metaContent($html, 'property', 'og:description'));
        $this->assertSame('Deskripsi SEO publikasi.', $this->metaContent($html, 'name', 'description'));
        $this->assertSame('article', $this->metaContent($html, 'property', 'og:type'));
        $this->assertSame('https://ab2ti.test/berita/panen-benih', $this->metaContent($html, 'property', 'og:url'));
        $this->assertSame('index, follow', $this->metaContent($html, 'name', 'robots'));
        $this->assertSame(
            'https://ab2ti.test/images/og/ab2ti-og-default-v1.jpg',
            $this->metaContent($html, 'property', 'og:image'),
        );
        $this->assertStringNotContainsString((string) $publication->created_by, $this->metaContent($html, 'property', 'og:title') ?? '');
        $this->assertStringNotContainsString('created_by', $this->metaContent($html, 'property', 'og:description') ?? '');
    }

    public function test_draft_and_unpublished_publications_are_not_publicly_retrievable(): void
    {
        $draft = Publication::factory()->create([
            'type' => PublicationType::Berita,
            'slug' => 'masih-draf',
        ]);
        $unpublished = Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'slug' => 'pernah-terbit',
        ]);
        $unpublished->forceFill(['status' => 'draft'])->save();

        $this->get('/berita/masih-draf')->assertNotFound();
        $this->get('/berita/'.$draft->id)->assertNotFound();
        $this->get('/berita/pernah-terbit')->assertNotFound();
        $this->get('/publikasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Publishing/Index')
                ->where('publications.data', []));
    }

    public function test_wrong_type_prefix_does_not_resolve_a_published_publication(): void
    {
        Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'slug' => 'hanya-berita',
        ]);

        $this->get('/kegiatan/hanya-berita')->assertNotFound();
        $this->get('/siaran-pers/hanya-berita')->assertNotFound();
    }

    public function test_og_image_uses_cover_then_dedicated_override(): void
    {
        Storage::fake('public');

        $withCover = Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'slug' => 'dengan-sampul',
        ]);
        $withCover->addMedia(UploadedFile::fake()->image('cover.jpg', 1400, 800))
            ->toMediaCollection('publication-cover');

        $coverHtml = $this->get('/berita/dengan-sampul')->assertOk()->getContent();
        $coverImage = $this->metaContent($coverHtml, 'property', 'og:image');
        $this->assertNotNull($coverImage);
        $this->assertStringStartsWith('https://ab2ti.test/', $coverImage);
        $this->assertStringNotContainsString('ab2ti-og-default-v1.jpg', $coverImage);

        $withOverride = Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'slug' => 'dengan-og',
        ]);
        $withOverride->addMedia(UploadedFile::fake()->image('cover.jpg', 1400, 800))
            ->toMediaCollection('publication-cover');
        $withOverride->addMedia(UploadedFile::fake()->image('og.jpg', 1200, 630))
            ->toMediaCollection('social-og');

        $overrideHtml = $this->get('/berita/dengan-og')->assertOk()->getContent();
        $overrideImage = $this->metaContent($overrideHtml, 'property', 'og:image');
        $this->assertNotNull($overrideImage);
        $this->assertStringNotContainsString('ab2ti-og-default-v1.jpg', $overrideImage);
        $this->assertNotSame($coverImage, $overrideImage);
    }

    public function test_homepage_lists_only_published_publications(): void
    {
        Publication::factory()->create(['title' => 'Draf rahasia', 'slug' => 'draf-rahasia']);
        Publication::factory()->published()->create([
            'title' => 'Sudah terbit',
            'slug' => 'sudah-terbit',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Landing/Welcome')
                ->has('latestPublications', 1)
                ->where('latestPublications.0.title', 'Sudah terbit')
                ->where('latestPublications.0.url', '/berita/sudah-terbit')
                ->missing('latestPublications.0.created_by'));
    }

    public function test_type_index_and_hub_are_indexable_public_pages(): void
    {
        Publication::factory()->published()->create([
            'type' => PublicationType::Kegiatan,
            'slug' => 'lokakarya',
            'title' => 'Lokakarya',
        ]);

        $hub = $this->get('/publikasi')->assertOk()->getContent();
        $this->assertSame('https://ab2ti.test/publikasi', $this->linkHref($hub, 'canonical'));
        $this->assertSame('index, follow', $this->metaContent($hub, 'name', 'robots'));

        $type = $this->get('/kegiatan')->assertOk()->getContent();
        $this->assertSame('https://ab2ti.test/kegiatan', $this->linkHref($type, 'canonical'));
        $this->assertSame('index, follow', $this->metaContent($type, 'name', 'robots'));
    }

    public function test_admin_publishing_pages_are_noindex(): void
    {
        $user = $this->userWithPermissions(['publishing-publications.view']);
        $publication = Publication::factory()->create([
            'title' => 'Judul Internal',
        ]);

        $html = $this->actingAs($user)
            ->get(route('admin.publishing.publications.edit', $publication))
            ->assertOk()
            ->getContent();

        $this->assertSame('noindex, nofollow', $this->metaContent($html, 'name', 'robots'));
        $this->assertNotSame('Judul Internal', $this->metaContent($html, 'property', 'og:title'));
    }

    public function test_existing_cms_and_membership_routes_remain_owned_by_their_contexts(): void
    {
        Page::query()->create([
            'title' => 'About',
            'slug' => 'about',
            'is_active' => true,
        ]);

        $this->get('/about')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Page/Show')
                ->where('page.slug', 'about'));

        $this->get('/keanggotaan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Membership/Index'));
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
