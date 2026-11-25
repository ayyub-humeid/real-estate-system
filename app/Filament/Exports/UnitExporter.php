<?php

namespace App\Filament\Exports;

use App\Enums\{UnitStatus, UnitType};
use App\Filament\Exports\Contracts\HasNumericExportColumns;
use App\Models\Unit;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/** Unit inventory with prices, so sales progress can be reported and totalled in Excel. */
class UnitExporter extends CompanyDataExporter implements HasNumericExportColumns
{
    protected static ?string $model = Unit::class;

    protected static function translationFile(): string
    {
        return 'unit_export';
    }

    public static function numericColumns(): array
    {
        return ['actual_area', 'sale_price', 'rent_price'];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['property:id,name', 'project:id,name']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('unit_number')->label(static::label('unit_number')),
            ExportColumn::make('property.name')->label(static::label('property')),
            ExportColumn::make('project.name')->label(static::label('project')),
            ExportColumn::make('type')->label(static::label('type'))
                ->formatStateUsing(fn (?string $state): ?string => $state ? (UnitType::tryFrom($state)?->getLabel() ?? $state) : null),
            ExportColumn::make('status')->label(static::label('status'))
                ->formatStateUsing(fn (?string $state): ?string => $state ? (UnitStatus::tryFrom($state)?->getLabel() ?? $state) : null),
            ExportColumn::make('bedrooms')->label(static::label('bedrooms')),
            ExportColumn::make('bathrooms')->label(static::label('bathrooms')),
            ExportColumn::make('actual_area')->label(static::label('area'))
                ->formatStateUsing(fn ($state): ?float => static::number($state)),
            ExportColumn::make('area_unit')->label(static::label('area_unit')),
            ExportColumn::make('sale_price')->label(static::label('sale_price'))
                ->formatStateUsing(fn ($state): ?float => static::number($state)),
            ExportColumn::make('sale_currency')->label(static::label('sale_currency')),
            ExportColumn::make('rent_price')->label(static::label('rent_price'))
                ->formatStateUsing(fn ($state): ?float => static::number($state)),
            ExportColumn::make('rent_currency')->label(static::label('rent_currency')),
            ExportColumn::make('created_at')->label(static::label('created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d'))->enabledByDefault(false),
            ExportColumn::make('id')->label(static::label('record_id'))->enabledByDefault(false),
        ];
    }
}
