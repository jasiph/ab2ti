<?php

namespace Tests\Feature\Admin;

use App\Models\File;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->syncAclFromConfig();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function protectedAdminReads(): array
    {
        return [
            'users index' => ['get', 'admin.users.index', []],
            'roles index' => ['get', 'admin.roles.index', []],
            'files index' => ['get', 'admin.files.index', []],
            'heroes index' => ['get', 'admin.heroes.index', []],
            'pages index' => ['get', 'admin.pages.index', []],
            'block templates index' => ['get', 'admin.block-templates.index', []],
            'welcome section edit' => ['get', 'admin.welcome-section.edit', []],
        ];
    }

    #[DataProvider('protectedAdminReads')]
    public function test_guest_is_redirected_from_admin_reads(string $method, string $routeName, array $params): void
    {
        $this->$method(route($routeName, $params))->assertRedirect(route('login'));
    }

    #[DataProvider('protectedAdminReads')]
    public function test_authenticated_user_without_permission_receives_403_on_admin_reads(string $method, string $routeName, array $params): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->$method(route($routeName, $params))->assertForbidden();
    }

    public function test_user_with_view_permission_can_read_users_index(): void
    {
        $user = $this->userWithPermissions(['users.view']);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_user_without_create_permission_cannot_create_users(): void
    {
        $actor = $this->userWithPermissions(['users.view']);

        $this->actingAs($actor)
            ->post(route('admin.users.store'), [
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'password123',
                'role' => 'super-admin',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_user_with_create_permission_can_create_users(): void
    {
        $actor = $this->userWithPermissions(['users.create']);
        $this->syncAclFromConfig();

        $this->actingAs($actor)
            ->post(route('admin.users.store'), [
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'password123',
                'role' => 'super-admin',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_user_with_media_view_can_list_files(): void
    {
        $user = $this->userWithPermissions(['media.view']);

        $this->actingAs($user)
            ->get(route('admin.files.index'))
            ->assertOk();
    }

    public function test_user_without_media_create_cannot_upload_files(): void
    {
        $user = $this->userWithPermissions(['media.view']);

        $this->actingAs($user)
            ->post(route('admin.files.store'), [
                'files' => [UploadedFile::fake()->image('blocked.jpg')],
            ])
            ->assertForbidden();
    }

    public function test_user_with_cms_update_can_update_a_page(): void
    {
        $user = $this->userWithPermissions(['cms.update']);
        $page = Page::query()->create([
            'title' => 'Original',
            'slug' => 'original',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('admin.pages.update', $page), [
                'title' => 'Updated',
                'slug' => 'original',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pages', ['id' => $page->id, 'title' => 'Updated']);
    }

    public function test_user_without_cms_update_cannot_update_a_page(): void
    {
        $user = $this->userWithPermissions(['cms.view']);
        $page = Page::query()->create([
            'title' => 'Original',
            'slug' => 'original',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('admin.pages.update', $page), [
                'title' => 'Updated',
                'slug' => 'original',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('pages', ['id' => $page->id, 'title' => 'Original']);
    }

    public function test_super_admin_bypasses_permission_checks(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.files.index'))
            ->assertOk();
    }

    public function test_authenticated_user_can_open_dashboard_without_module_permissions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_guest_cannot_restore_a_file(): void
    {
        $owner = User::factory()->create();
        $file = File::query()->create(['user_id' => $owner->id, 'name' => 'gone.jpg']);
        $file->delete();

        $this->post(route('admin.files.restore', $file->id))
            ->assertRedirect(route('login'));
    }
}
