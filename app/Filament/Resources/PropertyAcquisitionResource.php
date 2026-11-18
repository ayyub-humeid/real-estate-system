<?php

namespace App\Filament\Resources;

use App\Enums\{AcquisitionStatus, AcquisitionType, Currency};
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
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = PropertyAcquisition::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = '🏠 Properties';

    private static function companyField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('company_id')
            ->label(__('Company'))
            ->relationship('company', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->visible(fn(): bool => auth()->user()->isSuperAdmin());
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withCount(['acquisitionProperties', 'acquisitionParties', 'dueDiligenceCases']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            self::companyField(),
            Forms\Components\TextInput::make('reference_number')
                ->label(__('Reference Number'))
                ->placeholder(__('Generated automatically after creation.'))
                ->disabled()
                ->dehydrated(false),
            Forms\Components\Select::make('type')
                ->label(__('Type'))
                ->options(AcquisitionType::options())
                ->required(),
            Forms\Components\DatePicker::make('acquisition_date')->label(__('Acquisition Date')),
            Forms\Components\TextInput::make('agreed_value')->label(__('Agreed Value'))->numeric()->live(),
            Forms\Components\Select::make('currency')
                ->label(__('Currency'))
                ->options(Currency::options())
                ->default('ILS')
                ->searchable()
                ->native(false)
                ->required(fn(Forms\Get $get): bool => filled($get('agreed_value'))),

            // Land acquisition enhancement fields
            Forms\Components\TextInput::make('total_land_area_m2')
                ->label(__('acquisition.fields.total_land_area_m2'))
                ->numeric()->minValue(0)->step(0.01),
            Forms\Components\TextInput::make('title_deed_number')->label(__('acquisition.fields.title_deed_number'))->maxLength(100),
            Forms\Components\Select::make('seller_party_id')
                ->label(__('acquisition.fields.seller_party'))
                ->relationship('sellerParty', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            Forms\Components\TextInput::make('latitude')
                ->label(__('acquisition.fields.latitude'))->numeric()->step(0.00000001)->minValue(-90)->maxValue(90),
            Forms\Components\TextInput::make('longitude')
                ->label(__('acquisition.fields.longitude'))->numeric()->step(0.00000001)->minValue(-180)->maxValue(180),
            Forms\Components\Textarea::make('location_details')
                ->label(__('acquisition.fields.location_details'))->columnSpanFull()->rows(3),

            Forms\Components\Textarea::make('description')->label(__('Description'))->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('reference_number')->label(__('Reference Number'))->searchable(),
            Tables\Columns\TextColumn::make('type')
                ->label(__('Type'))
                ->badge()
                ->formatStateUsing(fn(?string $state): string => AcquisitionType::labelFor($state)),
            Tables\Columns\TextColumn::make('status')
                ->label(__('Status'))
                ->badge()
                ->color(fn(?string $state): string|array|null => AcquisitionStatus::tryFrom((string) $state)?->getColor() ?? 'gray')
                ->formatStateUsing(fn(?string $state): string => AcquisitionStatus::labelFor($state)),
            Tables\Columns\TextColumn::make('acquisition_properties_count')->label(__('Properties')),
            Tables\Columns\TextColumn::make('due_diligence_cases_count')->label(__('Due Diligence Cases')),
        ])->actions([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                        Tables\Actions\DeleteBulkAction::make(),
                    ]),
                ])
            ->emptyStateHeading(__('No property acquisitions found'))
            ->emptyStateIcon('heroicon-o-document-check');
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
