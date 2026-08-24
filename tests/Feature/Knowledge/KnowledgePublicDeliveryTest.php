<?php

namespace Tests\Feature\Knowledge;

use App\Enums\PublicationType;
use App\Models\KnowledgeArticle;
use App\Models\Page;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class KnowledgePublicDeliveryTest extends TestCase
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

    public function test_published_article_is_publicly_accessible_with_canonical_metadata(): void
    {
        $article = KnowledgeArticle::factory()->published()->create([
            'title' => 'Pengolahan benih',
            'slug' => 'pengolahan-benih',
            'excerpt' => 'Ringkasan publik.',
            'meta_title' => 'SEO Benih',
            'meta_description' => 'Deskripsi SEO pengetahuan.',
            'content' => '<p>Isi aman.</p>',
        ]);

        $html = $this->get('/teknologi-tani/pengolahan-benih')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Knowledge/Show')
                ->where('article.title', 'Pengolahan benih')
                ->where('article.content', '<p>Isi aman.</p>')
                ->missing('article.created_by')
                ->missing('article.updated_by')
                ->missing('article.status')
                ->missing('article.meta_title'))
            ->getContent();

        $this->assertSame('https://ab2ti.test/teknologi-tani/pengolahan-benih', $this->linkHref($html, 'canonical'));
        $this->assertSame('SEO Benih', $this->metaContent($html, 'property', 'og:title'));
        $this->assertSame('Deskripsi SEO pengetahuan.', $this->metaContent($html, 'property', 'og:description'));
        $this->assertSame('Deskripsi SEO pengetahuan.', $this->metaContent($html, 'name', 'description'));
        $this->assertSame('article', $this->metaContent($html, 'property', 'og:type'));
        $this->assertSame('https://ab2ti.test/teknologi-tani/pengolahan-benih', $this->metaContent($html, 'property', 'og:url'));
        $this->assertSame('index, follow', $this->metaContent($html, 'name', 'robots'));
        $this->assertSame(
            'https://ab2ti.test/images/og/ab2ti-og-default-v1.jpg',
            $this->metaContent($html, 'property', 'og:image'),
        );
        $this->assertStringNotContainsString((string) $article->created_by, $this->metaContent($html, 'property', 'og:title') ?? '');
        $this->assertStringNotContainsString('created_by', $this->metaContent($html, 'property', 'og:description') ?? '');
    }

    public function test_description_falls_back_from_excerpt_then_plaintext_content(): void
    {
        KnowledgeArticle::factory()->published()->create([
            'title' => 'Tanpa SEO',
            'slug' => 'tanpa-seo',
            'excerpt' => 'Ringkasan dipakai.',
            'meta_title' => null,
            'meta_description' => null,
            'content' => '<p>Isi yang tidak dipakai.</p>',
        ]);

        $excerptHtml = $this->get('/teknologi-tani/tanpa-seo')->assertOk()->getContent();
        $this->assertSame('Tanpa SEO', $this->metaContent($excerptHtml, 'property', 'og:title'));
        $this->assertSame('Ringkasan dipakai.', $this->metaContent($excerptHtml, 'name', 'description'));

        KnowledgeArticle::factory()->published()->create([
            'title' => 'Tanpa ringkasan',
            'slug' => 'tanpa-ringkasan',
            'excerpt' => null,
            'meta_title' => null,
            'meta_description' => null,
            'content' => '<p>Paragraf aman untuk deskripsi.</p>',
        ]);

        $bodyHtml = $this->get('/teknologi-tani/tanpa-ringkasan')->assertOk()->getContent();
        $this->assertSame('Paragraf aman untuk deskripsi.', $this->metaContent($bodyHtml, 'name', 'description'));
    }

    public function test_draft_and_unpublished_articles_are_not_publicly_retrievable(): void
    {
        $draft = KnowledgeArticle::factory()->create([
            'slug' => 'masih-draf',
        ]);
        $unpublished = KnowledgeArticle::factory()->published()->create([
            'slug' => 'pernah-terbit',
        ]);
        $unpublished->forceFill(['status' => 'draft'])->save();

        $this->get('/teknologi-tani/masih-draf')->assertNotFound();
        $this->get('/teknologi-tani/'.$draft->id)->assertNotFound();
        $this->get('/teknologi-tani/pernah-terbit')->assertNotFound();
        $this->get('/teknologi-tani')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Knowledge/Index')
                ->where('articles.data', []));
    }

    public function test_og_image_uses_cover_then_dedicated_override(): void
    {
        Storage::fake('public');

        $withCover = KnowledgeArticle::factory()->published()->create([
            'slug' => 'dengan-sampul',
        ]);
        $withCover->addMedia(UploadedFile::fake()->image('cover.jpg', 1400, 800))
            ->toMediaCollection('knowledge-cover');

        $coverHtml = $this->get('/teknologi-tani/dengan-sampul')->assertOk()->getContent();
        $coverImage = $this->metaContent($coverHtml, 'property', 'og:image');
        $this->assertNotNull($coverImage);
        $this->assertStringStartsWith('https://ab2ti.test/', $coverImage);
        $this->assertStringNotContainsString('ab2ti-og-default-v1.jpg', $coverImage);

        $withOverride = KnowledgeArticle::factory()->published()->create([
            'slug' => 'dengan-og',
        ]);
        $withOverride->addMedia(UploadedFile::fake()->image('cover.jpg', 1400, 800))
            ->toMediaCollection('knowledge-cover');
        $withOverride->addMedia(UploadedFile::fake()->image('og.jpg', 1200, 630))
            ->toMediaCollection('social-og');

        $overrideHtml = $this->get('/teknologi-tani/dengan-og')->assertOk()->getContent();
        $overrideImage = $this->metaContent($overrideHtml, 'property', 'og:image');
        $this->assertNotNull($overrideImage);
        $this->assertStringNotContainsString('ab2ti-og-default-v1.jpg', $overrideImage);
        $this->assertNotSame($coverImage, $overrideImage);
    }

    public function test_homepage_lists_only_published_knowledge_articles(): void
    {
        KnowledgeArticle::factory()->create(['title' => 'Draf rahasia', 'slug' => 'draf-rahasia']);
        KnowledgeArticle::factory()->published()->create([
            'title' => 'Sudah terbit',
            'slug' => 'sudah-terbit',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Landing/Welcome')
                ->has('latestKnowledgeArticles', 1)
                ->where('latestKnowledgeArticles.0.title', 'Sudah terbit')
                ->where('latestKnowledgeArticles.0.url', '/teknologi-tani/sudah-terbit')
                ->missing('latestKnowledgeArticles.0.created_by'));
    }

    public function test_index_is_indexable_and_admin_is_noindex(): void
    {
        $index = $this->get('/teknologi-tani')->assertOk()->getContent();
        $this->assertSame('https://ab2ti.test/teknologi-tani', $this->linkHref($index, 'canonical'));
        $this->assertSame('index, follow', $this->metaContent($index, 'name', 'robots'));

        $user = $this->userWithPermissions(['knowledge-articles.view']);
        $article = KnowledgeArticle::factory()->create([
            'title' => 'Judul Internal',
        ]);

        $html = $this->actingAs($user)
            ->get(route('admin.knowledge.articles.edit', $article))
            ->assertOk()
            ->getContent();

        $this->assertSame('noindex, nofollow', $this->metaContent($html, 'name', 'robots'));
        $this->assertNotSame('Judul Internal', $this->metaContent($html, 'property', 'og:title'));
    }

    public function test_knowledge_routes_are_not_captured_by_cms_and_other_families_remain(): void
    {
        Page::query()->create([
            'title' => 'Fake teknologi tani',
            'slug' => 'teknologi-tani',
            'is_active' => true,
        ]);
        Page::query()->create([
            'title' => 'About',
            'slug' => 'about',
            'is_active' => true,
        ]);
        Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'slug' => 'tetap-berita',
            'title' => 'Tetap berita',
        ]);
        KnowledgeArticle::factory()->published()->create([
            'slug' => 'artikel-tani',
            'title' => 'Artikel tani',
        ]);

        $this->get('/teknologi-tani')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Knowledge/Index'));
        $this->get('/teknologi-tani/artikel-tani')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Knowledge/Show'));
        $this->get('/berita/tetap-berita')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Publishing/Show'));
        $this->get('/publikasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Publishing/Index'));
        $this->get('/keanggotaan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Membership/Index'));
        $this->get('/about')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Page/Show')
                ->where('page.slug', 'about'));
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
