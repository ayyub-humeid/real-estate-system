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
        if (! auth()->check()) {
            return false;
        }

        return auth()->user()->hasAnyRole(['super_admin', 'company_admin', 'financial_manager', 'project_manager']);
    }

    protected function getStats(): array
    {
        return Cache::remember('financial_variance_widget', 300, function () {
            $user = auth()->user();
            $isSuperAdmin = $user && $user->isSuperAdmin();
            $companyId = $user?->company_id;

            $budgetQuery = ProjectBudget::withoutGlobalScopes()->where('status', 'approved');
            $commitmentQuery = FinancialCommitment::withoutGlobalScopes()->where('status', 'committed');
            $actualCostQuery = ActualCost::withoutGlobalScopes()->where('status', 'approved');

            if (! $isSuperAdmin && $companyId) {
                $budgetQuery->where('company_id', $companyId);
                $commitmentQuery->where('company_id', $companyId);
                $actualCostQuery->where('company_id', $companyId);
            }

            $budgetTotal = $budgetQuery
                ->withSum('items as planned_total', 'planned_amount')
                ->get()
                ->sum('planned_total');

            $commitmentTotal = $commitmentQuery->sum('budget_amount');

            $actualCostTotal = $actualCostQuery->sum('budget_amount');

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
                    ->descriptionIcon('heroicon-m-arrow-trending-' . ($variance >= 0 ? 'down' : 'up'))
                    ->color($variance >= 0 ? 'success' : 'danger'),
            ];
        });
    }
}
