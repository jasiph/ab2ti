<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $user = \App\Models\User::first();
    if (!$user) {
        throw new Exception("No users found.");
    }

    echo "Checking User: " . $user->email . " (ID: " . $user->id . ")\n";
    echo "Roles: " . implode(', ', $user->getRoleNames()->toArray()) . "\n";

    $permission = 'heroes.view';
    if ($user->can($permission)) {
        echo "User HAS permission: $permission\n";
    }
    else {
        echo "User does NOT have permission: $permission\n";
    }

    $role = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
    if ($role) {
        echo "Admin Role Permissions: " . implode(', ', $role->permissions->pluck('name')->toArray()) . "\n";
        if ($role->hasPermissionTo($permission)) {
            echo "Admin Role HAS permission: $permission\n";
        }
        else {
            echo "Admin Role does NOT have permission: $permission\n";
        }
    }
    else {
        echo "Admin Role NOT found!\n";
    }

}
catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
