<?php

namespace App\Filament\Widgets;

use App\Models\{ActualCost,FinancialCommitment,Payment,ProjectBudget};
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard widget showing both monetary totals and record counts.
 * Monetary totals are grouped by currency for clarity.
 */
class FinancialOverviewStats extends BaseWidget
{
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'company_admin', 'financial_manager']);
    }

    protected function getStats(): array
    {
        // Active commitments - count and total by currency
        $commitments = FinancialCommitment::query()
            ->where('status', 'committed')
            ->selectRaw('currency, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('currency')
            ->get();

        // Approved actual costs - count and total by currency
        $actualCosts = ActualCost::query()
            ->where('status', 'approved')
            ->selectRaw('currency, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('currency')
            ->get();

        // Completed outgoing payments - count and total by currency
        $payments = Payment::query()
            ->where('status', 'completed')
            ->where('direction', 'outgoing')
            ->selectRaw('currency, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('currency')
            ->get();

        // Budget baselines - count only (multi-currency per project)
        $budgetCount = ProjectBudget::query()->where('status', 'approved')->count();

        return [
            Stat::make(__('Current budget baselines'), $budgetCount)
                ->description(__('One current baseline per project'))
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make(__('Active commitments'), $commitments->sum('count'))
                ->description($this->formatCurrencyBreakdown($commitments))
                ->descriptionIcon('heroicon-m-document-currency-dollar')
                ->color('warning'),

            Stat::make(__('Approved actual costs'), $actualCosts->sum('count'))
                ->description($this->formatCurrencyBreakdown($actualCosts))
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('success'),

            Stat::make(__('Completed project payments'), $payments->sum('count'))
                ->description($this->formatCurrencyBreakdown($payments))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
        ];
    }

    private function formatCurrencyBreakdown($collection): string
    {
        return $collection->map(function ($item) {
            return "{$item->currency}: " . number_format($item->total, 2) . " ({$item->count} records)";
        })->implode('; ');
    }
}
