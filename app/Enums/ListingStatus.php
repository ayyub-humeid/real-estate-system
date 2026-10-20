<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ListingStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
    case Archived = 'archived';

    public static function options(): array
    {
        return [
            self::Draft->value => self::Draft->getLabel(),
            self::Published->value => self::Published->getLabel(),
            self::Paused->value => self::Paused->getLabel(),
            self::Archived->value => self::Archived->getLabel(),
        ];
    }

    public function getLabel(): ?string
    {
        return __(match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Paused => 'Paused',
            self::Archived => 'Archived',
        });
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Published,
            self::Published => in_array($target, [self::Paused, self::Archived], true),
            self::Paused => in_array($target, [self::Published, self::Archived], true),
            self::Archived => $target === self::Draft,
        };
    }
}
