<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$n = \Illuminate\Support\Facades\DB::table('notifications')->first();
if ($n) {
    echo "ID: {$n->id}\n";
    echo "Data:\n{$n->data}\n";
} else {
    echo "No notifications found.\n";
}
