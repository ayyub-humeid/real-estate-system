<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Support\Str;

trait UsesLocalizedResourceLabels
{
    public static function getModelLabel(): string
    {
        return static::safeTranslate(static::legacyResourceLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return static::safeTranslate(Str::plural(static::legacyResourceLabel()));
    }

    public static function getNavigationLabel(): string
    {
        return static::safeTranslate(static::$navigationLabel ?? static::getPluralModelLabel());
    }

    public static function getNavigationGroup(): ?string
    {
        return filled(static::$navigationGroup) ? static::safeTranslate(static::$navigationGroup) : null;
    }

    /**
     * Translate a key safely.
     *
     * Laravel's __() uses the first segment of the key as the PHP file namespace.
     * When a lang/*.php file shares the same name as the key (e.g. "Locations"
     * matches lang/locations.php), __() returns the entire array instead of a
     * string. This helper detects that case and returns the original key as a
     * readable fallback so no TypeError is thrown.
     */
    private static function safeTranslate(string $key): string
    {
        $result = __($key);

        if (is_array($result)) {
            // Humanize the key as a readable fallback (e.g. "Locations" stays "Locations")
            return Str::headline($key);
        }

        return (string) $result;
    }

    private static function legacyResourceLabel(): string
    {
        return Str::headline(class_basename(static::getModel()));
    }
}
