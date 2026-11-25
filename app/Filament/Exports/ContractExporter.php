<?php

namespace App\Filament\Exports;

use App\Models\Contract;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contract export — sales/legal audit trail.
 *
 * Shows contract number, parties, amounts, status, and dates so companies
 * can audit their portfolio, reconcile with legal docs, or share with accountants.
 */
class ContractExporter extends Exporter
{
    protected static ?string $model = Contract::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'unit:id,unit_number',
            'plannedUnit:id,code',
            'property:id,name',
            'project:id,name',
        ])->orderBy('contract_number');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('contract_number')
                ->label(__('contract_export.columns.contract_number')),

            ExportColumn::make('contract_type')
                ->label(__('contract_export.columns.type'))
                ->formatStateUsing(fn (?string $state): string => $state ? __('enums.contract_type.'.$state) : ''),

            ExportColumn::make('status')
                ->label(__('contract_export.columns.status'))
                ->formatStateUsing(fn (?string $state): string => $state ? __('enums.contract_status.'.$state) : ''),

            ExportColumn::make('unit.unit_number')
                ->label(__('contract_export.columns.unit')),

            ExportColumn::make('property.name')
                ->label(__('contract_export.columns.property')),

            ExportColumn::make('project.name')
                ->label(__('contract_export.columns.project')),

            ExportColumn::make('created_at')
                ->label(__('contract_export.columns.created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d')),

            ExportColumn::make('updated_at')
                ->label(__('contract_export.columns.updated_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d'))
                ->enabledByDefault(false),

            ExportColumn::make('created_by')
                ->label(__('contract_export.columns.created_by'))
                ->enabledByDefault(false),

            ExportColumn::make('id')
                ->label(__('contract_export.columns.record_id'))
                ->enabledByDefault(false),
        ];
    }

    public function getFileName(mixed $export): string
    {
        return __('contract_export.file_name', [
            'date' => now()->format('Y-m-d'),
        ]);
    }

    public static function getCompletedNotificationBody(\Filament\Actions\Exports\Models\Export $export): string
    {
        return __('export.completed', [
            'count' => number_format($export->successful_rows),
        ]);
    }
}
