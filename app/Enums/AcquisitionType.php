<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum AcquisitionType: string implements HasLabel
{
    use HasTranslatedLabel;

    case CashPurchase = 'cash_purchase';
    case InstallmentPurchase = 'installment_purchase';
    case Partnership = 'partnership';
    case LandForUnits = 'land_for_units';
    case ProfitSharing = 'profit_sharing';
    case OwnedExisting = 'owned_existing';
}
