<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Reset Cache Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Ambil struktur dari config/acl.php
        $permissionStructure = config('acl.permissions'); // <--- PERUBAHAN DISINI

        // 3. Loop & Create Permission
        foreach ($permissionStructure as $group => $permissions) {
            foreach ($permissions as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName]);
            }
        }

        // 4. Create Role & Assign Permission (Contoh)
        $userRole = Role::firstOrCreate(['name' => 'user']);
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $membershipAdminRole = Role::firstOrCreate(['name' => 'membership-admin']);

        // User biasa hanya boleh lihat
        $userRole->givePermissionTo([
            'media.view',
        ]);

        $membershipAdminRole->syncPermissions([
            'membership-applications.view',
            'membership-applications.review',
            'membership-applications.approve',
            'membership-applications.reject',
            'membership-applications.request-revision',
            'membership-members.view',
            'membership-members.update',
            'membership-documents.view',
            'membership-documents.manage',
            'lapak.view',
            'lapak.manage',
            'lapak.moderate',
        ]);

        $editorRole = Role::firstOrCreate(['name' => 'editor']);
        $editorRole->syncPermissions([
            'cms.view',
            'cms.create',
            'cms.update',
            'cms.delete',
            'heroes.view',
            'heroes.create',
            'heroes.update',
            'heroes.delete',
            'media.view',
            'media.create',
            'media.update',
            'media.delete',
            'publishing-publications.view',
            'publishing-publications.create',
            'publishing-publications.update',
            'publishing-publications.publish',
            'publishing-publications.delete',
            'knowledge-articles.view',
            'knowledge-articles.create',
            'knowledge-articles.update',
            'knowledge-articles.publish',
            'knowledge-articles.delete',
        ]);

        if (app()->environment('production')) {
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole($superAdminRole);

        $user = User::firstOrCreate(
            ['email' => 'user@user.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $user->assignRole($userRole);
    }
}
