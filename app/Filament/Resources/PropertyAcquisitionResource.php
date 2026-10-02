<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyAcquisitionResource\Pages;
use App\Filament\Resources\PropertyAcquisitionResource\RelationManagers;
use App\Models\PropertyAcquisition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PropertyAcquisitionResource extends Resource
{
    protected static ?string $model = PropertyAcquisition::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = '🏠 Properties';

    private static function companyField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('company_id')
            ->label('Company')
            ->relationship('company', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->visible(fn (): bool => auth()->user()->isSuperAdmin());
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withCount(['acquisitionProperties', 'acquisitionParties', 'dueDiligenceCases']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            self::companyField(),
            Forms\Components\TextInput::make('reference_number'),
            Forms\Components\Select::make('type')->options(array_combine(PropertyAcquisition::TYPES, PropertyAcquisition::TYPES))->required(),
            Forms\Components\DatePicker::make('acquisition_date'),
            Forms\Components\TextInput::make('agreed_value')->numeric()->live(),
            Forms\Components\TextInput::make('currency')->maxLength(10)->required(fn (Forms\Get $get): bool => filled($get('agreed_value'))),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('reference_number')->searchable(),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('acquisition_properties_count')->label('Properties'),
            Tables\Columns\TextColumn::make('due_diligence_cases_count')->label('DD cases'),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AcquisitionPropertiesRelationManager::class,
            RelationManagers\AcquisitionPartiesRelationManager::class,
            RelationManagers\DueDiligenceCasesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPropertyAcquisitions::route('/'),
            'create' => Pages\CreatePropertyAcquisition::route('/create'),
            'view' => Pages\ViewPropertyAcquisition::route('/{record}'),
            'edit' => Pages\EditPropertyAcquisition::route('/{record}/edit'),
        ];
    }
}
