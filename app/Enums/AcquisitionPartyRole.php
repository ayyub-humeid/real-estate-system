<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum AcquisitionPartyRole: string implements HasLabel
{
    use HasTranslatedLabel;

    case Seller = 'seller';
    case Buyer = 'buyer';
    case Broker = 'broker';
    case Investor = 'investor';
    case Guarantor = 'guarantor';
    case Other = 'other';
}
