<?php

namespace App\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Where a notification's "Open" button goes: the page of the first record that has one.
 * Pass the most specific record first and a broader one (usually the project) last.
 */
class NotificationLink
{
    public static function to(?Model ...$records): ?string
    {
        foreach (array_filter($records) as $record) {
            if ($url = self::pageFor($record)) {
                return $url;
            }
        }

        return null;
    }

    private static function pageFor(Model $record): ?string
    {
        try {
            $panel = Filament::getPanel('admin');
            $resource = $panel->getModelResource($record);
            if (! $resource || ! $record->exists) {
                return null;
            }

            foreach (['view', 'edit'] as $page) {
                if (array_key_exists($page, $resource::getPages())) {
                    return $resource::getUrl($page, ['record' => $record], panel: 'admin');
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return null;
    }
}
