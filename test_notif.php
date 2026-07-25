<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$n = \Filament\Notifications\Notification::make()->title('test');
print_r($n->getDatabaseMessage());
print_r($n->getBroadcastMessage());
