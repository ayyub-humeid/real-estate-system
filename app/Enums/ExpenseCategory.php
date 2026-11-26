<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Values are stored in expenses.category: add cases freely, never rename existing ones. */
enum ExpenseCategory: string implements HasLabel, HasColor
{
    use HasTranslatedLabel;

    case Salaries = 'salaries';
    case Commissions = 'commissions';
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Maintenance = 'maintenance';
    case Cleaning = 'cleaning';
    case Security = 'security';
    case Insurance = 'insurance';
    case Taxes = 'taxes';
    case GovernmentFees = 'government_fees';
    case ProfessionalServices = 'professional_services';
    case Marketing = 'marketing';
    case OfficeSupplies = 'office_supplies';
    case Software = 'software';
    case Transportation = 'transportation';
    case BankCharges = 'bank_charges';
    case Other = 'other';

    public function getColor(): string
    {
        return match ($this) {
            self::Salaries, self::Commissions => 'primary',
            self::Maintenance, self::Cleaning, self::Security => 'warning',
            self::Utilities, self::Rent, self::Software => 'info',
            self::Insurance => 'success',
            self::Taxes, self::GovernmentFees, self::BankCharges => 'danger',
            default => 'gray',
        };
    }

    /** A salary is paid to a known employee for a known month, which also blocks recording it twice. */
    public function requiresEmployee(): bool
    {
        return $this === self::Salaries;
    }
}
