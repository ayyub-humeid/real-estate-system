<?php

namespace App\Filament\Exports;

use App\Models\Expense;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Expense export — budget tracking and reconciliation.
 *
 * Shows expenses: amount, category, project, date, vendor, and status
 * so finance teams can reconcile budgets and analyze spending.
 */
class ExpenseExporter extends Exporter
{
    protected static ?string $model = Expense::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'project:id,name',
            'createdBy:id,name',
        ])->orderBy('expense_date', 'desc');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('expense_date')
                ->label(__('expense_export.columns.date'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d')),

            ExportColumn::make('category')
                ->label(__('expense_export.columns.category'))
                ->formatStateUsing(fn (?string $state): string => $state ? __('enums.expense_category.'.$state) : ''),

            ExportColumn::make('description')
                ->label(__('expense_export.columns.description')),

            ExportColumn::make('vendor')
                ->label(__('expense_export.columns.vendor')),

            ExportColumn::make('amount')
                ->label(__('expense_export.columns.amount'))
                ->formatStateUsing(fn ($state): ?float => $state !== null ? (float) $state : null),

            ExportColumn::make('currency')
                ->label(__('expense_export.columns.currency')),

            ExportColumn::make('project.name')
                ->label(__('expense_export.columns.project')),

            ExportColumn::make('status')
                ->label(__('expense_export.columns.status'))
                ->formatStateUsing(fn (?string $state): string => $state ? __('enums.expense_status.'.$state) : ''),

            ExportColumn::make('payment_method')
                ->label(__('expense_export.columns.payment_method'))
                ->enabledByDefault(false),

            ExportColumn::make('reference')
                ->label(__('expense_export.columns.reference'))
                ->enabledByDefault(false),

            ExportColumn::make('createdBy.name')
                ->label(__('expense_export.columns.created_by'))
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label(__('expense_export.columns.created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d'))
                ->enabledByDefault(false),

            ExportColumn::make('id')
                ->label(__('expense_export.columns.record_id'))
                ->enabledByDefault(false),
        ];
    }

    public function getFileName(mixed $export): string
    {
        return __('expense_export.file_name', [
            'date' => now()->format('Y-m-d'),
        ]);
    }
}
