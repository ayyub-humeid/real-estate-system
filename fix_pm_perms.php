<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "--- Fixing Property Manager Permissions ---\n";

// 1. Clear cache
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

// 2. Get the role
$role = Role::findOrCreate('property_manager', 'web');

// 3. Find and sync permissions
$permissions = Permission::where(function($q) {
    $q->where('name', 'like', '%property%')
      ->orWhere('name', 'like', '%unit%')
      ->orWhere('name', 'like', '%image%')
      ->orWhere('name', 'like', '%maintenance%')
      ->orWhere('name', 'like', '%rental%')
      ->orWhere('name', 'like', '%location%')
      ->orWhere('name', 'like', '%tenant%')
      ->orWhere('name', 'like', '%company%')
      ->orWhere('name', 'like', '%employee%');
})->whereNotIn('name', ['delete_any_company', 'delete_company', 'create_company']) // Safety
->get();

$role->syncPermissions($permissions);

echo "Synced " . $role->permissions()->count() . " permissions to Property Manager.\n";

// 4. Verify specifically for unit::feature
$unitFeaturePerms = $role->permissions()->where('name', 'like', '%unit::feature%')->pluck('name');
echo "Unit Feature permissions found: " . $unitFeaturePerms->count() . "\n";
foreach($unitFeaturePerms as $p) echo " - $p\n";

if ($unitFeaturePerms->isEmpty()) {
    echo "WARNING: No unit::feature permissions found in the database at all!\n";
    echo "Total permissions in DB: " . Permission::count() . "\n";
}
