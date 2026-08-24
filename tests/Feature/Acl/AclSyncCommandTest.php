<?php

namespace Tests\Feature\Acl;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class AclSyncCommandTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_ordinary_sync_adds_canonical_permissions_and_leaves_stale_rows(): void
    {
        $this->syncAclFromConfig();

        $stale = Permission::findOrCreate('culture.view', 'web');
        $role = Role::findOrCreate('editor', 'web');
        $role->givePermissionTo($stale);

        $this->artisan('acl:sync')
            ->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['name' => 'cms.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'culture.view']);
        $this->assertTrue($role->fresh()->hasPermissionTo('culture.view'));
    }

    public function test_dry_run_clean_does_not_delete_stale_permissions(): void
    {
        $this->syncAclFromConfig();
        Permission::findOrCreate('artikel.view', 'web');

        $this->artisan('acl:sync', ['--clean' => true, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['name' => 'artikel.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'cms.view']);
    }

    public function test_clean_removes_confirmed_stale_permissions_and_preserves_roles_users_and_canonical_assignments(): void
    {
        $this->syncAclFromConfig();

        $user = User::factory()->create();
        $role = Role::findOrCreate('editor', 'web');
        $role->givePermissionTo(['cms.view', 'media.view']);
        $user->assignRole($role);

        $stale = Permission::findOrCreate('culture.view', 'web');
        Permission::findOrCreate('artikel.create', 'web');
        $role->givePermissionTo($stale);

        $this->artisan('acl:sync', ['--clean' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('permissions', ['name' => 'culture.view']);
        $this->assertDatabaseMissing('permissions', ['name' => 'artikel.create']);
        $this->assertDatabaseHas('permissions', ['name' => 'cms.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'access settings']);
        $this->assertDatabaseHas('roles', ['name' => 'editor']);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertTrue($user->fresh()->hasRole('editor'));
        $this->assertTrue($role->fresh()->hasPermissionTo('cms.view'));
        $this->assertFalse($role->fresh()->permissions()->where('name', 'culture.view')->exists());

        $this->artisan('acl:sync', ['--clean' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['name' => 'cms.view']);
        $this->assertDatabaseMissing('permissions', ['name' => 'culture.view']);
    }
}
