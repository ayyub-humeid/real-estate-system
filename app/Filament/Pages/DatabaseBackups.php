<?php

namespace App\Filament\Pages;

use App\Jobs\RunDatabaseBackup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DatabaseBackups extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.database-backups';

    public static function getNavigationGroup(): ?string
    {
        return __('backups.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('backups.title');
    }

    public function getTitle(): string
    {
        return __('backups.title');
    }

    /** A backup holds every company's data, so only the platform owner may see it. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function isRunning(): bool
    {
        return Cache::has(RunDatabaseBackup::RUNNING_KEY);
    }

    /** @return array{backups: list<array{path: string, name: string, size: int, date: \Carbon\Carbon}>, error: ?string} */
    public function listing(): array
    {
        try {
            $disk = Storage::disk($this->disk());
            $backups = collect($disk->files($this->folder()))
                ->filter(fn (string $path) => str_ends_with($path, '.zip'))
                ->map(fn (string $path) => [
                    'path' => $path,
                    'name' => basename($path),
                    'size' => (int) $disk->size($path),
                    'date' => \Carbon\Carbon::createFromTimestamp($disk->lastModified($path)),
                ])
                ->sortByDesc('date')->values()->all();

            return ['backups' => $backups, 'error' => null];
        } catch (Throwable $exception) {
            Log::error('Could not list backups.', ['error' => $exception->getMessage()]);

            return ['backups' => [], 'error' => $exception->getMessage()];
        }
    }

    public function createBackup(): void
    {
        abort_unless(static::canAccess(), 403);

        if ($this->isRunning()) {
            Notification::make()->warning()->title(__('backups.already_running'))->send();

            return;
        }

        Cache::put(RunDatabaseBackup::RUNNING_KEY, true, now()->addMinutes(20));
        RunDatabaseBackup::dispatch(auth()->id());

        Notification::make()->success()->title(__('backups.started_title'))->body(__('backups.started_body'))->send();
    }

    public function download(string $path)
    {
        abort_unless(static::canAccess(), 403);

        $known = collect($this->listing()['backups'])->pluck('path');
        if (! $known->contains($path)) {
            Notification::make()->danger()->title(__('backups.download_missing'))->persistent()->send();

            return null;
        }

        try {
            $url = Storage::disk($this->disk())->temporaryUrl($path, now()->addMinutes(5));
        } catch (Throwable $exception) {
            Log::error('Could not prepare a backup download.', ['path' => $path, 'error' => $exception->getMessage()]);
            Notification::make()->danger()->persistent()->title(__('backups.download_failed'))->body($exception->getMessage())->send();

            return null;
        }

        Log::info('Database backup downloaded.', ['user_id' => auth()->id(), 'file' => $path]);

        return redirect()->away($url);
    }

    private function disk(): string
    {
        return config('backup.backup.destination.disks')[0];
    }

    private function folder(): string
    {
        return config('backup.backup.name');
    }
}
