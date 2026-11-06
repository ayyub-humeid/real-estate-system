<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FinancialHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'financialEvents';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('contract_finance.navigation.financial_history');
    }

    public static function getModelLabel(): string
    {
        return __('contract_finance.fields.financial_event');
    }

    public static function getPluralModelLabel(): string
    {
        return __('contract_finance.navigation.financial_history');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view_contract_financials') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_type')->label(__('contract_finance.fields.event'))->formatStateUsing(fn (string $state): string => __('contract_finance.notifications.'.$state)),
                Tables\Columns\TextColumn::make('schedule.revision_number')->label(__('contract_finance.fields.revision'))->prefix('V')->placeholder('—'),
                Tables\Columns\TextColumn::make('payment.reference_number')->label(__('contract_finance.fields.reference_number'))->placeholder('—'),
                Tables\Columns\TextColumn::make('actor.name')->label(__('contract_finance.fields.changed_by'))->placeholder('—'),
                Tables\Columns\TextColumn::make('occurred_at')->label(__('contract_finance.fields.changed_at'))->dateTime()->sortable(),
            ])
            ->emptyStateHeading(__('contract_finance.empty.financial_history_heading'))
            ->emptyStateDescription(__('contract_finance.empty.financial_history_description'))
            ->defaultSort('occurred_at', 'desc');
    }
}
