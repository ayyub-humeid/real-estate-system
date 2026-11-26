<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/** Same values as payments.payment_method, so an expense can later be settled by a payment record. */
enum PaymentMethod: string implements HasLabel
{
    use HasTranslatedLabel;

    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Card = 'card';
    case Other = 'other';
}
