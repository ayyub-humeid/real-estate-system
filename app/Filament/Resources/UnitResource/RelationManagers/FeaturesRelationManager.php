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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('feature_key')
                    ->label('Common feature')
                    ->options(collect(\App\Services\UnitSetupService::FEATURE_CATALOGUE)->mapWithKeys(fn($v, $k) => [$k => $v[0]]))
                    ->live()
                    ->afterStateUpdated(fn(Forms\Set $set, $state) => $state ? $set('name', \App\Services\UnitSetupService::FEATURE_CATALOGUE[$state][0]) : null),
                Forms\Components\TextInput::make('name')
                    ->label('Feature Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('value')
                    ->label('Value / Detail')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Feature')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Detail')
                    ->placeholder('—'),
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
