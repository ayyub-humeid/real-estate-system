<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitFeatureResource\Pages;
use App\Filament\Resources\UnitFeatureResource\RelationManagers;
use App\Models\UnitFeature;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UnitFeatureResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = UnitFeature::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationGroup = '🏠 Properties';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Unit Feature';
    protected static ?string $pluralModelLabel = 'Unit Features';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['unit.property']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Feature Details'))
                    ->description(__('Specify the feature for a specific unit'))
                    ->schema([
                        Forms\Components\Select::make('unit_id')
                            ->label(__('Unit'))
                            ->relationship('unit', 'unit_number', function (Builder $query) {
                                $query->with('property');
                            })
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->property->name} - Unit {$record->unit_number}")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('name')
                            ->label(__('Feature Name'))
                            ->placeholder(__('e.g., Sea View, High Ceiling'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('value')
                            ->label(__('Value / Detail (Optional)'))
                            ->placeholder(__('e.g., Panoramic, 4 Meters'))
                            ->maxLength(255),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('unit.property.name')
                    ->label(__('Property'))
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('unit.unit_number')
                    ->label(__('Unit #'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Feature'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('value')
                    ->label(__('Detail'))
                    ->placeholder(__('—')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit')
                    ->label(__('Unit'))
                    ->relationship('unit', 'unit_number')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('No unit features yet'))
            ->emptyStateIcon('heroicon-o-sparkles');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnitFeatures::route('/'),
            'create' => Pages\CreateUnitFeature::route('/create'),
            'view' => Pages\ViewUnitFeature::route('/{record}'),
            'edit' => Pages\EditUnitFeature::route('/{record}/edit'),
        ];
    }
}
