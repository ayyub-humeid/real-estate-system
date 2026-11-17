<?php

namespace App\Filament\Widgets;

use App\Models\{ActualCost, FinancialCommitment, ProjectBudget};
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class FinancialVarianceWidget extends BaseWidget
{
    protected static bool $isLazy = true;

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'company_admin', 'financial_manager', 'project_manager']);
    }

    protected function getStats(): array
    {
        return Cache::remember('financial_variance_widget', 300, function () {
            $budgetTotal = ProjectBudget::withoutGlobalScopes()
                ->where('status', 'approved')
                ->withSum('items as planned_total', 'planned_amount')
                ->get()
                ->sum('planned_total');

            $commitmentTotal = FinancialCommitment::withoutGlobalScopes()
                ->where('status', 'committed')
                ->sum('budget_amount');

            $actualCostTotal = ActualCost::withoutGlobalScopes()
                ->where('status', 'approved')
                ->sum('budget_amount');

            $variance = (float) $budgetTotal - (float) $commitmentTotal - (float) $actualCostTotal;

            return [
                Stat::make(__('Approved budget'), number_format($budgetTotal, 2))
                    ->description(__('Planned amount from approved budget versions'))
                    ->descriptionIcon('heroicon-m-calculator')
                    ->color('info'),

                Stat::make(__('Committed obligations'), number_format($commitmentTotal, 2))
                    ->description(__('Committed financial obligations against budget'))
                    ->descriptionIcon('heroicon-m-document-currency-dollar')
                    ->color('warning'),

                Stat::make(__('Approved actual costs'), number_format($actualCostTotal, 2))
                    ->description(__('Approved actual costs incurred'))
                    ->descriptionIcon('heroicon-m-receipt-percent')
                    ->color('success'),

                Stat::make(__('Budget variance'), number_format($variance, 2))
                    ->description($variance >= 0 ? __('Under budget') : __('Over budget'))
                    ->descriptionIcon('heroicon-m-trending-'.($variance >= 0 ? 'down' : 'up'))
                    ->color($variance >= 0 ? 'success' : 'danger'),
            ];
        });
    }
}
