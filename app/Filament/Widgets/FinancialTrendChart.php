<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Expense;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

class FinancialTrendChart extends ChartWidget
{
    public ?array $filters = [];

    protected $listeners = ['refreshWidgets'];

    public function refreshWidgets(array $filters): void
    {
        $this->filters = $filters;
        $this->getData(); // Force re-calculation
    }

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()->can('widget_FinancialTrendChart');
    }

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Revenue vs Expenses (Monthly)';
    protected static string $color = 'info';

    protected function getData(): array
    {
        $propertyId = $this->filters['property_id'] ?? null;

        $months = collect(range(0, 11))->map(function ($i) {
            return now()->subMonths($i)->format('Y-m');
        })->reverse()->values();

        // Optimized: Get all monthly revenue in one query
        $revenueQuery = Payment::select(
                DB::raw("DATE_FORMAT(payment_date, '%Y-%m') as month"),
                DB::raw("SUM(paid_amount) as total")
            )
            ->where('payment_date', '>=', now()->subMonths(11)->startOfMonth());
        
        if ($propertyId) {
            $revenueQuery->whereHas('lease.unit', fn($q) => $q->where('property_id', $propertyId));
        }

        $revenueMonthly = $revenueQuery->groupBy('month')->pluck('total', 'month');

        // Optimized: Get all monthly expenses in one query
        $expensesQuery = Expense::select(
                DB::raw("DATE_FORMAT(expense_date, '%Y-%m') as month"),
                DB::raw("SUM(amount) as total")
            )
            ->where('status', 'paid')
            ->where('expense_date', '>=', now()->subMonths(11)->startOfMonth());

        if ($propertyId) {
            $expensesQuery->where('property_id', $propertyId);
        }

        $expensesMonthly = $expensesQuery->groupBy('month')->pluck('total', 'month');

        $revenueData = $months->map(fn($month) => $revenueMonthly->get($month, 0));
        $expenseData = $months->map(fn($month) => $expensesMonthly->get($month, 0));

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $revenueData->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b98133',
                    'fill' => true,
                ],
                [
                    'label' => 'Expenses',
                    'data' => $expenseData->toArray(),
                    'borderColor' => '#ef4444',
                    'backgroundColor' => '#ef444433',
                    'fill' => true,
                ],
            ],
            'labels' => $months->map(fn($month) => Carbon::parse($month)->translatedFormat('M Y'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
