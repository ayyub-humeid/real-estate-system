<?php

namespace App\Filament\Widgets;

use App\Models\{Lease, Unit};
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

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
        return auth()->user()->hasAnyRole(['super_admin', 'company_admin', 'property_manager']);
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
        $vacantUnits = (clone $query)->where('status', 'ready')->whereDoesntHave('currentLease')->count();
        $maintenanceUnits = (clone $query)->where('status', 'maintenance')->count();

        $occupancyRate = $totalUnits > 0 ? ($occupiedUnits / $totalUnits) * 100 : 0;

        return [
            Stat::make(__('Occupancy Rate'), number_format($occupancyRate, 1) . '%')
                ->description(__('Percentage of occupied units'))
                ->descriptionIcon('heroicon-m-home-modern')
                ->color($occupancyRate > 80 ? 'success' : ($occupancyRate > 50 ? 'warning' : 'danger')),
            Stat::make(__('Occupied Units'), $occupiedUnits)
                ->description(__('Total rented units'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make(__('Vacant Units'), $vacantUnits)
                ->description(__('Ready for rent'))
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('info'),
            Stat::make(__('Maintenance'), $maintenanceUnits)
                ->description(__('Units under repair'))
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('warning'),
        ];
    }
}
