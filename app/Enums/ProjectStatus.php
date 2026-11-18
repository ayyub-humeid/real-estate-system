<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum ProjectStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    case Planning = 'planning';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /** @return array<int, string> Statuses counted as "active" on dashboards. */
    public static function activeValues(): array
    {
        return [self::Planning->value, self::Approved->value, self::InProgress->value];
    }
}
