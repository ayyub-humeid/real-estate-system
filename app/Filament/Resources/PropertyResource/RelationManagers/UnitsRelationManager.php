<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Enums\UnitType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UnitsRelationManager extends RelationManager
{
    protected static bool $isLazy = true;
    protected static string $relationship = 'units';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Units');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('unit_number')
                ->label(__('Unit #'))
                ->required()
                ->maxLength(50)
                ->prefixIcon('heroicon-m-hashtag'),

            Forms\Components\Select::make('type')
                ->label(__('Type'))
                ->options(UnitType::options())
                ->searchable()
                ->native(false),

            Forms\Components\Select::make('status')
                ->label(__('Physical status'))
                ->options(\App\Enums\UnitStatus::options())
                ->default('draft')
                ->disabled()
                ->dehydrated(false)
                ->helperText(__('Use the Unit Change status action after setup is saved.')),
            Forms\Components\TextInput::make('actual_area')->label(__('Actual Area'))->numeric()->minValue(0),
            Forms\Components\Select::make('area_unit')->label(__('Area Unit'))->options(['m2' => 'm²', 'sqft' => 'sq ft']),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('unit_number')
            ->columns([
                Tables\Columns\TextColumn::make('unit_number')
                    ->label(__('Unit #'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => $state ? UnitType::tryFrom($state)?->getLabel() ?? $state : __('—')),

                Tables\Columns\TextColumn::make('rent_price')
                    ->label(__('Legacy rent'))
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'ready',
                        'warning' => 'maintenance',
                        'danger' => 'inactive',
                    ])
                    ->formatStateUsing(fn (string $state): string => \App\Enums\UnitStatus::tryFrom($state)?->getLabel() ?? $state),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        ...\App\Enums\UnitStatus::options(),
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    }),
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
            ->emptyStateHeading(__('No units yet'))
            ->emptyStateDescription(__('Add units to this property.'))
            ->emptyStateIcon('heroicon-o-home');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getOwnerRecord()->company_id;

        return $data;
    }
}
