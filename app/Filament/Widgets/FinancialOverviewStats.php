<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

class FinancialOverviewStats extends BaseWidget
{
    public ?array $filters = [];

    protected $listeners = ['refreshWidgets'];

    public function refreshWidgets(array $filters): void
    {
        $this->filters = $filters;
    }

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()->can('widget_FinancialOverviewStats');
    }

    protected function getStats(): array
    {
        $propertyId = $this->filters['property_id'] ?? null;
        $startDate = $this->filters['start_date'] ?? null;
        $endDate = $this->filters['end_date'] ?? null;

        // Base Query for Revenue
        $revenueQuery = Payment::query();
        if ($propertyId) {
            $revenueQuery->whereHas('lease.unit', fn($q) => $q->where('property_id', $propertyId));
        }
        if ($startDate) $revenueQuery->where('payment_date', '>=', $startDate);
        if ($endDate) $revenueQuery->where('payment_date', '<=', $endDate);

        $totalRevenue = $revenueQuery->sum('paid_amount');

        // Base Query for Expenses
        $expenseQuery = Expense::where('status', 'paid');
        if ($propertyId) $expenseQuery->where('property_id', $propertyId);
        if ($startDate) $expenseQuery->where('expense_date', '>=', $startDate);
        if ($endDate) $expenseQuery->where('expense_date', '<=', $endDate);

        $totalExpenses = $expenseQuery->sum('amount');

        // Profit
        $netProfit = $totalRevenue - $totalExpenses;

        $pendingRevenue = Payment::whereIn('status', ['pending', 'partial', 'overdue'])
            ->when($propertyId, fn($q) => $q->whereHas('lease.unit', fn($uq) => $uq->where('property_id', $propertyId)))
            ->when($startDate, fn($q) => $q->where('due_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('due_date', '<=', $endDate))
            ->sum('remaining_amount');

        return [
            Stat::make('Total Revenue', number_format($totalRevenue, 2) . ' JOD')
                ->description('Actual collected amounts')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Total Expenses', number_format($totalExpenses, 2) . ' JOD')
                ->description('Paid expenses')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('danger'),
            Stat::make('Net Profit', number_format($netProfit, 2) . ' JOD')
                ->description('Revenue - Expenses')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($netProfit >= 0 ? 'success' : 'danger'),
            Stat::make('Pending Revenue', number_format($pendingRevenue, 2) . ' JOD')
                ->description('Amounts not yet collected')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
