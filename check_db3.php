<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::whereHas('roles', function($q) { $q->whereIn('name', ['company_admin']); })->first();
if (!$user) { echo "No admin found.\n"; exit; }

echo "User ID: {$user->id}\n";
$notifications = $user->notifications()->where('data->format', 'filament')->get();
echo "Filament Notifications count: " . $notifications->count() . "\n";

foreach ($notifications as $n) {
    echo "ID: {$n->id}\n";
}
