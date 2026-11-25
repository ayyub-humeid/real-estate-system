<?php

namespace App\Filament\Widgets;

use App\Enums\{ContractStatus, ContractType, MaintenanceStatus, ProjectStatus};
use App\Models\{Contract, MaintenanceRequest, Opportunity, Project, Property};
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class OperationalOverviewWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('dashboard.kpis.operational_overview');
    }

    public static function canView(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $user = auth()->user();

        return $user->isSuperAdmin() || $user->canAny([
            'view_any_project',
            'view_any_property',
            'view_any_contract',
            'view_any_maintenance::request',
        ]);
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $companyId = $user->company_id;
        $cacheKey = 'ops_overview_v2_' . ($isSuperAdmin ? 'all' : ($companyId ?? '0'));

        return Cache::remember($cacheKey, 300, function () use ($isSuperAdmin, $companyId) {
            $scope = fn ($query) => (! $isSuperAdmin && $companyId)
                ? $query->where('company_id', $companyId)
                : $query;

            $activeProjects = $scope(Project::withoutGlobalScopes()
                ->whereIn('status', ProjectStatus::activeValues()))
                ->count();

            $totalProperties = $scope(Property::withoutGlobalScopes())->count();

            $activeContracts = $scope(Contract::withoutGlobalScopes()
                ->where('status', ContractStatus::Active->value))
                ->count();

            // Leases are disabled; a rental is an active lease-type Contract.
            $activeLeases = $scope(Contract::withoutGlobalScopes()
                ->where('status', ContractStatus::Active->value)
                ->where('contract_type', ContractType::Lease->value))
                ->count();

            $pendingMaintenance = $scope(MaintenanceRequest::withoutGlobalScopes()
                ->whereIn('status', MaintenanceStatus::openValues()))
                ->count();

            $openOpportunities = $scope(Opportunity::withoutGlobalScopes()
                ->whereNull('closed_at'))
                ->count();

            return [
                Stat::make(__('dashboard.kpis.active_projects'), $activeProjects)
                    ->description(__('dashboard.descriptions.planning_in_progress'))
                    ->descriptionIcon('heroicon-m-building-office-2')
                    ->color('info')
                    ->chart($this->miniTrend($activeProjects)),

                Stat::make(__('dashboard.kpis.total_properties'), $totalProperties)
                    ->description(__('dashboard.descriptions.managed_properties'))
                    ->descriptionIcon('heroicon-m-map-pin')
                    ->color('gray'),

                Stat::make(__('dashboard.kpis.active_contracts'), $activeContracts)
                    ->description(__('dashboard.descriptions.active_contract_detail'))
                    ->descriptionIcon('heroicon-m-document-check')
                    ->color('success'),

                Stat::make(__('dashboard.kpis.active_leases'), $activeLeases)
                    ->description(__('dashboard.descriptions.active_lease_detail'))
                    ->descriptionIcon('heroicon-m-key')
                    ->color('primary'),

                Stat::make(__('dashboard.kpis.pending_maintenance'), $pendingMaintenance)
                    ->description(__('dashboard.descriptions.pending_maintenance_detail'))
                    ->descriptionIcon('heroicon-m-wrench-screwdriver')
                    ->color($pendingMaintenance > 10 ? 'danger' : ($pendingMaintenance > 3 ? 'warning' : 'success')),

                Stat::make(__('dashboard.kpis.open_opportunities'), $openOpportunities)
                    ->description(__('dashboard.descriptions.open_opportunities_detail'))
                    ->descriptionIcon('heroicon-m-sparkles')
                    ->color('info'),
            ];
        });
    }

    private function miniTrend(int $current): array
    {
        return [max(1, $current - 2), max(1, $current - 1), $current];
    }
}
