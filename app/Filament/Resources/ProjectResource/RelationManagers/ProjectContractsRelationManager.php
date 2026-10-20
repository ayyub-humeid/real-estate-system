<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Filament\Resources\ContractResource;
use App\Enums\{ContractStatus, ContractType};
use App\Models\Contract;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Contracts');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('contract_number')->label(__('Contract number'))->weight('bold'),
            Tables\Columns\TextColumn::make('contract_type')->label(__('Contract type'))->formatStateUsing(fn ($state): string => $state instanceof ContractType ? $state->getLabel() : ContractType::tryFrom((string) $state)?->getLabel() ?? (string) $state)->badge(),
            Tables\Columns\TextColumn::make('status')->label(__('Status'))->formatStateUsing(fn ($state): string => $state instanceof ContractStatus ? $state->getLabel() : ContractStatus::tryFrom((string) $state)?->getLabel() ?? (string) $state)->color(fn ($state): ?string => $state instanceof ContractStatus ? $state->color() : ContractStatus::tryFrom((string) $state)?->color())->badge(),
            Tables\Columns\TextColumn::make('unit.unit_number')->label(__('Actual unit'))->placeholder('—'),
            Tables\Columns\TextColumn::make('plannedUnit.code')->label(__('Planned unit'))->placeholder('—'),
        ])->actions([
            Tables\Actions\Action::make('details')->label(__('Details'))->icon('heroicon-o-eye')
                ->url(fn (Contract $record): string => ContractResource::getUrl('view', ['record' => $record])),
        ]);
    }
}
