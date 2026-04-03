<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$role = \Spatie\Permission\Models\Role::findByName('property_manager');
$perms = $role->permissions->where('name', 'like', '%unit::feature%')->pluck('name')->toArray();

if (count($perms) > 0) {
    echo "SUCCESS: Property Manager has " . count($perms) . " Unit Feature permissions.\n";
    foreach ($perms as $p) echo "- $p\n";
} else {
    echo "FAILURE: Property Manager is missing Unit Feature permissions.\n";
}

$imagePerms = $role->permissions->where('name', 'like', '%image%')->pluck('name')->toArray();
if (count($imagePerms) > 0) {
    echo "SUCCESS: Property Manager has " . count($imagePerms) . " Image (Gallery) permissions.\n";
} else {
    echo "FAILURE: Property Manager is missing Image (Gallery) permissions.\n";
}
