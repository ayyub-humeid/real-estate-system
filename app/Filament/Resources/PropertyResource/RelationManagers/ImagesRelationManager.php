<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static bool $isLazy = true;
    // This uses the morphMany('images') relationship defined on the Property model.
    // Filament scopes queries automatically by imageable_type + imageable_id.
    protected static string $relationship = 'images';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Gallery');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('path')
                ->label(__('Image'))
                ->image()
                ->directory('images/properties')
                ->imageEditor()
                ->maxSize(5120)       // 5 MB
                ->required()
                ->columnSpanFull(),

            Forms\Components\Toggle::make('is_primary')
                ->label(__('Primary Image'))
                ->helperText(__('Set as the main display image'))
                ->default(false)
                ->rules([
                    fn (Forms\Components\Toggle $component) => function (string $attribute, $value, \Closure $fail) use ($component) {
                        if ($value) {
                            $ownerRecord = $component->getLivewire()->getOwnerRecord();
                            $existingPrimary = $ownerRecord->images()
                                ->whereRaw('is_primary = true')
                                ->when($component->getLivewire()->getRecord(), fn ($q, $record) => $q->where('id', '!=', $record->id))
                                ->exists();

                            if ($existingPrimary) {
                                $fail(__('This property already has a primary image. Please unset the current primary image first.'));
                            }
                        }
                    }
                ]),

            Forms\Components\TextInput::make('order')
                ->label(__('Display Order'))
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
            ->reorderable('order')   // drag-to-reorder rows
            ->columns([
                Tables\Columns\ImageColumn::make('path')
                    ->label(__('Preview'))
                    ->height(60)
                    ->width(80),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label(__('Primary'))
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning'),

                Tables\Columns\TextColumn::make('order')
                    ->label(__('Order'))
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label(__('Upload Image')),
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
            ->emptyStateHeading(__('No images yet'))
            ->emptyStateDescription(__('Upload images for this property.'))
            ->emptyStateIcon('heroicon-o-photo');
    }
}
