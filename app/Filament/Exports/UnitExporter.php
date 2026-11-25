<?php

namespace App\Filament\Exports;

use App\Models\Unit;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Unit export — inventory and sales tracking.
 *
 * Shows unit inventory: number, type, status, property, area, and reservations
 * so companies can track sales progress and report to investors.
 */
class UnitExporter extends Exporter
{
    protected static ?string $model = Unit::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'property:id,name',
            'project:id,name',
            'reservations:id,unit_id,reservation_number,status',
        ])->orderBy('unit_number');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('unit_number')
                ->label(__('unit_export.columns.unit_number'))
                ->searchable(),

            ExportColumn::make('property.name')
                ->label(__('unit_export.columns.property')),

            ExportColumn::make('project.name')
                ->label(__('unit_export.columns.project')),

            ExportColumn::make('status')
                ->label(__('unit_export.columns.status'))
                ->formatStateUsing(fn (?string $state): string => $state ? __('enums.unit_status.'.$state) : ''),

            ExportColumn::make('unit_type')
                ->label(__('unit_export.columns.type'))
                ->formatStateUsing(fn (?string $state): string => $state ? __('enums.unit_type.'.$state) : ''),

            ExportColumn::make('area_sqm')
                ->label(__('unit_export.columns.area'))
                ->formatStateUsing(fn ($state): ?string => $state ? number_format($state, 2).' sqm' : null),

            ExportColumn::make('bedrooms')
                ->label(__('unit_export.columns.bedrooms'))
                ->enabledByDefault(false),

            ExportColumn::make('bathrooms')
                ->label(__('unit_export.columns.bathrooms'))
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label(__('unit_export.columns.created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d'))
                ->enabledByDefault(false),

            ExportColumn::make('id')
                ->label(__('unit_export.columns.record_id'))
                ->enabledByDefault(false),
        ];
    }

    public function getFileName(mixed $export): string
    {
        return __('unit_export.file_name', [
            'date' => now()->format('Y-m-d'),
        ]);
    }
}
