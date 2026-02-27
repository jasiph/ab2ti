<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $permissions = \Spatie\Permission\Models\Permission::pluck('name')->toArray();
    echo "Permissions found:\n";
    foreach ($permissions as $p) {
        echo "- $p\n";
    }
}
catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
