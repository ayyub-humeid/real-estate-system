<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AcquisitionStatus: string implements HasLabel, HasColor
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case UnderDueDiligence = 'under_due_diligence';
    case Approved = 'approved';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::UnderDueDiligence => 'warning',
            self::Approved => 'info',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
