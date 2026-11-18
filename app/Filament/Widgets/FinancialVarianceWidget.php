<?php

namespace App\Filament\Widgets;

use App\Enums\{ActualCostStatus, FinancialCommitmentStatus, ProjectBudgetStatus};
use App\Models\{ActualCost, FinancialCommitment, Project, ProjectBudget};
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FinancialVarianceWidget extends BaseWidget
{
    public ?Model $record = null;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $user = auth()->user();

        return $user->isSuperAdmin() || $user->canAny([
            'view_any_financial::commitment',
            'view_any_actual::cost',
            'view_any_project',
        ]);
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $companyId = $user?->company_id;

        if ($this->record instanceof Project) {
            return $this->projectStats($this->record);
        }

        $cacheKey = 'fin_variance_v2_' . ($isSuperAdmin ? 'all' : ($companyId ?? '0'));

        return Cache::remember($cacheKey, 300, function () use ($isSuperAdmin, $companyId) {
            return $this->globalStats($isSuperAdmin, $companyId);
        });
    }

    private function projectStats(Project $project): array
    {
        $currency = $project->currency ?? 'USD';

        $budgetTotal = (float) ProjectBudget::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('status', ProjectBudgetStatus::Approved->value)
            ->withSum('items as planned_total', 'planned_amount')
            ->get()
            ->sum('planned_total');

        $commitmentTotal = (float) FinancialCommitment::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('status', FinancialCommitmentStatus::Committed->value)
            ->sum('budget_amount');

        $actualCostTotal = (float) ActualCost::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('status', ActualCostStatus::Approved->value)
            ->sum('budget_amount');

        $variance = $budgetTotal - $commitmentTotal - $actualCostTotal;

        return [
            Stat::make(__('dashboard.kpis.approved_budget'), number_format($budgetTotal, 2) . ' ' . $currency)
                ->description(__('dashboard.descriptions.budget_planned'))
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make(__('dashboard.kpis.committed_obligations'), number_format($commitmentTotal, 2) . ' ' . $currency)
                ->description(__('dashboard.descriptions.committed_obligations'))
                ->descriptionIcon('heroicon-m-document-currency-dollar')
                ->color('warning'),

            Stat::make(__('dashboard.kpis.approved_actual_costs'), number_format($actualCostTotal, 2) . ' ' . $currency)
                ->description(__('dashboard.descriptions.approved_actual_costs'))
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('success'),

            Stat::make(__('dashboard.kpis.budget_variance'), number_format($variance, 2) . ' ' . $currency)
                ->description($variance >= 0 ? __('dashboard.kpis.under_budget') : __('dashboard.kpis.over_budget'))
                ->descriptionIcon('heroicon-m-arrow-trending-' . ($variance >= 0 ? 'down' : 'up'))
                ->color($variance >= 0 ? 'success' : 'danger'),
        ];
    }

    private function globalStats(bool $isSuperAdmin, ?int $companyId): array
    {
        $budgetQuery = ProjectBudget::withoutGlobalScopes()->where('status', ProjectBudgetStatus::Approved->value);
        $commitmentQuery = FinancialCommitment::withoutGlobalScopes()->where('status', FinancialCommitmentStatus::Committed->value);
        $actualCostQuery = ActualCost::withoutGlobalScopes()->where('status', ActualCostStatus::Approved->value);

        if (! $isSuperAdmin && $companyId) {
            $budgetQuery->where('company_id', $companyId);
            $commitmentQuery->where('company_id', $companyId);
            $actualCostQuery->where('company_id', $companyId);
        }

        $budgetTotal = (float) $budgetQuery
            ->withSum('items as planned_total', 'planned_amount')
            ->get()
            ->sum('planned_total');

        $commitmentCount = (int) (clone $commitmentQuery)->count();
        $commitmentTotal = (float) $commitmentQuery->sum('budget_amount');

        $actualCostCount = (int) (clone $actualCostQuery)->count();
        $actualCostTotal = (float) $actualCostQuery->sum('budget_amount');

        $variance = $budgetTotal - $commitmentTotal - $actualCostTotal;

        return [
            Stat::make(__('dashboard.kpis.approved_budget'), number_format($budgetTotal, 2))
                ->description(__('dashboard.descriptions.budget_planned'))
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make(
                __('dashboard.kpis.committed_obligations'),
                number_format($commitmentTotal, 2) . ' (' . $commitmentCount . ')'
            )
                ->description(__('dashboard.descriptions.committed_obligations'))
                ->descriptionIcon('heroicon-m-document-currency-dollar')
                ->color('warning'),

            Stat::make(
                __('dashboard.kpis.approved_actual_costs'),
                number_format($actualCostTotal, 2) . ' (' . $actualCostCount . ')'
            )
                ->description(__('dashboard.descriptions.approved_actual_costs'))
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('success'),

            Stat::make(__('dashboard.kpis.budget_variance'), number_format($variance, 2))
                ->description($variance >= 0 ? __('dashboard.kpis.under_budget') : __('dashboard.kpis.over_budget'))
                ->descriptionIcon('heroicon-m-arrow-trending-' . ($variance >= 0 ? 'down' : 'up'))
                ->color($variance >= 0 ? 'success' : 'danger'),
        ];
    }
}
