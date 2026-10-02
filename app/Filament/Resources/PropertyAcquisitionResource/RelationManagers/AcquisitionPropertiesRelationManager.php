<?php

namespace App\Filament\Resources\PropertyAcquisitionResource\RelationManagers;

use App\Models\Property;
use App\Services\PropertyAcquisitionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class AcquisitionPropertiesRelationManager extends RelationManager
{
    protected static string $relationship = 'acquisitionProperties';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('property_id')
                ->label('Property')
                ->options(function () {
                    $companyId = $this->getOwnerRecord()->company_id;

                    return Property::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->pluck('name', 'id');
                })
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\TextInput::make('share_percentage')
                ->label('Share Percentage (%)')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%'),

            Forms\Components\TextInput::make('allocated_value')
                ->numeric()
                ->prefix('$'),

            Forms\Components\Textarea::make('notes'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('property.name')
                    ->label('Property'),
                Tables\Columns\TextColumn::make('share_percentage')
                    ->label('Share %')
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('allocated_value')
                    ->label('Allocated Value')
                    ->money('USD'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addProperty')
                    ->label('Add Property')
                    ->visible(fn (): bool => auth()->user()->can('create_acquisition_property'))
                    ->form([
                        Forms\Components\Select::make('property_id')
                            ->label('Property')
                            ->options(function () {
                                $companyId = $this->getOwnerRecord()->company_id;
                                return Property::withoutGlobalScopes()
                                    ->where('company_id', $companyId)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        if ($this->getOwnerRecord()->acquisitionProperties()->where('property_id', $value)->exists()) {
                                            $fail('This property is already attached to this acquisition.');
                                        }
                                    };
                                }
                            ]),
                        Forms\Components\TextInput::make('share_percentage')
                            ->label('Share Percentage (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                        Forms\Components\TextInput::make('allocated_value')
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\Textarea::make('notes'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $property = Property::withoutGlobalScopes()->findOrFail($data['property_id']);

                            return app(PropertyAcquisitionService::class)->attachProperty(
                                auth()->user(),
                                $this->getOwnerRecord(),
                                $property,
                                collect($data)->except('property_id')->all(),
                            );
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot Add Property')
                                ->body(collect($e->errors())->flatten()->first())
                                ->send();

                            return null;
                        } catch (AuthorizationException) {
                            Notification::make()
                                ->danger()
                                ->title('Unauthorized')
                                ->body('You do not have permission to add properties to this acquisition.')
                                ->send();

                            return null;
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
