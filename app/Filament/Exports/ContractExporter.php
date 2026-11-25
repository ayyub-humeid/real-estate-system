<?php

namespace App\Filament\Exports;

use App\Enums\{ContractStatus, ContractType};
use App\Models\Contract;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/** Contract register: what each contract covers and where it stands, for legal and sales audits. */
class ContractExporter extends CompanyDataExporter
{
    protected static ?string $model = Contract::class;

    protected static function exportPermission(): ?string
    {
        return 'export_contract';
    }

    protected static function translationFile(): string
    {
        return 'contract_export';
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'unit:id,unit_number', 'plannedUnit:id,code', 'property:id,name', 'project:id,name', 'createdBy:id,name',
        ]);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('contract_number')->label(static::label('contract_number')),
            ExportColumn::make('subject')->label(static::label('subject'))
                ->state(fn (Contract $record): string => $record->displayName(withType: false)),
            ExportColumn::make('contract_type')->label(static::label('type'))
                ->formatStateUsing(fn (?ContractType $state): ?string => $state?->getLabel()),
            ExportColumn::make('status')->label(static::label('status'))
                ->formatStateUsing(fn (?ContractStatus $state): ?string => $state?->getLabel()),
            ExportColumn::make('unit.unit_number')->label(static::label('unit')),
            ExportColumn::make('plannedUnit.code')->label(static::label('planned_unit')),
            ExportColumn::make('property.name')->label(static::label('property')),
            ExportColumn::make('project.name')->label(static::label('project')),
            ExportColumn::make('created_at')->label(static::label('created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d')),
            ExportColumn::make('createdBy.name')->label(static::label('created_by'))->enabledByDefault(false),
            ExportColumn::make('id')->label(static::label('record_id'))->enabledByDefault(false),
        ];
    }
}
