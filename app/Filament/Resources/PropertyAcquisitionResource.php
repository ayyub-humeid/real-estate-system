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
            Forms\Components\TextInput::make('reference_number')->label(__('Reference Number')),
            Forms\Components\Select::make('type')
                ->label(__('Type'))
                ->options([
                    'cash_purchase' => __('cash_purchase'),
                    'installment_purchase' => __('installment_purchase'),
                    'partnership' => __('partnership'),
                    'land_for_units' => __('land_for_units'),
                    'profit_sharing' => __('profit_sharing'),
                    'owned_existing' => __('owned_existing'),
                ])
                ->required(),
            Forms\Components\DatePicker::make('acquisition_date')->label(__('Acquisition Date')),
            Forms\Components\TextInput::make('agreed_value')->label(__('Agreed Value'))->numeric()->live(),
            Forms\Components\TextInput::make('currency')->label(__('Currency'))->maxLength(10)->required(fn (Forms\Get $get): bool => filled($get('agreed_value'))),
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
                ->formatStateUsing(fn (?string $state): string => $state ? match ($state) {
                    'cash_purchase' => __('cash_purchase'),
                    'installment_purchase' => __('installment_purchase'),
                    'partnership' => __('partnership'),
                    'land_for_units' => __('land_for_units'),
                    'profit_sharing' => __('profit_sharing'),
                    'owned_existing' => __('owned_existing'),
                    default => $state,
                } : '—'),
            Tables\Columns\TextColumn::make('status')
                ->label(__('Status'))
                ->badge()
                ->colors([
                    'gray' => 'draft',
                    'warning' => 'under_due_diligence',
                    'info' => 'approved',
                    'success' => 'completed',
                    'danger' => 'cancelled',
                ])
                ->formatStateUsing(fn (?string $state): string => $state ? match ($state) {
                    'draft' => __('draft'),
                    'under_due_diligence' => __('under_due_diligence'),
                    'approved' => __('approved'),
                    'completed' => __('completed'),
                    'cancelled' => __('cancelled'),
                    default => $state,
                } : '—'),
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
