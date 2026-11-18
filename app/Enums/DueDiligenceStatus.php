<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DueDiligenceStatus: string implements HasLabel, HasColor
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case Waived = 'waived';
    case Blocked = 'blocked';
    case Cleared = 'cleared';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Passed, self::Cleared => 'success',
            self::Failed => 'danger',
            self::Waived => 'info',
            self::Blocked => 'warning',
        };
    }

    /** Statuses a user may pick when adding a checklist item. */
    public static function itemOptions(): array
    {
        return [
            self::Pending->value => self::Pending->getLabel(),
            self::Passed->value => self::Passed->getLabel(),
            self::Failed->value => self::Failed->getLabel(),
        ];
    }
}
