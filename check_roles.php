<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::first();
echo "User ID: {$user->id}, Role col: {$user->role}\n";
$roles = $user->roles()->pluck('name')->toArray();
echo "Spatie roles: " . implode(', ', $roles) . "\n";
