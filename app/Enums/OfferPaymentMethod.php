<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OfferPaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Installments = 'installments';
    case MortgageFinancing = 'mortgage_financing';
    case Cheque = 'cheque';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return __('crm.offers.payment_methods.' . $this->value);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $method) => [$method->value => $method->getLabel()])
            ->all();
    }
}
