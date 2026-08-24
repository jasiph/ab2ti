<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class ApplicationErrorPageTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://ab2ti.test',
            'public_delivery.canonical_origin' => 'https://ab2ti.test',
            'public_delivery.site_name' => 'AB2TI 2.0',
        ]);
    }

    public function test_production_like_404_is_branded_and_not_indexable(): void
    {
        $html = $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page
                ->component('Errors/Show')
                ->where('status', 404)
                ->where('title', 'Halaman tidak ditemukan')
                ->missing('exception')
                ->missing('trace'))
            ->getContent();

        $this->assertSame('noindex, nofollow', $this->metaContent($html, 'name', 'robots'));
        $this->assertStringNotContainsString('NotFoundHttpException', $html);
        $this->assertStringNotContainsString('storage/app', $html);
    }

    public function test_403_is_branded_and_does_not_reveal_permissions(): void
    {
        $this->syncAclFromConfig();
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page
                ->component('Errors/Show')
                ->where('status', 403)
                ->where('title', 'Akses tidak diizinkan'))
            ->getContent();

        $this->assertStringNotContainsString('users.view', $html);
        $this->assertStringNotContainsString('Necessary permissions', $html);
        $this->assertStringNotContainsString('User does not have the right permissions', $html);
        $this->assertStringNotContainsString('Spatie\\Permission', $html);
        $this->assertSame('noindex, nofollow', $this->metaContent($html, 'name', 'robots'));
    }

    public function test_419_is_branded_session_expiry_not_a_crash(): void
    {
        $html = $this->get('/__phpunit/expired')
            ->assertStatus(419)
            ->assertInertia(fn ($page) => $page
                ->component('Errors/Show')
                ->where('status', 419)
                ->where('title', 'Sesi telah berakhir'))
            ->getContent();

        $this->assertStringNotContainsString('TokenMismatchException', $html);
        $this->assertStringNotContainsString('CSRF', $html);
        $this->assertSame('noindex, nofollow', $this->metaContent($html, 'name', 'robots'));
    }

    public function test_production_500_is_branded_and_hides_exception_detail(): void
    {
        config(['app.debug' => false]);

        $html = $this->get('/__phpunit/server-error')
            ->assertStatus(500)
            ->assertInertia(fn ($page) => $page
                ->component('Errors/Show')
                ->where('status', 500)
                ->where('title', 'Terjadi gangguan'))
            ->getContent();

        $this->assertStringNotContainsString('internal-secret-should-not-leak', $html);
        $this->assertStringNotContainsString('RuntimeException', $html);
        $this->assertStringNotContainsString('app/Support', $html);
        $this->assertSame('noindex, nofollow', $this->metaContent($html, 'name', 'robots'));
    }

    public function test_debug_500_still_exposes_diagnostics(): void
    {
        config(['app.debug' => true]);

        $html = $this->get('/__phpunit/server-error')
            ->assertStatus(500)
            ->getContent();

        $this->assertStringContainsString('internal-secret-should-not-leak', $html);
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
