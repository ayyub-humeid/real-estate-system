<?php

namespace App\Services;

use App\Enums\ContractInstallmentKind;
use App\Enums\ContractType;
use App\Models\ContractParty;
use App\Models\ContractVersion;
use App\Support\AmountInWords;
use App\Support\RentTerms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Traits\Localizable;

/**
 * The single list of contract information a legal template may insert. The
 * editor buttons, the sample preview and the PDF all read it, so they cannot
 * drift apart.
 */
class ContractPlaceholders
{
    use Localizable;

    public const PAYMENT_SCHEDULE = '{{payment_schedule}}';

    private const ROLES = ['seller', 'buyer', 'landlord', 'tenant', 'guarantor'];

    private const PARTY_FIELDS = ['name', 'id_number', 'address', 'phone'];

    /** Inserted values follow the language the legal text is written in, not the printer's screen language. */
    public static function languageOf(?string $body): string
    {
        return preg_match('/\p{Arabic}/u', strip_tags((string) $body)) ? 'ar' : 'en';
    }

    /** A schedule typed on its own line must not stay wrapped in a paragraph, as it prints a table. */
    public static function unwrapBlocks(string $body): string
    {
        return preg_replace(
            '#<(p|div)>\s*\{\{payment_schedule\}\}\s*(?:<br\s*/?>)?\s*</\1>#u',
            self::PAYMENT_SCHEDULE,
            $body,
        ) ?? $body;
    }

    /**
     * What the editor offers for one kind of contract.
     *
     * @return array<string, array<string, string>> group label => [token => label]
     */
    public function catalogue(ContractType $type): array
    {
        $isLease = $type === ContractType::Lease;

        $contract = ['contract_number', 'contract_type', 'company_name', 'property_name', 'unit_number', 'project_name', 'start_date', 'end_date'];
        if (! $isLease) {
            $contract[] = 'delivery_date';
        }

        $money = ['total_amount', 'total_amount_in_words', 'currency', 'payment_schedule'];
        if ($isLease) {
            array_push($money, 'rent_amount', 'rent_amount_in_words', 'rent_frequency', 'payments_count');
        }

        $labelled = fn (array $keys): array => collect($keys)
            ->mapWithKeys(fn (string $key): array => [$this->token($key) => __('contract_domain.placeholders.fields.'.$key)])
            ->all();

        $groups = [
            __('contract_domain.placeholders.groups.contract') => $labelled($contract),
            __('contract_domain.placeholders.groups.money') => $labelled($money),
            __('contract_domain.placeholders.groups.parties') => $labelled(['parties']),
        ];

        foreach ($isLease ? ['landlord', 'tenant', 'guarantor'] : ['seller', 'buyer', 'guarantor'] as $role) {
            $groups[$this->roleLabel($role)] = collect(self::PARTY_FIELDS)
                ->mapWithKeys(fn (string $field): array => [
                    $this->token($role.'_'.$field) => __('contract_domain.placeholders.party_fields.'.$field, ['role' => $this->roleLabel($role)]),
                ])
                ->all();
        }

        return $groups;
    }

    /**
     * The values printed for one contract version. Anything the contract does
     * not record prints as a dash.
     *
     * @return array<string, string> token => escaped HTML
     */
    public function values(ContractVersion $version, string $body, ?array $paymentPlan): array
    {
        $locale = self::languageOf($body);

        return $this->withLocale($locale, function () use ($version, $paymentPlan, $locale): array {
            $contract = $version->contract;
            $snapshot = $version->unit_snapshot ?? [];
            $date = fn ($value): string => $value ? $value->format('Y-m-d') : '—';
            $rent = $contract->contract_type === ContractType::Lease && filled($version->rent_amount)
                ? RentTerms::summarise($version->rent_amount, $version->rent_frequency, $version->start_date, $version->end_date)
                : null;

            $plain = [
                'contract_number' => $contract->contract_number,
                'contract_type' => $contract->contract_type?->getLabel(),
                'company_name' => $contract->company?->name,
                'property_name' => self::snapshotValue($snapshot, 'property_name'),
                'unit_number' => self::snapshotValue($snapshot, 'unit_number', 'planned_unit_code'),
                'project_name' => self::snapshotValue($snapshot, 'project_name'),
                'start_date' => $date($version->start_date),
                'end_date' => $date($version->end_date),
                'delivery_date' => $date($version->delivery_date),
                'total_amount' => number_format((float) $version->total_amount, 2),
                'total_amount_in_words' => filled($version->total_amount)
                    ? AmountInWords::format($version->total_amount, $version->currency, $locale)
                    : '—',
                'currency' => $version->currency,
                'rent_amount' => $rent ? number_format((float) $version->rent_amount, 2) : '—',
                'rent_amount_in_words' => $rent ? AmountInWords::format($version->rent_amount, $version->currency, $locale) : '—',
                'rent_frequency' => ($rent['frequency'] ?? null)?->getLabel(),
                'payments_count' => $rent['periods'] ?? '—',
            ];

            foreach (self::ROLES as $role) {
                $parties = $version->parties->where('role', $role);
                foreach (self::PARTY_FIELDS as $field) {
                    $plain[$role.'_'.$field] = $parties
                        ->map(fn (ContractParty $party): string => $this->partyValue($party, $field))
                        ->filter()
                        ->implode($locale === 'ar' ? '، ' : ', ');
                }
            }

            $values = [];
            foreach ($plain as $key => $value) {
                $values[$this->token($key)] = e(filled($value) ? (string) $value : '—');
            }

            $values[$this->token('parties')] = $version->parties
                ->map(fn (ContractParty $party): string => e(($this->partyValue($party, 'name') ?: '—').' ('.$this->roleLabel($party->role).')'))
                ->implode('<br>');
            $values[self::PAYMENT_SCHEDULE] = $this->scheduleHtml($paymentPlan);
            // Terms are rendered once, in the controlled terms section below the
            // legal text. Older templates keep working without duplicate clauses.
            $values[$this->token('terms')] = '';
            $values[$this->token('contract_terms')] = '';

            return $values;
        });
    }

    /**
     * Example values for the editor preview, in the language of the text being written.
     *
     * @return array<string, string>
     */
    public function samples(string $body): array
    {
        $locale = self::languageOf($body);

        return $this->withLocale($locale, function () use ($locale): array {
            $company = __('Madar Real Estate Development');
            $customer = __('Ahmed Khalil');
            $plain = [
                'contract_number' => 'CON-2026-000001',
                'contract_type' => ContractType::Sale->getLabel(),
                'company_name' => $company,
                'property_name' => __('Madar Residence'),
                'unit_number' => 'A-204',
                'project_name' => __('Madar Residence Project'),
                'start_date' => today()->format('Y-m-d'),
                'end_date' => today()->addYear()->subDay()->format('Y-m-d'),
                'delivery_date' => today()->addMonths(6)->format('Y-m-d'),
                'total_amount' => '12,000.00',
                'total_amount_in_words' => AmountInWords::format('12000', 'USD', $locale),
                'currency' => 'USD',
                'rent_amount' => '1,000.00',
                'rent_amount_in_words' => AmountInWords::format('1000', 'USD', $locale),
                'rent_frequency' => __('crm.offers.rent_frequencies.monthly'),
                'payments_count' => '12',
            ];

            foreach (self::ROLES as $role) {
                $isCompany = in_array($role, ['seller', 'landlord'], true);
                $plain[$role.'_name'] = $isCompany ? $company : $customer;
                $plain[$role.'_id_number'] = $isCompany ? '562000000' : '900000000';
                $plain[$role.'_address'] = __('contract_domain.placeholders.sample_address');
                $plain[$role.'_phone'] = '+970 59 000 0000';
            }

            $values = [];
            foreach ($plain as $key => $value) {
                $values[$this->token($key)] = e($value);
            }

            $values[$this->token('parties')] = e($company.' ('.$this->roleLabel('seller').')').'<br>'.e($customer.' ('.$this->roleLabel('buyer').')');
            $values[self::PAYMENT_SCHEDULE] = $this->scheduleHtml([
                'issued' => true,
                'reconciles' => true,
                'currency' => 'USD',
                'total' => '12000.00',
                'installments' => collect([1, 2])->map(fn (int $sequence): object => (object) [
                    'sequence' => $sequence,
                    'kind' => ContractInstallmentKind::Installment,
                    'due_date' => Carbon::today()->addMonths($sequence - 1),
                    'amount' => '6000',
                ]),
            ]);

            return $values;
        });
    }

    /** Supports current snapshots and older snapshots that stored nested records. */
    public static function snapshotValue(array $snapshot, string ...$keys): string
    {
        foreach ($keys as $key) {
            $value = $snapshot[$key] ?? null;
            if (is_string($value) || is_numeric($value)) {
                return (string) $value;
            }
            if (is_array($value)) {
                foreach (['name', 'unit_number', 'code', 'label'] as $labelKey) {
                    if (isset($value[$labelKey]) && (is_string($value[$labelKey]) || is_numeric($value[$labelKey]))) {
                        return (string) $value[$labelKey];
                    }
                }
            }
        }

        return '—';
    }

    /** Parties are read from the snapshot taken with the version, so a later edit cannot rewrite a signed contract. */
    private function partyValue(ContractParty $party, string $field): string
    {
        $snapshot = $party->snapshot ?? [];

        return (string) match ($field) {
            'name' => $snapshot['name'] ?? $party->party?->name,
            'id_number' => ($snapshot['national_id'] ?? null) ?: ($snapshot['registration_number'] ?? null),
            default => $snapshot[$field] ?? null,
        };
    }

    private function scheduleHtml(?array $paymentPlan): string
    {
        return view('pdf.partials.payment-schedule', ['paymentPlan' => $paymentPlan])->render();
    }

    private function roleLabel(?string $role): string
    {
        return match ($role) {
            'buyer' => __('Buyer'),
            'seller' => __('Seller'),
            'landlord' => __('Landlord'),
            'tenant' => __('Tenant'),
            'guarantor' => __('Guarantor'),
            default => $role ?: '—',
        };
    }

    private function token(string $key): string
    {
        return '{{'.$key.'}}';
    }
}
