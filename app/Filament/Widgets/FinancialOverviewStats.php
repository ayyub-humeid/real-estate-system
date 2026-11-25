<?php

namespace App\Filament\Widgets;

use App\Enums\{ActualCostStatus, FinancialCommitmentStatus, PaymentStatus, ProjectBudgetStatus};
use App\Models\{ActualCost, FinancialCommitment, Payment, ProjectBudget};
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialOverviewStats extends BaseWidget
{
    protected static bool $isLazy = false;

    public function getHeading(): ?string
    {
        return __('dashboard.kpis.financial_overview');
    }

    public static function canView(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $user = auth()->user();

        return $user->isSuperAdmin() || $user->canAny([
            'view_any_financial::commitment',
            'view_any_actual::cost',
            'view_any_payment',
        ]);
    }

    protected function getStats(): array
    {
        $commitments = FinancialCommitment::query()
            ->where('status', FinancialCommitmentStatus::Committed->value)
            ->selectRaw('currency, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('currency')
            ->get();

        $actualCosts = ActualCost::query()
            ->where('status', ActualCostStatus::Approved->value)
            ->selectRaw('currency, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('currency')
            ->get();

        $payments = Payment::query()
            ->where('status', PaymentStatus::Completed->value)
            ->where('direction', 'outgoing')
            ->selectRaw('currency, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('currency')
            ->get();

        $budgetCount = ProjectBudget::query()->where('status', ProjectBudgetStatus::Approved->value)->count();

        return [
            Stat::make(__('dashboard.kpis.current_budget_baselines'), $budgetCount)
                ->description(__('dashboard.descriptions.one_baseline_per_project'))
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make(
                __('dashboard.kpis.active_commitments'),
                $commitments->sum('cnt') . ' — ' . $this->formatTotals($commitments)
            )
                ->description(__('dashboard.descriptions.committed_obligations'))
                ->descriptionIcon('heroicon-m-document-currency-dollar')
                ->color('warning'),

            Stat::make(
                __('dashboard.kpis.approved_actual_costs'),
                $actualCosts->sum('cnt') . ' — ' . $this->formatTotals($actualCosts)
            )
                ->description(__('dashboard.descriptions.approved_actual_costs'))
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('success'),

            Stat::make(
                __('dashboard.kpis.completed_project_payments'),
                $payments->sum('cnt') . ' — ' . $this->formatTotals($payments)
            )
                ->description(__('dashboard.descriptions.completed_payments_detail'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
        ];
    }

    private function formatTotals($collection): string
    {
        if ($collection->isEmpty()) {
            return __('dashboard.descriptions.no_active_records');
        }

        return $collection->map(fn ($row) => number_format($row->total, 2) . ' ' . $row->currency)->implode(' | ');
    }
}
