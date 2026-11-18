<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Voided = 'voided';
}
