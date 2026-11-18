<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteAction as PageDeleteAction;
use Filament\Actions\MountableAction;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\DeleteAction as TableDeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Applies to every Filament delete action:
 * - explains history blockers instead of deleting,
 * - turns database constraint failures into a translated notification,
 * - makes bulk delete honour the per-record `delete` policy (Filament only checks `deleteAny`).
 */
class SafeDeletion
{
    public static function register(): void
    {
        foreach ([TableDeleteAction::class, PageDeleteAction::class] as $actionClass) {
            $actionClass::configureUsing(fn (MountableAction $action) => $action
                ->before(fn (MountableAction $action, Model $record) => static::cancelIfBlocked($action, $record))
                ->using(fn (MountableAction $action, Model $record): bool => static::deleteOne($action, $record)));
        }

        DeleteBulkAction::configureUsing(fn (DeleteBulkAction $action) => $action
            ->using(fn (DeleteBulkAction $action, Collection $records): bool => static::deleteMany($action, $records)));
    }

    private static function cancelIfBlocked(MountableAction $action, Model $record): void
    {
        $blockers = method_exists($record, 'deletionBlockers') ? $record->deletionBlockers() : [];

        if ($blockers === []) {
            return;
        }

        static::notify(__('deletion.blocked_title'), $record::deletionBlockedMessage($blockers));
        $action->cancel();
    }

    private static function deleteOne(MountableAction $action, Model $record): bool
    {
        try {
            return DB::transaction(fn (): bool => (bool) $record->delete());
        } catch (ValidationException $exception) {
            static::notify(__('deletion.blocked_title'), (string) collect($exception->errors())->flatten()->first());
        } catch (QueryException $exception) {
            report($exception);
            static::notify(__('deletion.failed_title'), __('deletion.failed_body'));
        }

        $action->cancel();

        return false;
    }

    private static function deleteMany(DeleteBulkAction $action, Collection $records): bool
    {
        $deleted = 0;

        foreach ($records as $record) {
            if (Gate::denies('delete', $record)) {
                continue;
            }

            try {
                DB::transaction(fn () => $record->delete());
                $deleted++;
            } catch (ValidationException) {
                // Kept: the record has history; counted below.
            } catch (QueryException $exception) {
                report($exception);
            }
        }

        $kept = $records->count() - $deleted;

        if ($kept > 0) {
            Notification::make()
                ->warning()
                ->title(__('deletion.bulk_partial_title'))
                ->body(__('deletion.bulk_partial_body', ['deleted' => $deleted, 'total' => $records->count(), 'kept' => $kept]))
                ->persistent()
                ->send();

            $action->cancel();
        }

        return true;
    }

    private static function notify(string $title, string $body): void
    {
        Notification::make()->danger()->title($title)->body($body)->persistent()->send();
    }
}
