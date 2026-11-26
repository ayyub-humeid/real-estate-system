<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FinancialTrendChart;
use App\Filament\Widgets\FinancialVarianceWidget;
use App\Filament\Widgets\OccupancyStats;
use App\Filament\Widgets\OperationalOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -2;

    public static function getNavigationLabel(): string
    {
        return __('dashboard.nav_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('dashboard.title');
    }

    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            \App\Filament\Widgets\ContractTemplateSetupWidget::class,
            OperationalOverviewWidget::class,
            FinancialVarianceWidget::class,
            OccupancyStats::class,
            FinancialTrendChart::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
