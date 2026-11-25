<?php

namespace App\Filament\Exports;

use App\Models\Project;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/** Project portfolio: status, timeline and size, for investor and management reporting. */
class ProjectExporter extends CompanyDataExporter
{
    protected static ?string $model = Project::class;

    protected static function translationFile(): string
    {
        return 'project_export';
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->withCount(['units', 'activeProjectProperties', 'buildings']);
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
            ExportColumn::make('units_count')->label(static::label('units_count')),
            ExportColumn::make('active_project_properties_count')->label(static::label('properties_count')),
            ExportColumn::make('buildings_count')->label(static::label('buildings_count')),
            ExportColumn::make('description')->label(static::label('description'))->enabledByDefault(false),
            ExportColumn::make('created_at')->label(static::label('created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d'))->enabledByDefault(false),
            ExportColumn::make('id')->label(static::label('record_id'))->enabledByDefault(false),
        ];
    }
}
