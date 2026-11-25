<?php

namespace App\Filament\Exports;

use App\Enums\{CrmInquirySource, LeadStatus};
use App\Models\Lead;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/** Lead list for sales follow-up and source analysis. */
class LeadExporter extends CompanyDataExporter
{
    protected static ?string $model = Lead::class;

    protected static function translationFile(): string
    {
        return 'lead_export';
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['assignedTo:id,name', 'createdBy:id,name']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')->label(static::label('name')),
            ExportColumn::make('phone')->label(static::label('phone')),
            ExportColumn::make('email')->label(static::label('email')),
            ExportColumn::make('status')->label(static::label('status'))
                ->formatStateUsing(fn (?LeadStatus $state): ?string => $state?->getLabel()),
            ExportColumn::make('first_source')->label(static::label('source'))
                ->formatStateUsing(fn (?CrmInquirySource $state): ?string => $state?->getLabel()),
            ExportColumn::make('assignedTo.name')->label(static::label('owner')),
            ExportColumn::make('created_at')->label(static::label('created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d')),
            ExportColumn::make('createdBy.name')->label(static::label('created_by'))->enabledByDefault(false),
            ExportColumn::make('id')->label(static::label('record_id'))->enabledByDefault(false),
        ];
    }
}
