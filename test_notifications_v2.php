<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\MaintenanceRequest;
use App\Models\Unit;
use App\Notifications\MaintenanceRequestNotification;

echo "--- Testing Notification Logic (Manual Dispatch) ---\n";

// 1. Get any existing user
$user = User::first();
if (!$user) {
    echo "FAILURE: No user found in DB to notify.\n";
    exit(1);
}

// 2. Clear existing notifications
$user->notifications()->delete();

// 3. Create a mock MaintenanceRequest (not saved to DB to avoid errors)
$mockRequest = new MaintenanceRequest();
$mockRequest->unit = Unit::first() ?? new Unit(['name' => 'Mock Unit']);
$mockRequest->unit->property = new \App\Models\Property(['name' => 'Mock Property']);
$mockRequest->description = 'Test Description';
$mockRequest->id = 999;

// 4. Manually dispatch
echo "Dispatching notification to User ID: {$user->id}...\n";
$user->notify(new MaintenanceRequestNotification($mockRequest));

// 5. Verify
$notificationCount = $user->notifications()->count();
if ($notificationCount > 0) {
    echo "SUCCESS: Dashboard notification stored in database!\n";
    echo "Data: " . json_encode($user->notifications()->first()->data) . "\n";
} else {
    echo "FAILURE: Notification not stored.\n";
}
