<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$payment = App\Models\Payment::first();
$notifiable = App\Models\User::first();
$notification = new App\Notifications\PaymentNotification($payment);

try {
    $array = $notification->toArray($notifiable);
    $json = json_encode($array);
    if ($json === false) {
        echo "JSON Encode failed: " . json_last_error_msg() . "\n";
    } else {
        echo "JSON encoded Database Array: \n" . $json . "\n";
    }
} catch (\Exception $e) {
    echo "Exception in toArray: " . $e->getMessage() . "\n";
}

try {
    $broadcast = $notification->toBroadcast($notifiable);
    $json = json_encode($broadcast->data);
    if ($json === false) {
        echo "JSON Encode failed for broadcast: " . json_last_error_msg() . "\n";
    } else {
        echo "JSON encoded Broadcast Array: \n" . $json . "\n";
    }
} catch (\Exception $e) {
    echo "Exception in toBroadcast: " . $e->getMessage() . "\n";
}
