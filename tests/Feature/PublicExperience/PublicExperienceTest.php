<?php

namespace Tests\Feature\PublicExperience;

use App\Models\KnowledgeArticle;
use App\Models\Page;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_public_destinations_are_owned_and_not_cms_catch_all(): void
    {
        $this->assertTrue(Route::has('welcome'));
        $this->assertTrue(Route::has('about'));
        $this->assertTrue(Route::has('contact'));
        $this->assertTrue(Route::has('publications.index'));
        $this->assertTrue(Route::has('knowledge.index'));
        $this->assertTrue(Route::has('membership.index'));
        $this->assertTrue(Route::has('lapak.index'));
        $this->assertTrue(Route::has('robots'));
        $this->assertTrue(Route::has('sitemap'));
        $this->assertFalse(Route::has('sermons'));
        $this->assertFalse(Route::has('events'));
        $this->assertFalse(Route::has('kontak'));

        $this->get('/sermons')->assertNotFound();
        $this->get('/events')->assertNotFound();
        $this->get('/kontak')->assertNotFound();
        $this->get('/keanggotaan/tidak-ada')->assertNotFound();
        $this->get('/teknologi-tani/tidak-ada')->assertNotFound();
        $this->get('/lapak/tidak-ada')->assertNotFound();
    }

    public function test_homepage_uses_domain_data_and_drops_demo_framework_props(): void
    {
        Publication::factory()->create(['title' => 'Draf publikasi', 'slug' => 'draf-publikasi']);
        Publication::factory()->published()->create([
            'title' => 'Publikasi terbit',
            'slug' => 'publikasi-terbit',
        ]);
        KnowledgeArticle::factory()->create(['title' => 'Draf pengetahuan', 'slug' => 'draf-pengetahuan']);
        KnowledgeArticle::factory()->published()->create([
            'title' => 'Artikel terbit',
            'slug' => 'artikel-terbit',
        ]);

        $html = $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Landing/Welcome')
                ->missing('laravelVersion')
                ->missing('phpVersion')
                ->missing('canLogin')
                ->has('membershipCta.url')
                ->where('membershipCta.url', route('membership.index'))
                ->where('membershipCta.learn_url', route('membership.index'))
                ->where('membershipCta.apply_url', route('membership.apply'))
                ->has('latestPublications', 1)
                ->where('latestPublications.0.title', 'Publikasi terbit')
                ->missing('latestPublications.0.created_by')
                ->has('latestKnowledgeArticles', 1)
                ->where('latestKnowledgeArticles.0.title', 'Artikel terbit')
                ->missing('latestKnowledgeArticles.0.created_by')
                ->has('latestLapakListings', 0))
            ->getContent();

        $this->assertStringNotContainsString('unsplash.com', $html);
        $this->assertStringNotContainsString('/sermons', $html);
        $this->assertStringNotContainsString('ServiceEvents', $html);
        $this->assertStringNotContainsString('laravelVersion', $html);
        $this->assertStringNotContainsString('facebook.com/ab2ti', $html);
        $this->assertStringNotContainsString('Member Portal', $html);
    }

    public function test_homepage_is_public_and_uses_shared_organization_and_seo(): void
    {
        $this->assertGuest();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Landing/Welcome')
                ->where('membershipCta.url', route('membership.index'))
                ->where('membershipCta.learn_url', route('membership.index'))
                ->where('membershipCta.apply_url', route('membership.apply'))
                ->where('organization.short_name', 'AB2TI')
                ->where('organization.contact.email', 'ab2ti.pusat@gmail.com')
                ->missing('organization.social')
                ->where('seo.og_type', 'website')
                ->where('seo.twitter_card', 'summary_large_image')
                ->where('seo.indexable', true)
                ->has('seo.canonical_url')
                ->has('seo.og_image')
                ->has('seo.og_title')
                ->has('seo.description')
                ->where('seo.title', 'Asosiasi Bank Benih dan Teknologi Tani Indonesia'));

        $this->assertNotSame(route('membership.apply'), route('membership.index'));
        $this->assertSame(route('membership.index'), url('/keanggotaan'));
        $this->assertArrayNotHasKey('facebook', config('organization.contact'));
        $this->assertNull(config('organization.social'));
        $this->assertTrue(Route::has('lapak.index'));
        $this->assertTrue(Route::has('lapak.show'));
        $this->assertTrue(Route::has('lapak.listings.show'));
        $this->assertFalse(Route::has('lapak.storefronts.index'));
        $this->get('/lapak')->assertOk();
    }

    public function test_empty_homepage_content_does_not_error(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Landing/Welcome')
                ->where('hero', null)
                ->where('welcomeSection', null)
                ->has('latestPublications', 0)
                ->has('latestKnowledgeArticles', 0)
                ->has('latestLapakListings', 0)
                ->has('membershipCta.url')
                ->has('membershipCta.learn_url')
                ->has('membershipCta.apply_url'));
    }

    public function test_membership_public_entry_remains_canonical_after_public_shell(): void
    {
        $this->get('/keanggotaan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Membership/Index'));

        $this->get('/keanggotaan/daftar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Membership/Apply'));
    }

    public function test_public_content_routes_do_not_require_auth_or_expose_admin_pages(): void
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

        $this->get('/')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/publikasi')->assertOk();
        $this->get('/teknologi-tani')->assertOk();
        $this->get('/lapak')->assertOk();
        $this->get('/keanggotaan')->assertOk();
        $this->get('/contact')->assertOk();

        $this->get('/admin/dashboard')->assertRedirect();
        $this->get('/admin/publishing/publications')->assertRedirect();
        $this->get('/admin/knowledge/articles')->assertRedirect();
    }

    public function test_active_cms_page_is_shaped_and_inactive_page_is_hidden(): void
    {
        Storage::fake('public');

        $active = Page::query()->create([
            'title' => 'Tentang',
            'slug' => 'tentang-publik',
            'content' => '<h2>Profil</h2><p>Teks sah.</p><script>alert(1)</script>',
            'is_active' => true,
            'settings' => [
                'related_links' => [
                    ['label' => 'Keanggotaan', 'url' => '/keanggotaan'],
                    ['label' => 'Bahaya', 'url' => 'javascript:alert(1)'],
                ],
            ],
        ]);
        $active->addMedia(UploadedFile::fake()->image('cover.jpg'))
            ->toMediaCollection('page-cover');

        Page::query()->create([
            'title' => 'Rahasia',
            'slug' => 'halaman-rahasia',
            'is_active' => false,
            'content' => '<p>Jangan tampil</p>',
        ]);

        $this->get('/tentang-publik')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Page/Show')
                ->where('page.slug', 'tentang-publik')
                ->where('page.title', 'Tentang')
                ->where('page.content', fn ($content) => is_string($content)
                    && str_contains($content, 'Profil')
                    && str_contains($content, 'Teks sah.')
                    && ! str_contains($content, '<script'))
                ->where('page.related_links', [
                    ['label' => 'Keanggotaan', 'url' => '/keanggotaan'],
                ])
                ->has('page.cover_url')
                ->missing('page.media')
                ->missing('page.is_active')
                ->missing('page.settings')
                ->missing('page.object_key'));

        $this->get('/halaman-rahasia')->assertNotFound();
    }

    public function test_known_template_block_remains_on_public_cms_page(): void
    {
        Page::query()->create([
            'title' => 'Dengan blok',
            'slug' => 'halaman-blok',
            'is_active' => true,
            'content' => '<div class="tiptap-block-wrapper" data-block-type="Two Columns Feature"><div class="tiptap-block-content"><h4>Visi Kami</h4><p>Teks.</p></div></div>',
        ]);

        $this->get('/halaman-blok')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('page.content', fn ($content) => is_string($content)
                    && str_contains($content, 'data-block-type="Two Columns Feature"')
                    && str_contains($content, 'Visi Kami')));
    }
}
