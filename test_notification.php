<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $payment = \App\Models\Payment::first();
    if (!$payment) {
        echo "No payment found\n";
        exit;
    }
    $user = \App\Models\User::first();
    
    $notification = new \App\Notifications\PaymentNotification($payment);
    $dbMessage = $notification->toDatabase($user);
    echo "toDatabase success:\n";
    print_r($dbMessage);
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
