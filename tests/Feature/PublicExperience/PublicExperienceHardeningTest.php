<?php

namespace Tests\Feature\PublicExperience;

use App\Enums\PublicationType;
use App\Models\Hero;
use App\Models\KnowledgeArticle;
use App\Models\Lapak;
use App\Models\LapakListing;
use App\Models\Page;
use App\Models\Publication;
use App\Models\WelcomeSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicExperienceHardeningTest extends TestCase
{
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

    public function test_robots_txt_disallows_private_surfaces_and_points_to_sitemap(): void
    {
        $body = $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('User-agent: *', $body);
        $this->assertStringContainsString('Sitemap: https://ab2ti.test/sitemap.xml', $body);

        foreach ([
            '/admin',
            '/akun',
            '/akses',
            '/dashboard',
            '/login',
            '/register',
            '/user',
            '/language',
            '/keanggotaan/daftar',
        ] as $path) {
            $this->assertStringContainsString('Disallow: '.$path, $body);
        }

        $this->assertStringNotContainsString('Disallow: /publikasi', $body);
        $this->assertStringNotContainsString('Disallow: /lapak', $body);
        $this->assertStringNotContainsString("Disallow: /keanggotaan\n", $body."\n");
    }

    public function test_sitemap_includes_eligible_public_urls_and_excludes_private_ones(): void
    {
        $publishedPublication = Publication::factory()->published()->create([
            'type' => PublicationType::Berita,
            'title' => 'Panen terbit',
            'slug' => 'panen-terbit',
        ]);
        Publication::factory()->create([
            'title' => 'Draf publikasi',
            'slug' => 'draf-publikasi',
        ]);

        $publishedArticle = KnowledgeArticle::factory()->published()->create([
            'title' => 'Artikel terbit',
            'slug' => 'artikel-terbit',
        ]);
        KnowledgeArticle::factory()->create([
            'title' => 'Draf pengetahuan',
            'slug' => 'draf-pengetahuan',
        ]);

        $listing = LapakListing::factory()->publiclyVisible()->create([
            'title' => 'Benih tayang',
            'slug' => 'benih-tayang',
        ]);
        $hiddenLapak = Lapak::factory()->create([
            'name' => 'Lapak draf',
            'slug' => 'lapak-draf',
        ]);
        LapakListing::factory()->published()->create([
            'lapak_id' => $hiddenLapak->id,
            'title' => 'Produk tersembunyi',
            'slug' => 'produk-tersembunyi',
        ]);

        Page::query()->create([
            'title' => 'Tentang',
            'slug' => 'about',
            'content' => '<p>Profil.</p>',
            'is_active' => true,
        ]);
        Page::query()->create([
            'title' => 'Draf CMS',
            'slug' => 'draft-rahasia',
            'content' => '<p>Rahasia.</p>',
            'is_active' => false,
        ]);

        $xml = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('<loc>https://ab2ti.test/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://ab2ti.test/publikasi</loc>', $xml);
        $this->assertStringContainsString('<loc>https://ab2ti.test/berita</loc>', $xml);
        $this->assertStringContainsString('<loc>https://ab2ti.test/teknologi-tani</loc>', $xml);
        $this->assertStringContainsString('<loc>https://ab2ti.test/lapak</loc>', $xml);
        $this->assertStringContainsString('<loc>https://ab2ti.test/keanggotaan</loc>', $xml);
        $this->assertStringContainsString('<loc>https://ab2ti.test/about</loc>', $xml);
        $this->assertStringContainsString('<loc>https://ab2ti.test/'.$publishedPublication->publicPath().'</loc>', $xml);
        $this->assertStringContainsString('<loc>https://ab2ti.test/'.$publishedArticle->publicPath().'</loc>', $xml);
        $this->assertStringContainsString('<loc>https://ab2ti.test/lapak/'.$listing->lapak->slug.'</loc>', $xml);
        $this->assertStringContainsString(
            '<loc>https://ab2ti.test/lapak/'.$listing->lapak->slug.'/produk/benih-tayang</loc>',
            $xml,
        );

        $this->assertStringNotContainsString('draf-publikasi', $xml);
        $this->assertStringNotContainsString('draf-pengetahuan', $xml);
        $this->assertStringNotContainsString('draft-rahasia', $xml);
        $this->assertStringNotContainsString('lapak-draf', $xml);
        $this->assertStringNotContainsString('produk-tersembunyi', $xml);
        $this->assertStringNotContainsString('/admin', $xml);
        $this->assertStringNotContainsString('/akun', $xml);
        $this->assertStringNotContainsString('/akses', $xml);
        $this->assertStringNotContainsString('/login', $xml);
        $this->assertStringNotContainsString('/keanggotaan/daftar', $xml);
    }

    public function test_english_locale_uses_agricultural_technology_not_indonesian_label(): void
    {
        $html = $this->withSession(['locale' => 'en'])
            ->get('/teknologi-tani')
            ->assertOk()
            ->getContent();

        $this->assertSame('Agricultural Technology', $this->metaContent($html, 'property', 'og:title'));
        $this->assertStringNotContainsString(
            'Teknologi Tani',
            (string) $this->metaContent($html, 'property', 'og:title'),
        );
    }

    public function test_indonesian_locale_keeps_teknologi_tani_label(): void
    {
        $html = $this->withSession(['locale' => 'id'])
            ->get('/teknologi-tani')
            ->assertOk()
            ->getContent();

        $this->assertSame('Teknologi Tani', $this->metaContent($html, 'property', 'og:title'));
    }

    public function test_unsafe_hero_and_welcome_hrefs_are_not_passed_to_the_homepage(): void
    {
        Hero::query()->create([
            'title' => 'Hero',
            'primary_text' => 'Utama',
            'secondary_text' => 'Pendukung',
            'button_text' => 'Gabung',
            'button_url' => 'javascript:alert(1)',
            'is_active' => true,
        ]);
        WelcomeSection::query()->create([
            'title' => 'Sambutan',
            'content' => '<p>Isi.</p>',
            'cta_label' => 'Lanjut',
            'cta_url' => 'data:text/html,phish',
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Landing/Welcome')
                ->where('hero.button_url', null)
                ->where('welcomeSection.cta_url', null));
    }

    public function test_public_navbar_exposes_open_and_close_menu_labels(): void
    {
        $navbar = (string) file_get_contents(resource_path('js/Layouts/Partials/FrontendNavbar.vue'));
        $switcher = (string) file_get_contents(resource_path('js/Components/LanguageSwitcher.vue'));

        $this->assertStringContainsString('$t("Close sidebar")', $navbar);
        $this->assertStringContainsString('$t("Open main menu")', $navbar);
        $this->assertStringContainsString('$t("Change to")', $switcher);
        $this->assertStringNotContainsString('Change to:', $switcher);
        $this->assertStringNotContainsString('Ganti ke:', $switcher);
        $this->assertStringNotContainsString('console.log', $switcher);
    }

    private function metaContent(string $html, string $attribute, string $name): ?string
    {
        $pattern = '/<meta '.$attribute.'="'.preg_quote($name, '/').'" content="([^"]*)"/';

        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
