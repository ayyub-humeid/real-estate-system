<?php

namespace App\Filament\Widgets;

use App\Enums\UnitStatus;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OccupancyStats extends BaseWidget
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
        if (! auth()->check()) {
            return false;
        }

        $user = auth()->user();

        return $user->isSuperAdmin() || $user->canAny([
            'view_any_unit',
            'view_any_property',
        ]);
    }

    protected function getStats(): array
    {
        $propertyId = $this->filters['property_id'] ?? null;

        $query = Unit::query();
        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        $totalUnits = (clone $query)->count();
        $occupiedUnits = (clone $query)->whereHas('currentLease')->count();
        $vacantUnits = (clone $query)->where('status', UnitStatus::Ready->value)->whereDoesntHave('currentLease')->count();
        $maintenanceUnits = (clone $query)->where('status', UnitStatus::Maintenance->value)->count();

        $occupancyRate = $totalUnits > 0 ? ($occupiedUnits / $totalUnits) * 100 : 0;

        return [
            Stat::make(__('dashboard.kpis.occupancy_rate'), number_format($occupancyRate, 1) . '%')
                ->description(__('dashboard.descriptions.occupancy_percentage') . ' — ' . $occupiedUnits . '/' . $totalUnits)
                ->descriptionIcon('heroicon-m-home-modern')
                ->color($occupancyRate > 80 ? 'success' : ($occupancyRate > 50 ? 'warning' : 'danger')),

            Stat::make(__('dashboard.kpis.occupied_units'), $occupiedUnits)
                ->description(__('dashboard.descriptions.total_rented_units'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('dashboard.kpis.vacant_units'), $vacantUnits)
                ->description(__('dashboard.descriptions.ready_for_rent'))
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('info'),

            Stat::make(__('maintenance.widgets.maintenance_units'), $maintenanceUnits)
                ->description(__('maintenance.widgets.under_repair'))
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('warning'),
        ];
    }
}
