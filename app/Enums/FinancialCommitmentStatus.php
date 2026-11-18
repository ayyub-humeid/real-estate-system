<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum FinancialCommitmentStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Committed = 'committed';
    case Released = 'released';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
}
