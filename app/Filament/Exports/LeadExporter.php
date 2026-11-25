<?php

namespace App\Filament\Exports;

use App\Models\Lead;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lead export — CRM and sales funnel tracking.
 *
 * Shows leads/opportunities: contact, source, status, interests, and dates
 * so sales teams can track pipeline and report on conversion metrics.
 */
class LeadExporter extends Exporter
{
    protected static ?string $model = Lead::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'contact:id,name,email,phone',
            'source:id,name',
            'createdBy:id,name',
        ])->orderBy('created_at', 'desc');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('contact.name')
                ->label(__('lead_export.columns.contact_name'))
                ->searchable(),

            ExportColumn::make('contact.email')
                ->label(__('lead_export.columns.email')),

            ExportColumn::make('contact.phone')
                ->label(__('lead_export.columns.phone')),

            ExportColumn::make('status')
                ->label(__('lead_export.columns.status'))
                ->formatStateUsing(fn (?string $state): string => $state ? __('enums.lead_status.'.$state) : ''),

            ExportColumn::make('source.name')
                ->label(__('lead_export.columns.source')),

            ExportColumn::make('interests')
                ->label(__('lead_export.columns.interests'))
                ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (string) ($state ?? '')),

            ExportColumn::make('budget')
                ->label(__('lead_export.columns.budget'))
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label(__('lead_export.columns.created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d')),

            ExportColumn::make('updated_at')
                ->label(__('lead_export.columns.updated_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d'))
                ->enabledByDefault(false),

            ExportColumn::make('createdBy.name')
                ->label(__('lead_export.columns.assigned_to'))
                ->enabledByDefault(false),

            ExportColumn::make('id')
                ->label(__('lead_export.columns.record_id'))
                ->enabledByDefault(false),
        ];
    }

    public function getFileName(mixed $export): string
    {
        return __('lead_export.file_name', [
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
