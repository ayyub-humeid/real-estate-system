<?php

namespace App\Filament\Resources\ListingResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PriceHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'priceHistories';
    protected static ?string $title = 'Price History';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('old_price')->label('Previous')->money(fn ($record) => $record->old_currency ?: $record->new_currency)->placeholder('Initial price'),
            Tables\Columns\TextColumn::make('new_price')->label('New price')->money(fn ($record) => $record->new_currency),
            Tables\Columns\TextColumn::make('old_rent_frequency')->label('Previous frequency')->placeholder('—')->formatStateUsing(fn ($state) => $state ? ucwords(str_replace('_', ' ', $state->value)) : '—'),
            Tables\Columns\TextColumn::make('new_rent_frequency')->label('New frequency')->placeholder('—')->formatStateUsing(fn ($state) => $state ? ucwords(str_replace('_', ' ', $state->value)) : '—'),
            Tables\Columns\TextColumn::make('reason')->placeholder('—')->wrap(),
            Tables\Columns\TextColumn::make('changedBy.name')->label('Changed by')->placeholder('System'),
            Tables\Columns\TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc');
    }
}
