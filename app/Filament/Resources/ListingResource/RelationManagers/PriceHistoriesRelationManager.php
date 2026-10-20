<?php

namespace App\Filament\Resources\ListingResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PriceHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'priceHistories';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Price History');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make(
                'old_price'
            )->label(__('Previous price'))
                ->money(fn($record) => $record->old_currency ?: $record->new_currency)->placeholder(__('Initial price')),
            Tables\Columns\TextColumn::make('new_price')
                ->label(__('New price'))
                ->money(fn($record) => $record->new_currency),
            Tables\Columns\TextColumn::make('old_rent_frequency')
                ->label(__('Previous frequency'))
                ->placeholder(__('—'))->formatStateUsing(fn($state) => $state?->getLabel() ?? __('—')),
            Tables\Columns\TextColumn::make('new_rent_frequency')
                ->label(__('New frequency'))
                ->placeholder(__('—'))->formatStateUsing(fn($state) => $state?->getLabel() ?? __('—')),
            Tables\Columns\TextColumn::make('reason')
                ->label(__('Reason'))
                ->placeholder(__('—'))->wrap(),
            Tables\Columns\TextColumn::make('changedBy.name')
                ->label(__('Changed by'))
                ->placeholder(__('System')),
            Tables\Columns\TextColumn::make('created_at')
                ->label(__('Date'))
                ->dateTime()->sortable(),
        ])
            ->emptyStateHeading(__('No price history recorded'))
            ->defaultSort('created_at', 'desc');
    }
}
