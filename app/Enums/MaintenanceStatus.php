<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MaintenanceStatus: string implements HasLabel, HasColor
{
    use HasTranslatedLabel;

    case New = 'new';
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Pending => 'warning',
            self::InProgress => 'info',
            self::Resolved => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Work is still outstanding, so the unit may need to stay out of service. */
    public function isOpen(): bool
    {
        return in_array($this->value, self::openValues(), true);
    }

    /** @return array<int, string> Statuses counted as "pending work" on dashboards. */
    public static function openValues(): array
    {
        return [self::New->value, self::Pending->value, self::InProgress->value];
    }
}
