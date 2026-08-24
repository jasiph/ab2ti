<?php

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithAcl
{
    protected function syncAclFromConfig(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (collect(config('acl.permissions'))->flatten()->unique() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('super-admin', 'web');
    }

    protected function userWithPermissions(array $permissions, array $userAttributes = []): User
    {
        $this->syncAclFromConfig();

        $user = User::factory()->create($userAttributes);
        $this->grantPlatformStaffBoundary($user);
        $user->givePermissionTo($permissions);

        return $user;
    }

    /**
     * Mark a test actor as platform staff without attaching catalog permissions.
     *
     * Production staff always hold `super-admin`, `membership-admin`, or `editor`.
     * ACL feature tests grant permissions directly; the empty `editor` role is only
     * the staff-boundary ticket so those tests can still enter `/admin`.
     */
    protected function grantPlatformStaffBoundary(User $user): User
    {
        Role::findOrCreate('editor', 'web');
        $user->assignRole('editor');

        return $user;
    }

    protected function superAdmin(array $userAttributes = []): User
    {
        $this->syncAclFromConfig();

        $user = User::factory()->create($userAttributes);
        $user->assignRole('super-admin');

        return $user;
    }
}
