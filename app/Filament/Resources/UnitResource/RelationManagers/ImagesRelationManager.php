<?php

namespace App\Filament\Resources\UnitResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static bool $isLazy = true;
    // Uses the morphMany('images') on the Unit model.
    // Filament scopes automatically by imageable_type=Unit + imageable_id.
    protected static string $relationship = 'images';
    protected static ?string $title = 'Unit Gallery';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('path')
                ->label('Image')
                ->image()
                ->directory('images/units')
                ->disk('public')
                ->imageEditor()
                ->maxSize(5120)
                ->required()
                ->columnSpanFull(),

            Forms\Components\Toggle::make('is_primary')
                ->label('Primary Image')
                ->helperText('Set as the main display image for this unit')
                ->default(false),

            Forms\Components\TextInput::make('order')
                ->label('Display Order')
                ->numeric()
                ->default(0)
                ->minValue(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->defaultSort('order')
            ->reorderable('order')
            ->columns([
                Tables\Columns\ImageColumn::make('path')
                    ->label('Preview')
                    ->disk('public')
                    ->height(60)
                    ->width(80),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning'),

                Tables\Columns\TextColumn::make('order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Upload Image'),
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
            ->emptyStateHeading('No images yet')
            ->emptyStateDescription('Upload images for this unit.')
            ->emptyStateIcon('heroicon-o-photo')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Upload Image')
                    ->icon('heroicon-m-plus'),
            ]);
    }
}
