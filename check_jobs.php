<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$jobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
echo "Pending jobs: $jobs\n";
