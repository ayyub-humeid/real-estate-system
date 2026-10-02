<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Models\PropertyAcquisition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PropertyAcquisitionsRelationManager extends RelationManager
{
    protected static string $relationship = 'propertyAcquisitions';
    protected static ?string $title = 'Acquisition History';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('reference_number'),
            Forms\Components\Select::make('type')
                ->options(array_combine(PropertyAcquisition::TYPES, PropertyAcquisition::TYPES))
                ->required(),
            Forms\Components\DatePicker::make('acquisition_date'),
            Forms\Components\TextInput::make('agreed_value')->numeric(),
            Forms\Components\TextInput::make('currency')->maxLength(10),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')->label('Reference')->placeholder('—'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('acquisition_date')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('agreed_value')->money(fn ($record): string => $record->currency ?? 'USD'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data): PropertyAcquisition {
                        $property = $this->getOwnerRecord();
                        $acquisition = PropertyAcquisition::create(array_merge($data, [
                            'company_id' => $property->company_id,
                            'status' => 'draft',
                        ]));

                        $property->propertyAcquisitions()->attach($acquisition->id, [
                            'company_id' => $property->company_id,
                        ]);

                        return $acquisition;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
