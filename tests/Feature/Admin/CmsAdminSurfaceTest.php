<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class CmsAdminSurfaceTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_create_routes_are_not_shadowed_by_missing_show_actions(): void
    {
        $this->assertFalse(Route::has('admin.files.show'));
        $this->assertFalse(Route::has('admin.pages.show'));
        $this->assertFalse(Route::has('admin.block-templates.show'));
        $this->assertFalse(Route::has('admin.heroes.show'));
        $this->assertFalse(Route::has('admin.users.show'));
        $this->assertFalse(Route::has('admin.roles.show'));
        $this->assertFalse(Route::has('admin.users.create'));
        $this->assertFalse(Route::has('admin.roles.create'));

        $this->assertTrue(Route::has('admin.files.create'));
        $this->assertTrue(Route::has('admin.pages.create'));
        $this->assertTrue(Route::has('admin.block-templates.create'));
        $this->assertTrue(Route::has('admin.heroes.create'));
        $this->assertTrue(Route::has('admin.heroes.toggle'));
    }

    public function test_cms_create_screens_render_for_authorized_staff(): void
    {
        $staff = $this->userWithPermissions([
            'cms.view',
            'cms.create',
            'heroes.view',
            'heroes.create',
            'media.view',
            'media.create',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.pages.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Pages/Form'));

        $this->actingAs($staff)
            ->get(route('admin.block-templates.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/BlockTemplates/Form'));

        $this->actingAs($staff)
            ->get(route('admin.heroes.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Hero/Form'));

        $this->actingAs($staff)
            ->get(route('admin.files.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/FileManager/Form'));
    }

    public function test_view_permission_cannot_open_create_screens(): void
    {
        $viewer = $this->userWithPermissions(['cms.view', 'heroes.view', 'media.view']);

        $this->actingAs($viewer)->get(route('admin.pages.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.block-templates.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.heroes.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.files.create'))->assertForbidden();
    }

    public function test_cms_indexes_and_welcome_section_load(): void
    {
        $staff = $this->userWithPermissions(['cms.view', 'heroes.view', 'media.view']);

        Page::query()->create([
            'title' => 'About',
            'slug' => 'about',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.pages.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Pages/Index'));

        $this->actingAs($staff)
            ->get(route('admin.block-templates.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/BlockTemplates/Index'));

        $this->actingAs($staff)
            ->get(route('admin.heroes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Hero/Index'));

        $this->actingAs($staff)
            ->get(route('admin.files.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/FileManager/Index'));

        $this->actingAs($staff)
            ->get(route('admin.welcome-section.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/WelcomeSection/Form'));
    }

    public function test_cms_page_edit_loads_and_public_about_contact_remain_valid(): void
    {
        $staff = $this->userWithPermissions(['cms.view', 'cms.update']);
        $page = Page::query()->create([
            'title' => 'About',
            'slug' => 'about',
            'content' => '<p>Tentang AB2TI</p>',
            'is_active' => true,
        ]);
        Page::query()->create([
            'title' => 'Contact',
            'slug' => 'contact',
            'content' => '<p>Kontak</p>',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertInertia(fn ($inertia) => $inertia
                ->component('Admin/Pages/Form')
                ->where('page.slug', 'about'));

        $this->get('/about')
            ->assertOk()
            ->assertInertia(fn ($inertia) => $inertia->component('Frontend/Page/Show'));

        $this->get('/contact')
            ->assertOk()
            ->assertInertia(fn ($inertia) => $inertia->component('Frontend/Page/Show'));

        $this->get('/publikasi')->assertOk();
        $this->get('/teknologi-tani')->assertOk();
        $this->get('/')->assertOk();
    }

    public function test_welcome_section_can_be_created_and_homepage_still_reads_it(): void
    {
        $staff = $this->userWithPermissions(['cms.view', 'cms.update']);

        $this->actingAs($staff)
            ->put(route('admin.welcome-section.update'), [
                'title' => 'Sambutan',
                'content' => 'Teks sambutan',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('welcome_sections', [
            'title' => 'Sambutan',
            'content' => 'Teks sambutan',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Landing/Welcome')
                ->where('welcomeSection.title', 'Sambutan'));
    }

    public function test_unauthorized_user_cannot_mutate_welcome_section(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.welcome-section.update'), [
                'title' => 'Blocked',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('welcome_sections', ['title' => 'Blocked']);
    }
}
