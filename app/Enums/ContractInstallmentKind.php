<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractInstallmentKind: string implements HasLabel
{
    case DownPayment = 'down_payment';
    case Installment = 'installment';
    case Deposit = 'deposit';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return __('contract_finance.installment_kinds.'.$this->value);
    }
}
