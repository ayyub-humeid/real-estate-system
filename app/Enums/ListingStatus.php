<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
    case Archived = 'archived';

    public static function options(): array
    {
        return [
            self::Draft->value => 'Draft',
            self::Published->value => 'Published',
            self::Paused->value => 'Paused',
            self::Archived->value => 'Archived',
        ];
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
