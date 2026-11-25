<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Contracts\HasNumericExportColumns;
use App\Models\Plan;
use Filament\Actions\Exports\ExportColumn;

/**
 * Plan tiers with feature limits — what each company gets.
 *
 * Shows all feature flags so admins and customers can see exactly what
 * the difference is between Starter, Growth, and Business plans.
 */
class PlanExporter extends CompanyDataExporter implements HasNumericExportColumns
{
    protected static ?string $model = Plan::class;

    protected static function translationFile(): string
    {
        return 'plan_export';
    }

    public static function numericColumns(): array
    {
        return ['price', 'annual_price'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label(__('plan_export.columns.name')),

            ExportColumn::make('price')
                ->label(__('plan_export.columns.price_monthly'))
                ->formatStateUsing(fn ($state): ?float => static::number($state)),

            ExportColumn::make('annual_price')
                ->label(__('plan_export.columns.price_annual'))
                ->formatStateUsing(fn ($state): ?float => static::number($state)),

            ExportColumn::make('trial_days')
                ->label(__('plan_export.columns.trial_days')),

            ExportColumn::make('is_active')
                ->label(__('plan_export.columns.status'))
                ->formatStateUsing(fn (bool $state): string => $state ? __('plan_export.statuses.active') : __('plan_export.statuses.inactive')),

            // Core features — included on all plans
            ExportColumn::make('features.maintenance_tracking')
                ->label(__('plan_export.features.maintenance_tracking'))
                ->formatStateUsing(fn (?bool $state): string => $state ? __('plan_export.boolean.yes') : __('plan_export.boolean.no'))
                ->enabledByDefault(false),

            ExportColumn::make('features.rental_requests')
                ->label(__('plan_export.features.rental_requests'))
                ->formatStateUsing(fn (?bool $state): string => $state ? __('plan_export.boolean.yes') : __('plan_export.boolean.no'))
                ->enabledByDefault(false),

            ExportColumn::make('features.expense_tracking')
                ->label(__('plan_export.features.expense_tracking'))
                ->formatStateUsing(fn (?bool $state): string => $state ? __('plan_export.boolean.yes') : __('plan_export.boolean.no'))
                ->enabledByDefault(false),

            // Limits and optional features
            ExportColumn::make('features.max_projects')
                ->label(__('plan_export.features.max_projects'))
                ->formatStateUsing(fn (?int $state): string => $state === null ? __('plan_export.limits.unlimited') : (string) $state),

            ExportColumn::make('features.max_properties')
                ->label(__('plan_export.features.max_properties'))
                ->formatStateUsing(fn (?int $state): string => $state === null ? __('plan_export.limits.unlimited') : (string) $state),

            ExportColumn::make('features.max_units')
                ->label(__('plan_export.features.max_units'))
                ->formatStateUsing(fn (?int $state): string => $state === null ? __('plan_export.limits.unlimited') : (string) $state),

            ExportColumn::make('features.max_employees')
                ->label(__('plan_export.features.max_employees'))
                ->formatStateUsing(fn (?int $state): string => $state === null ? __('plan_export.limits.unlimited') : (string) $state),

            ExportColumn::make('features.max_users')
                ->label(__('plan_export.features.max_users'))
                ->formatStateUsing(fn (?int $state): string => $state === null ? __('plan_export.limits.unlimited') : (string) $state),

            ExportColumn::make('features.construction_tracking')
                ->label(__('plan_export.features.construction_tracking'))
                ->formatStateUsing(fn (?bool $state): string => $state ? __('plan_export.boolean.yes') : __('plan_export.boolean.no')),

            ExportColumn::make('features.due_diligence')
                ->label(__('plan_export.features.due_diligence'))
                ->formatStateUsing(fn (?bool $state): string => $state ? __('plan_export.boolean.yes') : __('plan_export.boolean.no')),

            ExportColumn::make('features.advanced_reports')
                ->label(__('plan_export.features.advanced_reports'))
                ->formatStateUsing(fn (?bool $state): string => $state ? __('plan_export.boolean.yes') : __('plan_export.boolean.no')),

            ExportColumn::make('features.export_data')
                ->label(__('plan_export.features.export_data'))
                ->formatStateUsing(fn (?bool $state): string => $state ? __('plan_export.boolean.yes') : __('plan_export.boolean.no')),
        ];
    }
}
