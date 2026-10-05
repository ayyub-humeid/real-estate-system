<?php

namespace App\Filament\Widgets;

use App\Models\{ActualCost,Payment};
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Project Financial Activity (Monthly)';
    protected static string $color = 'info';
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'company_admin', 'financial_manager']);
    }

    protected function getData(): array
    {
        $months = collect(range(0, 11))->map(fn($i)=>now()->subMonths($i)->format('Y-m'))->reverse()->values();
        $driver = DB::getDriverName();
        $paymentMonth = $driver==='pgsql' ? "TO_CHAR(payment_date, 'YYYY-MM')" : "DATE_FORMAT(payment_date, '%Y-%m')";
        $costMonth = $driver==='pgsql' ? "TO_CHAR(incurred_at, 'YYYY-MM')" : "DATE_FORMAT(incurred_at, '%Y-%m')";
        $payments = Payment::query()->selectRaw("{$paymentMonth} as month, COUNT(*) as total")->where('status','completed')->where('direction','outgoing')->where('payment_date','>=',now()->subMonths(11)->startOfMonth())->groupBy('month')->pluck('total','month');
        $costs = ActualCost::query()->selectRaw("{$costMonth} as month, COUNT(*) as total")->where('status','approved')->where('incurred_at','>=',now()->subMonths(11)->startOfMonth())->groupBy('month')->pluck('total','month');
        return ['datasets'=>[['label'=>'Completed payments','data'=>$months->map(fn($month)=>(int)$payments->get($month,0))->all(),'borderColor'=>'#10b981','backgroundColor'=>'#10b98133','fill'=>true],['label'=>'Approved actual costs','data'=>$months->map(fn($month)=>(int)$costs->get($month,0))->all(),'borderColor'=>'#3b82f6','backgroundColor'=>'#3b82f633','fill'=>true]],'labels'=>$months->map(fn($month)=>Carbon::parse($month)->translatedFormat('M Y'))->all()];
    }

    protected function getType(): string { return 'line'; }
}
