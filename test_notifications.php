<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\MaintenanceRequest;
use App\Models\Unit;
use App\Models\Company;

echo "--- Testing Notification System ---\n";

// 1. Create a dummy company and manager
$company = Company::firstOrCreate(['name' => 'Test Company']);
$manager = User::firstOrCreate(
    ['email' => 'test_manager@example.com'],
    ['name' => 'Manager X', 'password' => bcrypt('password'), 'company_id' => $company->id, 'role' => 'property_manager']
);
$manager->syncRoles(['property_manager']);

$unit = Unit::first() ?? Unit::create(['name' => 'Unit 101', 'property_id' => 1, 'company_id' => $company->id]);

// 2. Clear existing notifications for manager
$manager->notifications()->delete();

// 3. Create a Maintenance Request
echo "Creating Maintenance Request...\n";
$request = MaintenanceRequest::create([
    'unit_id' => $unit->id,
    'company_id' => $company->id,
    'reported_by_id' => $manager->id,
    'title' => 'Bathroom Issue',
    'description' => 'Leaking pipe in bathroom',
    'status' => 'pending',
    'priority' => 'high'
]);

// 4. Verify
$notificationCount = $manager->notifications()->count();
if ($notificationCount > 0) {
    echo "SUCCESS: Manager received " . $notificationCount . " notification.\n";
    echo "Notification Body: " . $manager->notifications()->first()->data['body'] . "\n";
} else {
    echo "FAILURE: No notification found for manager.\n";
}
