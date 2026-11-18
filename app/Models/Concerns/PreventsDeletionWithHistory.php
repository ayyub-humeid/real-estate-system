<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Refuses to delete a record whose removal would erase (or cascade into) history.
 * Enforced in the model event so every path is covered: Filament, services, bulk actions.
 */
trait PreventsDeletionWithHistory
{
    /** @return array<string, bool|\Closure(): bool> keys are deletion.blockers.* translation keys */
    abstract protected function deletionChecks(): array;

    public static function bootPreventsDeletionWithHistory(): void
    {
        static::deleting(function (Model $model): void {
            $blockers = $model->deletionBlockers();

            if ($blockers !== []) {
                throw ValidationException::withMessages(['record' => static::deletionBlockedMessage($blockers)]);
            }
        });
    }

    /** @return array<int, string> Translated reasons the record must be kept. */
    public function deletionBlockers(): array
    {
        return collect($this->deletionChecks())
            ->filter(fn (bool|\Closure $check): bool => $check instanceof \Closure ? (bool) $check() : $check)
            ->keys()
            ->map(fn (string $key): string => __('deletion.blockers.' . $key))
            ->values()
            ->all();
    }

    /** @param array<int, string> $blockers */
    public static function deletionBlockedMessage(array $blockers): string
    {
        return __('deletion.blocked_body', ['items' => implode(__('deletion.separator'), $blockers)]);
    }
}
