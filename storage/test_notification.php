<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$payment = App\Models\Payment::first();
$notifiable = App\Models\User::first();
$notification = new App\Notifications\PaymentNotification($payment);

echo "--- Database Array ---\n";
try {
    print_r($notification->toArray($notifiable));
} catch (\Exception $e) {
    echo "Exception in toArray: " . $e->getMessage() . "\n";
}

echo "\n--- Broadcast Array ---\n";
try {
    $broadcast = $notification->toBroadcast($notifiable);
    print_r($broadcast->data ?? $broadcast);
} catch (\Exception $e) {
    echo "Exception in toBroadcast: " . $e->getMessage() . "\n";
}
