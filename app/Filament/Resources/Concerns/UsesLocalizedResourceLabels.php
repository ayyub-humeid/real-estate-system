<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Support\Str;

trait UsesLocalizedResourceLabels
{
    public static function getModelLabel(): string
    {
        return __(static::legacyResourceLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return __(Str::plural(static::legacyResourceLabel()));
    }

    public static function getNavigationLabel(): string
    {
        return __(static::$navigationLabel ?? static::getPluralModelLabel());
    }

    public static function getNavigationGroup(): ?string
    {
        return filled(static::$navigationGroup) ? __(static::$navigationGroup) : null;
    }

    private static function legacyResourceLabel(): string
    {
        return Str::headline(class_basename(static::getModel()));
    }
}
