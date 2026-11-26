<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Expense rules reject by column name, while the form listens on "data.<column>".
 * Without this translation a rejected save would show nothing at all.
 */
trait SurfacesExpenseErrors
{
    /** @param callable(): \Illuminate\Database\Eloquent\Model $save */
    private function saveOrExplain(callable $save): \Illuminate\Database\Eloquent\Model
    {
        try {
            return $save();
        } catch (ValidationException $exception) {
            // The notification also covers errors on fields the user cannot see.
            Notification::make()
                ->danger()
                ->title(__('expense_domain.errors.save_failed'))
                ->body(collect($exception->errors())->flatten()->first())
                ->persistent()
                ->send();

            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $field): array => ["data.{$field}" => $messages])->all()
            );
        }
    }
}
