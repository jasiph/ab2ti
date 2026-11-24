<?php

namespace Tests\Feature\Cms;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class CmsRoutingTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_explicit_login_route_beats_a_cms_page_with_the_same_slug(): void
    {
        Page::query()->create([
            'title' => 'Fake Login',
            'slug' => 'login',
            'is_active' => true,
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_normal_cms_slug_resolves(): void
    {
        Page::query()->create([
            'title' => 'About',
            'slug' => 'about-ab2ti',
            'content' => '<p>Hello</p>',
            'is_active' => true,
        ]);

        $this->get('/about-ab2ti')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Page/Show')
                ->where('page.slug', 'about-ab2ti'));
    }

    public function test_nested_cms_slug_resolves(): void
    {
        Page::query()->create([
            'title' => 'Team',
            'slug' => 'about/team',
            'is_active' => true,
        ]);

        $this->get('/about/team')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Page/Show')
                ->where('page.slug', 'about/team'));
    }

    public function test_unknown_cms_path_returns_not_found(): void
    {
        $this->get('/no-such-foundation-page')->assertNotFound();
    }

    public function test_register_is_not_served_as_cms_even_if_a_page_row_exists(): void
    {
        Page::query()->create([
            'title' => 'Register',
            'slug' => 'register',
            'is_active' => true,
        ]);

        $this->get('/register')->assertNotFound();
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('register'));
    }

    public function test_retired_presbi_aliases_are_not_content_routes(): void
    {
        Page::query()->create([
            'title' => 'Sermons leftover',
            'slug' => 'sermons',
            'is_active' => true,
        ]);
        Page::query()->create([
            'title' => 'Events leftover',
            'slug' => 'events',
            'is_active' => true,
        ]);

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('sermons'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('events'));
        $this->get('/sermons')->assertNotFound();
        $this->get('/events')->assertNotFound();
    }

    public function test_inactive_cms_page_is_not_found(): void
    {
        Page::query()->create([
            'title' => 'Hidden',
            'slug' => 'hidden-page',
            'is_active' => false,
        ]);

        $this->get('/hidden-page')->assertNotFound();
    }

    public function test_reserved_top_level_slug_is_rejected_on_create(): void
    {
        $user = $this->userWithPermissions(['cms.create']);

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'Login CMS',
                'slug' => 'login',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('pages', ['slug' => 'login']);
    }

    public function test_reserved_nested_slug_is_rejected_on_create(): void
    {
        $user = $this->userWithPermissions(['cms.create']);

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'Admin Help',
                'slug' => 'admin/help',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('pages', ['slug' => 'admin/help']);
    }

    public function test_register_cannot_become_a_cms_page(): void
    {
        $user = $this->userWithPermissions(['cms.create']);

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'Join',
                'slug' => 'register',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_keanggotaan_cannot_become_a_cms_page(): void
    {
        $user = $this->userWithPermissions(['cms.create']);

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'Keanggotaan CMS',
                'slug' => 'keanggotaan',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'Nested',
                'slug' => 'keanggotaan/info',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_koperasi_cannot_become_a_cms_page(): void
    {
        $user = $this->userWithPermissions(['cms.create']);

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'Koperasi CMS',
                'slug' => 'koperasi',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_akses_cannot_become_a_cms_page(): void
    {
        $user = $this->userWithPermissions(['cms.create']);

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'Akses CMS',
                'slug' => 'akses',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_publishing_families_cannot_become_cms_pages(): void
    {
        $user = $this->userWithPermissions(['cms.create']);

        foreach (['publikasi', 'berita', 'kegiatan', 'siaran-pers', 'liputan-media'] as $slug) {
            $this->actingAs($user)
                ->post(route('admin.pages.store'), [
                    'title' => 'CMS '.$slug,
                    'slug' => $slug,
                    'is_active' => true,
                ])
                ->assertSessionHasErrors('slug');

            $this->assertDatabaseMissing('pages', ['slug' => $slug]);
        }

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'Nested berita',
                'slug' => 'berita/lama',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_teknologi_tani_cannot_become_a_cms_page(): void
    {
        $user = $this->userWithPermissions(['cms.create']);

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'CMS teknologi tani',
                'slug' => 'teknologi-tani',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'Nested',
                'slug' => 'teknologi-tani/lama',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_retired_presbi_slugs_cannot_become_cms_pages(): void
    {
        $user = $this->userWithPermissions(['cms.create']);

        foreach (['sermons', 'events', 'kontak'] as $slug) {
            $this->actingAs($user)
                ->post(route('admin.pages.store'), [
                    'title' => 'CMS '.$slug,
                    'slug' => $slug,
                    'is_active' => true,
                ])
                ->assertSessionHasErrors('slug');
        }
    }

    public function test_publishing_routes_are_not_captured_by_the_cms_catch_all(): void
    {
        Page::query()->create([
            'title' => 'Fake publikasi',
            'slug' => 'publikasi',
            'is_active' => true,
        ]);
        Page::query()->create([
            'title' => 'Fake berita',
            'slug' => 'berita',
            'is_active' => true,
        ]);

        $this->get('/publikasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Publishing/Index'));
        $this->get('/berita')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Publishing/Index'));
        $this->get('/kegiatan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Publishing/Index'));
    }

    public function test_knowledge_routes_are_not_captured_by_the_cms_catch_all(): void
    {
        Page::query()->create([
            'title' => 'Fake teknologi tani',
            'slug' => 'teknologi-tani',
            'is_active' => true,
        ]);

        $this->get('/teknologi-tani')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Knowledge/Index'));
    }

    public function test_membership_public_family_still_beats_cms(): void
    {
        Page::query()->create([
            'title' => 'Fake keanggotaan',
            'slug' => 'keanggotaan',
            'is_active' => true,
        ]);

        $this->get('/keanggotaan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Membership/Index'));
    }

    public function test_valid_nested_slug_can_be_created(): void
    {
        $user = $this->userWithPermissions(['cms.create']);

        $this->actingAs($user)
            ->post(route('admin.pages.store'), [
                'title' => 'Team',
                'slug' => 'about/team',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.pages.index'));

        $this->assertDatabaseHas('pages', ['slug' => 'about/team']);
    }

    public function test_page_update_may_keep_its_own_slug(): void
    {
        $user = $this->userWithPermissions(['cms.update']);
        $page = Page::query()->create([
            'title' => 'About',
            'slug' => 'about',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('admin.pages.update', $page), [
                'title' => 'About AB2TI',
                'slug' => 'about',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.pages.index'));

        $this->assertDatabaseHas('pages', ['id' => $page->id, 'title' => 'About AB2TI', 'slug' => 'about']);
    }

    public function test_page_update_cannot_rename_to_a_reserved_path(): void
    {
        $user = $this->userWithPermissions(['cms.update']);
        $page = Page::query()->create([
            'title' => 'About',
            'slug' => 'about',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('admin.pages.update', $page), [
                'title' => 'Dashboard',
                'slug' => 'dashboard',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseHas('pages', ['id' => $page->id, 'slug' => 'about']);
    }
}
