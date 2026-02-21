<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UnitsRelationManager extends RelationManager
{
    protected static bool $isLazy = true;
    protected static string $relationship = 'units';
    protected static ?string $title = 'Units';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('unit_number')
                ->required()
                ->maxLength(50)
                ->prefixIcon('heroicon-m-hashtag'),

            Forms\Components\TextInput::make('type')
                ->label('Type')
                ->datalist([
                    'Apartment',
                    'Studio',
                    'Villa',
                    'Office',
                    'Shop',
                    'Warehouse',
                ])
                ->autocomplete(false),

            Forms\Components\TextInput::make('rent_price')
                ->required()
                ->numeric()
                ->prefix('$')
                ->minValue(0),

            Forms\Components\Select::make('status')
                ->required()
                ->options([
                    'available'   => '✅ Available',
                    'occupied'    => '🔴 Occupied',
                    'maintenance' => '🔧 Maintenance',
                    'reserved'    => '🟡 Reserved',
                ])
                ->default('available')
                ->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('unit_number')
            ->columns([
                Tables\Columns\TextColumn::make('unit_number')
                    ->label('Unit #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—'),

                Tables\Columns\TextColumn::make('rent_price')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'available',
                        'danger'  => 'occupied',
                        'warning' => 'maintenance',
                        'info'    => 'reserved',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'available'   => 'Available',
                        'occupied'    => 'Occupied',
                        'maintenance' => 'Maintenance',
                        'reserved'    => 'Reserved',
                    ]),
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
            ])
            ->emptyStateHeading('No units yet')
            ->emptyStateDescription('Add units to this property.')
            ->emptyStateIcon('heroicon-o-home');
    }
}
