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

        // Only add financial/occupancy widgets if the user is an admin or manager
        if ($user && $user->hasAnyRole(['super_admin', 'company_admin', 'property_manager'])) {
            $widgets[] = FinancialOverviewStats::class;
            $widgets[] = OccupancyStats::class;
            $widgets[] = FinancialTrendChart::class;
        }

        return $widgets;
    }

    public function getColumns(): int | string | array
    {
        return 2;
    }
}
