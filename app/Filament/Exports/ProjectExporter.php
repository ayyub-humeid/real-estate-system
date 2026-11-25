<?php

namespace App\Filament\Exports;

use App\Enums\{ActualCostStatus, ContractStatus, FinancialCommitmentStatus, ProjectBudgetStatus, UnitStatus};
use App\Filament\Exports\Contracts\HasNumericExportColumns;
use App\Models\Project;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Project analysis: for each project, what it is, how far sales have got, what the team and
 * its demand look like, and how the money stands. The money figures use the dashboard's
 * definitions (approved budget, committed obligations, approved actual costs) so a file and
 * the screen never disagree. Amounts are in the project's own currency.
 */
class ProjectExporter extends CompanyDataExporter implements HasNumericExportColumns
{
    protected static ?string $model = Project::class;

    protected static function exportPermission(): ?string
    {
        return 'export_project';
    }

    protected static function translationFile(): string
    {
        return 'project_export';
    }

    public static function numericColumns(): array
    {
        return [
            'units_count', 'sold_units_count', 'reserved_units_count', 'available_units_count',
            'contracts_count', 'active_contracts_count', 'opportunities_count',
            'active_project_properties_count', 'buildings_count', 'members_count', 'design_packages_count', 'constructions_count',
            'approved_budget', 'committed_amount', 'approved_actual_costs', 'budget_variance',
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($query->getModel()->getTable().'.*');
        }

        return $query
            ->with(['approvedBy:id,name', 'activeProjectProperties.property:id,name'])
            ->withCount([
                'units', 'activeProjectProperties', 'buildings', 'designPackages', 'constructions', 'opportunities', 'contracts',
                'activeMembers as members_count',
                'units as sold_units_count' => fn (Builder $q) => $q->where('status', UnitStatus::Sold->value),
                'units as reserved_units_count' => fn (Builder $q) => $q->where('status', UnitStatus::Reserved->value),
                'units as available_units_count' => fn (Builder $q) => $q->where('status', UnitStatus::Ready->value),
                'contracts as active_contracts_count' => fn (Builder $q) => $q->where('status', ContractStatus::Active->value),
            ])
            ->withSum(['commitments as committed_amount' => fn (Builder $q) => $q->where('status', FinancialCommitmentStatus::Committed->value)], 'budget_amount')
            ->withSum(['actualCosts as approved_actual_costs' => fn (Builder $q) => $q->where('status', ActualCostStatus::Approved->value)], 'budget_amount')
            ->addSelect(['approved_budget' => DB::table('budget_items')
                ->selectRaw('coalesce(sum(budget_items.planned_amount), 0)')
                ->join('budget_categories', 'budget_categories.id', '=', 'budget_items.budget_category_id')
                ->join('project_budgets', 'project_budgets.id', '=', 'budget_categories.project_budget_id')
                ->whereColumn('project_budgets.project_id', 'projects.id')
                ->where('project_budgets.status', ProjectBudgetStatus::Approved->value)]);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')->label(static::label('name')),
            ExportColumn::make('project_type')->label(static::label('type'))
                ->formatStateUsing(fn (?string $state): ?string => $state ? __('project.project_types.'.$state) : null),
            ExportColumn::make('status')->label(static::label('status'))
                ->formatStateUsing(fn (?string $state): ?string => $state ? __('project.statuses.'.$state) : null),
            ExportColumn::make('currency')->label(static::label('currency')),
            ExportColumn::make('start_date')->label(static::label('start_date'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d')),
            ExportColumn::make('expected_completion_date')->label(static::label('expected_completion'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d')),

            // Inventory and sales.
            ExportColumn::make('units_count')->label(static::label('units_count')),
            ExportColumn::make('sold_units_count')->label(static::label('sold_units_count')),
            ExportColumn::make('reserved_units_count')->label(static::label('reserved_units_count')),
            ExportColumn::make('available_units_count')->label(static::label('available_units_count')),
            ExportColumn::make('contracts_count')->label(static::label('contracts_count')),
            ExportColumn::make('active_contracts_count')->label(static::label('active_contracts_count')),
            ExportColumn::make('opportunities_count')->label(static::label('opportunities_count')),

            // Structure and team.
            ExportColumn::make('active_project_properties_count')->label(static::label('properties_count')),
            ExportColumn::make('buildings_count')->label(static::label('buildings_count')),
            ExportColumn::make('members_count')->label(static::label('members_count')),
            ExportColumn::make('design_packages_count')->label(static::label('design_packages_count')),
            ExportColumn::make('constructions_count')->label(static::label('constructions_count')),

            // Money, in the project's currency.
            ExportColumn::make('approved_budget')->label(static::label('approved_budget'))
                ->formatStateUsing(fn ($state): float => (float) $state),
            ExportColumn::make('committed_amount')->label(static::label('committed_amount'))
                ->formatStateUsing(fn ($state): float => (float) $state),
            ExportColumn::make('approved_actual_costs')->label(static::label('approved_actual_costs'))
                ->formatStateUsing(fn ($state): float => (float) $state),
            ExportColumn::make('budget_variance')->label(static::label('budget_variance'))
                ->state(fn (Project $record): float => round((float) $record->approved_budget - (float) $record->committed_amount - (float) $record->approved_actual_costs, 4)),

            ExportColumn::make('property_names')->label(static::label('property_names'))
                ->state(fn (Project $record): ?string => $record->activeProjectProperties->pluck('property.name')->filter()->implode(', ') ?: null)
                ->enabledByDefault(false),
            ExportColumn::make('approvedBy.name')->label(static::label('approved_by'))->enabledByDefault(false),
            ExportColumn::make('description')->label(static::label('description'))->enabledByDefault(false),
            ExportColumn::make('created_at')->label(static::label('created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d'))->enabledByDefault(false),
            ExportColumn::make('id')->label(static::label('record_id'))->enabledByDefault(false),
        ];
    }
}
