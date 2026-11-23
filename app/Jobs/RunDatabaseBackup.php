<?php

namespace App\Jobs;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunDatabaseBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const RUNNING_KEY = 'database-backup-running';

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $code = Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);

        Cache::forget(self::RUNNING_KEY);

        if ($code !== 0) {
            $this->tell(false, trim(Artisan::output()));

            return;
        }

        Log::info('Database backup created from the dashboard.', ['user_id' => $this->userId]);
        $this->tell(true);
    }

    public function failed(Throwable $exception): void
    {
        Cache::forget(self::RUNNING_KEY);
        Log::error('Database backup from the dashboard failed.', ['user_id' => $this->userId, 'error' => $exception->getMessage()]);
        $this->tell(false, $exception->getMessage());
    }

    private function tell(bool $ok, string $detail = ''): void
    {
        $user = User::withoutGlobalScopes()->find($this->userId);
        if (! $user) {
            return;
        }

        $notification = Notification::make()->persistent();
        $ok
            ? $notification->success()->title(__('backups.notify.done_title'))->body(__('backups.notify.done_body'))
            : $notification->danger()->title(__('backups.notify.failed_title'))->body(__('backups.notify.failed_body', ['detail' => \Illuminate\Support\Str::limit($detail, 300)]));

        $notification->sendToDatabase($user);
    }
}
