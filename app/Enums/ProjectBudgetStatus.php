<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum ProjectBudgetStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Superseded = 'superseded';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
