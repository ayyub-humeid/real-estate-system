<?php

namespace App\Filament\Widgets;

use App\Enums\{ActualCostStatus, PaymentStatus};
use App\Models\{ActualCost, Payment};
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialTrendChart extends ChartWidget
{
    protected static string $color = 'info';
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('dashboard.kpis.financial_activity_monthly');
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

    protected function getData(): array
    {
        $months = collect(range(0, 11))
            ->map(fn ($i) => now()->subMonths($i)->format('Y-m'))
            ->reverse()
            ->values();

        $driver = DB::getDriverName();
        $paymentMonth = $driver === 'pgsql'
            ? "TO_CHAR(payment_date, 'YYYY-MM')"
            : "DATE_FORMAT(payment_date, '%Y-%m')";
        $costMonth = $driver === 'pgsql'
            ? "TO_CHAR(incurred_at, 'YYYY-MM')"
            : "DATE_FORMAT(incurred_at, '%Y-%m')";

        $cutoff = now()->subMonths(11)->startOfMonth();

        $payments = Payment::query()
            ->selectRaw("{$paymentMonth} as month, COUNT(*) as total")
            ->where('status', PaymentStatus::Completed->value)
            ->where('direction', 'outgoing')
            ->where('payment_date', '>=', $cutoff)
            ->groupBy('month')
            ->pluck('total', 'month');

        $costs = ActualCost::query()
            ->selectRaw("{$costMonth} as month, COUNT(*) as total")
            ->where('status', ActualCostStatus::Approved->value)
            ->where('incurred_at', '>=', $cutoff)
            ->groupBy('month')
            ->pluck('total', 'month');

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.kpis.completed_project_payments'),
                    'data' => $months->map(fn ($m) => (int) $payments->get($m, 0))->all(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b98133',
                    'fill' => true,
                ],
                [
                    'label' => __('dashboard.kpis.approved_actual_costs'),
                    'data' => $months->map(fn ($m) => (int) $costs->get($m, 0))->all(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => '#3b82f633',
                    'fill' => true,
                ],
            ],
            'labels' => $months->map(fn ($m) => Carbon::parse($m)->translatedFormat('M Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
