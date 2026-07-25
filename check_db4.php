<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$notifications = \Illuminate\Support\Facades\DB::table('notifications')
    ->where('data->format', 'filament')
    ->get();
echo "Filament format notifications: " . $notifications->count() . "\n";
