<?php

namespace App\Filament\Support;

use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Runs a workflow service call from a Filament page/action and turns every
 * outcome into a notification the user actually sees — no silent failures.
 *
 * - ValidationException: the first field error, as a persistent notification.
 * - AuthorizationException: a persistent "not allowed" notification.
 * - Any other Throwable: reported and shown as a persistent notification too.
 */
trait RunsWorkflowAction
{
    /**
     * @param  callable(): mixed  $operation
     * @param  string|callable(mixed): string  $successTitle  A fixed title, or a callback resolved with $operation's result (e.g. to vary it between "created" and "linked").
     * @param  string|callable(mixed): ?string|null  $successBody
     * @param  callable(mixed): void|null  $onSuccess  Runs only after $operation succeeds (e.g. refresh the record).
     * @param  bool  $rethrowValidation  Set true when the action has a form, so Filament can also show the field error inline.
     */
    private function runWorkflowAction(
        callable $operation,
        string|callable $successTitle,
        ?string $unauthorizedBody = null,
        ?callable $onSuccess = null,
        bool $rethrowValidation = false,
        string|callable|null $successBody = null,
    ): mixed {
        try {
            $result = $operation();
            if ($onSuccess) {
                $onSuccess($result);
            }
            Notification::make()->success()
                ->title(is_callable($successTitle) ? $successTitle($result) : $successTitle)
                ->body(is_callable($successBody) ? $successBody($result) : $successBody)
                ->send();

            return $result;
        } catch (ValidationException $exception) {
            Notification::make()->danger()
                ->title(__('crm.errors.action_failed'))
                ->body(collect($exception->errors())->flatten()->first())
                ->persistent()
                ->send();
            if ($rethrowValidation) {
                throw $exception;
            }
        } catch (AuthorizationException) {
            Notification::make()->danger()
                ->title(__('crm.errors.unauthorized'))
                ->body($unauthorizedBody ?? __('crm.errors.unauthorized_body_generic'))
                ->persistent()
                ->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->danger()
                ->title(__('crm.errors.action_failed'))
                ->body($exception->getMessage())
                ->persistent()
                ->send();
        }

        return null;
    }
}
