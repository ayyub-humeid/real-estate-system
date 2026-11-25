<?php

namespace App\Filament\Exports;

use App\Models\Project;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Project export — portfolio and investment tracking.
 *
 * Shows projects: name, status, location, units count, and progress
 * so companies can report to investors and track portfolio.
 */
class ProjectExporter extends Exporter
{
    protected static ?string $model = Project::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->withCount(['units', 'properties'])
            ->with(['location:id,name'])
            ->orderBy('name');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label(__('project_export.columns.name'))
                ->searchable(),

            ExportColumn::make('status')
                ->label(__('project_export.columns.status'))
                ->formatStateUsing(fn (?string $state): string => $state ? __('enums.project_status.'.$state) : ''),

            ExportColumn::make('location.name')
                ->label(__('project_export.columns.location')),

            ExportColumn::make('units_count')
                ->label(__('project_export.columns.units_count')),

            ExportColumn::make('properties_count')
                ->label(__('project_export.columns.properties_count')),

            ExportColumn::make('description')
                ->label(__('project_export.columns.description'))
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label(__('project_export.columns.created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d')),

            ExportColumn::make('updated_at')
                ->label(__('project_export.columns.updated_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d'))
                ->enabledByDefault(false),

            ExportColumn::make('id')
                ->label(__('project_export.columns.record_id'))
                ->enabledByDefault(false),
        ];
    }

    public function getFileName(mixed $export): string
    {
        return __('project_export.file_name', [
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
