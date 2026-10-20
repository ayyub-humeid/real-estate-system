<?php

namespace App\Filament\Pages;

use App\Models\Company;
use Filament\Pages\Page;

class CompanySubscriptionOverview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = '🏢 Core';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return filled(static::$navigationGroup) ? __(static::$navigationGroup) : null;
    }

    public static function getNavigationLabel(): string
    {
        return __('Subscription Overview');
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return __('Company Subscription');
    }

    protected static string $view = 'filament.pages.company-subscription-overview';

    public ?Company $company = null;

    public array $companyOverview = [];

    public array $usageAnalysis = [];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        $companyId = auth()->user()?->company_id;

        if (! $companyId) {
            $this->companyOverview = $this->emptyOverview();

            return;
        }

        $this->company = Company::query()
            ->select(['id', 'name'])
            ->withCount([
                'users',
                'employees',
                'properties',
                'units',
            ])
            ->with([
                'subscription' => fn ($query) => $query
                    ->select([
                        'subscriptions.id',
                        'subscriptions.company_id',
                        'subscriptions.plan_id',
                        'subscriptions.status',
                        'subscriptions.ends_at',
                    ])
                    ->with([
                        'plan:id,name,price,features',
                    ]),
            ])
            ->find($companyId);

        $this->companyOverview = $this->buildOverview();
        $this->usageAnalysis = $this->buildUsageAnalysis();
    }

    protected function getViewData(): array
    {
        return [
            'overview' => $this->companyOverview,
            'usageAnalysis' => $this->usageAnalysis,
        ];
    }

    protected function buildOverview(): array
    {
        if (! $this->company) {
            return $this->emptyOverview();
        }

        $subscription = $this->company->subscription;
        $plan = $subscription?->plan;

        return [
            'company_name' => $this->company->name,
            'plan_name' => $plan?->name ?? __('No active plan'),
            'plan_price' => $plan ? '$' . number_format((float) $plan->price, 2) : __('N/A'),
            'plan_limits' => $this->formatLimits($plan?->features),
            'subscription_status' => $subscription?->status ? __($subscription->status) : __('No subscription'),
            'expiry_date' => $subscription?->ends_at?->toFormattedDateString() ?? __('N/A'),
        ];
    }

    protected function formatLimits(?array $features): string
    {
        if (empty($features)) {
            return __('N/A');
        }

        $limits = [];

        foreach ($features as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'max_')) {
                continue;
            }

            $label = __(ucfirst(str_replace('_', ' ', str_replace('max_', '', $key))));
            $limits[] = sprintf('%s: %s', $label, $value ?? __('Unlimited'));
        }

        return empty($limits) ? __('N/A') : implode(' | ', $limits);
    }

    protected function emptyOverview(): array
    {
        return [
            'company_name' => __('N/A'),
            'plan_name' => __('No active plan'),
            'plan_price' => __('N/A'),
            'plan_limits' => __('N/A'),
            'subscription_status' => __('No subscription'),
            'expiry_date' => __('N/A'),
        ];
    }

    protected function buildUsageAnalysis(): array
    {
        if (! $this->company) {
            return [];
        }

        $features = $this->company->subscription?->plan?->features ?? [];

        $usageMap = [
            'max_users' => [
                'label' => __('Users'),
                'used' => (int) ($this->company->users_count ?? 0),
            ],
            'max_employees' => [
                'label' => __('Employees'),
                'used' => (int) ($this->company->employees_count ?? 0),
            ],
            'max_properties' => [
                'label' => __('Properties'),
                'used' => (int) ($this->company->properties_count ?? 0),
            ],
            'max_units' => [
                'label' => __('Units'),
                'used' => (int) ($this->company->units_count ?? 0),
            ],
        ];

        $analysis = [];

        foreach ($usageMap as $featureKey => $item) {
            $rawLimit = $features[$featureKey] ?? null;
            $max = is_numeric($rawLimit) ? (int) $rawLimit : null;
            $used = $item['used'];
            $remaining = $max === null ? null : max($max - $used, 0);
            $usagePercent = $max && $max > 0
                ? min((int) round(($used / $max) * 100), 100)
                : null;

            $analysis[] = [
                'label' => $item['label'],
                'used' => $used,
                'max' => $max,
                'remaining' => $remaining,
                'usage_percent' => $usagePercent,
                'status' => $this->resolveUsageStatus($max, $remaining, $usagePercent),
                'status_level' => $this->resolveStatusLevel($max, $remaining, $usagePercent),
            ];
        }

        return $analysis;
    }

    protected function resolveUsageStatus(?int $max, ?int $remaining, ?int $usagePercent): string
    {
        if ($max === null) {
            return __('Unlimited');
        }

        if ($remaining === 0) {
            return __('Limit reached');
        }

        if (($usagePercent ?? 0) >= 80) {
            return __('Near limit');
        }

        return __('Healthy');
    }

    protected function resolveStatusLevel(?int $max, ?int $remaining, ?int $usagePercent): string
    {
        if ($max === null) {
            return 'success';
        }

        if ($remaining === 0) {
            return 'danger';
        }

        if (($usagePercent ?? 0) >= 80) {
            return 'warning';
        }

        return 'success';
    }
}
