<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FinancialOverviewStats;
use App\Filament\Widgets\FinancialTrendChart;
use App\Filament\Widgets\OccupancyStats;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = -2;

    public function getWidgets(): array
    {
        $user = auth()->user();

        // Always show AccountWidget for all users
        $widgets = [AccountWidget::class];

        // Only add financial/occupancy widgets if the user has permission
        if ($user?->can('widget_FinancialOverviewStats')) {
            $widgets[] = FinancialOverviewStats::class;
        }

        if ($user?->can('widget_OccupancyStats')) {
            $widgets[] = OccupancyStats::class;
        }

        if ($user?->can('widget_FinancialTrendChart')) {
            $widgets[] = FinancialTrendChart::class;
        }

        return $widgets;
    }

    public function getColumns(): int | string | array
    {
        return 2;
    }
}
