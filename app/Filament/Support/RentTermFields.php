<?php

namespace App\Filament\Support;

use App\Enums\ContractType;
use App\Enums\RentFrequency;
use App\Support\RentTerms;
use Closure;
use Filament\Forms;

/**
 * The rent terms of a lease contract, shared by the contract form and the
 * version draft form.
 *
 * A rental is agreed per period ("500 a month for a year"), so the total the
 * contract is signed for is calculated from the rent and the term. These fields
 * keep the figure visible while the form is open; ContractDraftService
 * recalculates it on save, so it is right whatever the request looks like.
 */
final class RentTermFields
{
    /** True when the form being filled in describes a rental. */
    public static function isLeaseForm(Forms\Get $get): bool
    {
        return ContractType::tryFrom((string) $get('contract_type')) === ContractType::Lease;
    }

    /**
     * @param  Closure(Forms\Get): bool  $isLease  how this form knows it is a rental
     * @return array<int, Forms\Components\Component>
     */
    public static function make(Closure $isLease): array
    {
        $visible = fn (Forms\Get $get): bool => (bool) $isLease($get);

        return [
            Forms\Components\TextInput::make('rent_amount')
                ->label(__('contract_domain.rent.amount'))
                ->numeric()
                ->minValue(0.01)
                ->visible($visible)
                ->required($visible)
                ->live(onBlur: true)
                ->afterStateUpdated(self::recalculate($isLease))
                ->helperText(__('contract_domain.rent.amount_help')),

            Forms\Components\Select::make('rent_frequency')
                ->label(__('contract_domain.rent.frequency'))
                ->options(RentFrequency::options())
                ->default(RentFrequency::Monthly->value)
                ->native(false)
                ->visible($visible)
                ->required($visible)
                ->live()
                ->afterStateUpdated(self::recalculate($isLease)),

            Forms\Components\Placeholder::make('rent_summary')
                ->label(__('contract_domain.rent.summary'))
                ->visible($visible)
                ->content(fn (Forms\Get $get): string => self::summary($get)),
        ];
    }

    /**
     * Writes the derived total back into the form, so the person sees the figure
     * they are signing for before they save.
     *
     * @param  Closure(Forms\Get): bool  $isLease
     */
    public static function recalculate(Closure $isLease): Closure
    {
        return function (Forms\Get $get, Forms\Set $set) use ($isLease): void {
            if (! $isLease($get)) {
                return;
            }

            $set('total_amount', RentTerms::summarise(
                $get('rent_amount'),
                $get('rent_frequency'),
                $get('start_date'),
                $get('end_date'),
            )['total']);
        };
    }

    /** One line explaining the total, or what is still missing. */
    private static function summary(Forms\Get $get): string
    {
        $terms = RentTerms::summarise($get('rent_amount'), $get('rent_frequency'), $get('start_date'), $get('end_date'));

        if (! $terms['frequency'] || blank($get('rent_amount'))) {
            return __('contract_domain.rent.summary_incomplete');
        }

        if (! $terms['periods']) {
            return __('contract_domain.rent.summary_term_mismatch', ['frequency' => $terms['frequency']->getLabel()]);
        }

        return __('contract_domain.rent.summary_total', [
            'count' => $terms['periods'],
            'total' => number_format((float) $terms['total'], 2),
            'currency' => (string) ($get('currency') ?: ''),
        ]);
    }
}
