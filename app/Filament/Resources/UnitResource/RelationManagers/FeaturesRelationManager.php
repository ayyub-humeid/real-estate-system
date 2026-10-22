<?php

namespace App\Filament\Resources\UnitResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'features';

    /** Features are part of unit setup and remain manageable from View Unit. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Features');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('feature_key')
                    ->label(__('Common feature'))
                    ->options(collect(\App\Services\UnitSetupService::FEATURE_CATALOGUE)
                        ->mapWithKeys(fn ($value, $key) => [$key => __('unit_features.'.$key)]))
                    ->live()
                    // Persist the stable catalogue value; the table renders the
                    // translated label from feature_key for the active locale.
                    ->afterStateUpdated(fn (Forms\Set $set, $state) => $state ? $set('name', \App\Services\UnitSetupService::FEATURE_CATALOGUE[$state][0]) : null),
                Forms\Components\TextInput::make('name')
                    ->label(__('Feature Name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('value')
                    ->label(__('Value / Detail'))
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Feature'))
                    ->weight('bold')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state, $record): string => $record->feature_key
                        ? __('unit_features.'.$record->feature_key)
                        : ($state ?? __('—'))),
                Tables\Columns\TextColumn::make('value')
                    ->label(__('Detail'))
                    ->placeholder(__('—')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
