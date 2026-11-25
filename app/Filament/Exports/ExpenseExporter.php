<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Contracts\HasNumericExportColumns;
use App\Models\Expense;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/** Expense ledger for budget reconciliation; amounts stay numeric so Excel can total them. */
class ExpenseExporter extends CompanyDataExporter implements HasNumericExportColumns
{
    protected static ?string $model = Expense::class;

    protected static function translationFile(): string
    {
        return 'expense_export';
    }

    public static function numericColumns(): array
    {
        return ['amount'];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['property:id,name', 'unit:id,unit_number', 'creator:id,name']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('expense_date')->label(static::label('date'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d')),
            ExportColumn::make('title')->label(static::label('title')),
            ExportColumn::make('category')->label(static::label('category'))
                ->formatStateUsing(fn (?string $state): ?string => $state ? __('expense_export.categories.'.$state) : null),
            ExportColumn::make('amount')->label(static::label('amount'))
                ->formatStateUsing(fn ($state): ?float => static::number($state)),
            ExportColumn::make('currency')->label(static::label('currency')),
            ExportColumn::make('status')->label(static::label('status'))
                ->formatStateUsing(fn (?string $state): ?string => $state ? __('expense_export.statuses.'.$state) : null),
            ExportColumn::make('property.name')->label(static::label('property')),
            ExportColumn::make('unit.unit_number')->label(static::label('unit')),
            ExportColumn::make('payment_method')->label(static::label('payment_method')),
            ExportColumn::make('reference_number')->label(static::label('reference')),
            ExportColumn::make('paid_at')->label(static::label('paid_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d')),
            ExportColumn::make('description')->label(static::label('description'))->enabledByDefault(false),
            ExportColumn::make('creator.name')->label(static::label('created_by'))->enabledByDefault(false),
            ExportColumn::make('id')->label(static::label('record_id'))->enabledByDefault(false),
        ];
    }
}
