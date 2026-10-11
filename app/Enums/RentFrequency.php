<?php

namespace App\Enums;

/** Matches the existing Lease payment_frequency business values. */
enum RentFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnually = 'semi_annually';
    case Yearly = 'yearly';

    public static function options(): array
    {
        return [
            self::Monthly->value => 'Monthly',
            self::Quarterly->value => 'Quarterly',
            self::SemiAnnually->value => 'Semi-annually',
            self::Yearly->value => 'Yearly',
        ];
    }
}
