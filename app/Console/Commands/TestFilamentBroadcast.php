<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Filament\Notifications\Notification;

class TestFilamentBroadcast extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:filament-broadcast {userId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger a Filament broadcast notification for a specific user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('userId');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User not found!");
            return;
        }

        Notification::make()
            ->title('Test Broadcast Notification')
            ->body('This is a live test via WebSocket.')
            ->success()
            ->sendToDatabase($user)
            ->broadcast($user);

        $this->info("Broadcast notification sent to user {$user->name}!");
    }
}
