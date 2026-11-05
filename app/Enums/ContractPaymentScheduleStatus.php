<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractPaymentScheduleStatus: string implements HasLabel
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Issued = 'issued';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return __('contract_finance.schedule_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'warning',
            self::Issued => 'success',
            self::Superseded => 'info',
            self::Cancelled => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->getLabel()])->all();
    }
}
