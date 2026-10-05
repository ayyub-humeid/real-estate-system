<?php

namespace App\Filament\Widgets;

use App\Models\{ActualCost,FinancialCommitment,Payment,ProjectBudget};
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Cross-project dashboard deliberately exposes counts, not currency totals.
 * Monetary totals belong to one Project's accounting currency and are shown in
 * that Project's financial relation managers.
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
        return [
            Stat::make('Current budget baselines', ProjectBudget::query()->where('status','approved')->count())
                ->description('One current baseline per project')->descriptionIcon('heroicon-m-calculator')->color('info'),
            Stat::make('Active commitments', FinancialCommitment::query()->where('status','committed')->count())
                ->description('Commercial obligations, not cash')->descriptionIcon('heroicon-m-document-currency-dollar')->color('warning'),
            Stat::make('Approved actual costs', ActualCost::query()->where('status','approved')->count())
                ->description('Recognized supplier costs')->descriptionIcon('heroicon-m-receipt-percent')->color('success'),
            Stat::make('Completed project payments', Payment::query()->where('status','completed')->where('direction','outgoing')->count())
                ->description('Cash movements; totals are per project currency')->descriptionIcon('heroicon-m-banknotes')->color('primary'),
        ];
    }
}
