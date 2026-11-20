<?php

namespace App\Filament\Support;

use App\Enums\{Currency, OfferPaymentMethod, OpportunityType, RentFrequency};
use App\Models\Opportunity;
use Closure;
use Filament\Forms;

/**
 * "Our price" section of the offer create forms. It lets an admin record the
 * first price while creating the offer, pre-filled from the unit's list price,
 * instead of opening the Price history tab and adding a revision by hand.
 */
class OfferPriceFields
{
    /**
     * @param Closure(Forms\Get): mixed $type  current deal type (sale/rent)
     * @param array{amount?: mixed, currency?: mixed} $defaults
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(Closure $type, array $defaults = [], bool $onlyOnCreate = false): array
    {
        $isSale = fn (Forms\Get $get): bool => self::type($type($get)) === OpportunityType::Sale;
        $isRent = fn (Forms\Get $get): bool => self::type($type($get)) === OpportunityType::Rent;
        $hasAmount = fn (Forms\Get $get): bool => filled($get('amount'));

        return [
            Forms\Components\Section::make(__('sales.offer.price_section'))
                ->description(__('sales.offer.price_section_help'))
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (?\Illuminate\Database\Eloquent\Model $record = null): bool => ! $onlyOnCreate || $record === null)
                ->schema([
                    Forms\Components\TextInput::make('amount')
                        ->label(__('crm.offers.fields.amount'))
                        ->helperText(__('sales.offer.amount_help'))
                        ->numeric()->minValue(0.01)->live(onBlur: true)
                        ->default($defaults['amount'] ?? null),
                    Forms\Components\Select::make('currency')
                        ->label(__('crm.fields.currency'))
                        ->options(Currency::options())
                        ->default($defaults['currency'] ?? Currency::USD->value)
                        ->native(false)
                        ->required($hasAmount),
                    Forms\Components\TextInput::make('down_payment_amount')
                        ->label(__('crm.offers.fields.down_payment_amount'))
                        ->helperText(__('sales.offer.down_payment_help'))
                        ->numeric()->minValue(0)->visible($isSale),
                    Forms\Components\Select::make('payment_method')
                        ->label(__('crm.offers.fields.payment_method'))
                        ->options(OfferPaymentMethod::options())->native(false)->visible($isSale),
                    Forms\Components\Select::make('rent_frequency')
                        ->label(__('crm.offers.fields.rent_frequency'))
                        ->helperText(__('sales.offer.rent_frequency_help'))
                        ->options(RentFrequency::options())->native(false)
                        ->visible($isRent)->required(fn (Forms\Get $get): bool => $isRent($get) && $hasAmount($get)),
                    Forms\Components\TextInput::make('lease_term_value')
                        ->label(__('crm.offers.fields.lease_term_value'))
                        ->numeric()->integer()->minValue(1)
                        ->visible($isRent)->required(fn (Forms\Get $get): bool => $isRent($get) && $hasAmount($get)),
                    Forms\Components\Select::make('lease_term_unit')
                        ->label(__('crm.offers.fields.lease_term_unit'))
                        ->options(['month' => __('crm.offers.lease_units.month'), 'year' => __('crm.offers.lease_units.year')])->native(false)
                        ->visible($isRent)->required(fn (Forms\Get $get): bool => $isRent($get) && $hasAmount($get)),
                    Forms\Components\TextInput::make('deposit_amount')
                        ->label(__('crm.offers.fields.deposit_amount'))
                        ->numeric()->minValue(0)->visible($isRent),
                    Forms\Components\Textarea::make('payment_terms')
                        ->label(__('crm.offers.fields.payment_terms'))->columnSpanFull()->visible($isSale),
                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label(__('crm.offers.fields.expires_at'))
                        ->helperText(__('sales.offer.expires_help'))
                        ->minDate(now()),
                    Forms\Components\Toggle::make('send_now')
                        ->label(__('sales.offer.send_now'))
                        ->helperText(__('sales.offer.send_now_help'))
                        ->default(true)
                        ->visible(fn (): bool => self::canSend()),
                ]),
        ];
    }

    /** Price and currency suggested from the unit's list price (sale price for a sale, rent price for a rent). */
    public static function defaultsFor(?Opportunity $opportunity): array
    {
        $unit = $opportunity?->unit;
        $currency = $opportunity?->currency ?: Currency::USD->value;
        if (! $unit) {
            return ['currency' => $currency];
        }

        [$amount, $unitCurrency] = $opportunity->type === OpportunityType::Rent
            ? [$unit->rent_price, $unit->rent_currency]
            : [$unit->sale_price, $unit->sale_currency];

        return [
            'amount' => filled($amount) && (float) $amount > 0 ? (float) $amount : null,
            'currency' => $unitCurrency ?: $currency,
        ];
    }

    public static function canSend(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('finalize_offer_revision') || $user?->can('manage_own_crm_offer'));
    }

    private static function type(mixed $value): ?OpportunityType
    {
        return $value instanceof OpportunityType ? $value : OpportunityType::tryFrom((string) $value);
    }
}
