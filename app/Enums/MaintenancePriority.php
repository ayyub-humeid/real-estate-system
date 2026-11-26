<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MaintenancePriority: string implements HasLabel, HasColor
{
    use HasTranslatedLabel;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Emergency = 'emergency';

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'info',
            self::High => 'warning',
            self::Emergency => 'danger',
        };
    }
}
