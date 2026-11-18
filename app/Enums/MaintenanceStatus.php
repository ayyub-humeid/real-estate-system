<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum MaintenanceStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    case New = 'new';
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';

    /** @return array<int, string> Statuses counted as "pending work" on dashboards. */
    public static function openValues(): array
    {
        return [self::New->value, self::Pending->value, self::InProgress->value];
    }
}
