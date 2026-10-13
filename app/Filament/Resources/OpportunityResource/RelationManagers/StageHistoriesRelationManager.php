<?php

namespace App\Filament\Resources\OpportunityResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StageHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'stageHistories';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('crm.navigation.stage_history');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('changed_at', 'desc')
            ->emptyStateHeading(__('crm.empty.stage_history.heading'))
            ->emptyStateDescription(__('crm.empty.stage_history.description'))
            ->emptyStateIcon('heroicon-o-clock')
            ->columns([
                Tables\Columns\TextColumn::make('fromStage.label')->label(__('crm.fields.stage'))->placeholder('—')
                    ->formatStateUsing(fn ($state, $record) => $record->fromStage?->display_label ?? $state),
                Tables\Columns\TextColumn::make('toStage.label')->label(__('crm.actions.move'))->weight('medium')
                    ->formatStateUsing(fn ($state, $record) => $record->toStage?->display_label ?? $state),
                Tables\Columns\TextColumn::make('changedBy.name')->label(__('crm.fields.owner'))->placeholder('—'),
                Tables\Columns\TextColumn::make('changed_at')->label(__('crm.fields.changed_at'))->dateTime(),
            ]);
    }
}
