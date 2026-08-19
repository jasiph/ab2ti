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

        // User biasa hanya boleh lihat
        $userRole->givePermissionTo([
            'media.view',
        ]);

        // 5. Create Default Users
        // Super Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole($superAdminRole);

        // Regular User
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
