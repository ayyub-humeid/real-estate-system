<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExpenseStatus: string implements HasLabel, HasColor
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Pending => in_array($to, [self::Paid, self::Cancelled], true),
            self::Paid => $to === self::Cancelled,
            self::Cancelled => false,
        };
    }

    /** Statuses a person may choose when first recording an expense. @return array<string, string> */
    public static function creationOptions(): array
    {
        return [self::Pending->value => self::Pending->getLabel(), self::Paid->value => self::Paid->getLabel()];
    }
}
