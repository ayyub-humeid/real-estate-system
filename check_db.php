<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = \Illuminate\Support\Facades\DB::table('notifications')->count();
echo "Total notifications in DB: $count\n";

$notifications = \Illuminate\Support\Facades\DB::table('notifications')->get();
foreach ($notifications as $n) {
    echo "ID: {$n->id}, Type: {$n->type}, Notifiable: {$n->notifiable_id}\n";
}
