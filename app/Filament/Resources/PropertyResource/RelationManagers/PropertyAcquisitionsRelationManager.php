<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Models\PropertyAcquisition;
use App\Services\PropertyAcquisitionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PropertyAcquisitionsRelationManager extends RelationManager
{
    protected static string $relationship = 'propertyAcquisitions';

    /** The relation exposes its own guarded acquisition-creation workflow. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Acquisition History');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
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
            Forms\Components\TextInput::make('agreed_value')->label(__('Agreed Value'))->numeric(),
            Forms\Components\TextInput::make('currency')->label(__('Currency'))->maxLength(10),
            Forms\Components\Textarea::make('description')->label(__('Description'))->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')->label(__('Reference'))->placeholder(__('—')),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash_purchase' => __('cash_purchase'),
                        'installment_purchase' => __('installment_purchase'),
                        'partnership' => __('partnership'),
                        'land_for_units' => __('land_for_units'),
                        'profit_sharing' => __('profit_sharing'),
                        'owned_existing' => __('owned_existing'),
                        default => $state ?? '—',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => __('draft'),
                        'under_due_diligence' => __('under_due_diligence'),
                        'approved' => __('approved'),
                        'completed' => __('completed'),
                        'cancelled' => __('cancelled'),
                        default => $state ?? '—',
                    }),
                Tables\Columns\TextColumn::make('acquisition_date')->label(__('Acquisition Date'))->date()->placeholder(__('—')),
                Tables\Columns\TextColumn::make('agreed_value')->label(__('Agreed Value'))->money(fn ($record): string => $record->currency ?? 'USD'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createAcquisition')
                    ->label(__('Create acquisition'))
                    ->visible(fn (): bool => auth()->user()->can('create', PropertyAcquisition::class))
                    ->form([
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
                        Forms\Components\TextInput::make('agreed_value')->label(__('Agreed Value'))->numeric(),
                        Forms\Components\TextInput::make('currency')->label(__('Currency'))->maxLength(10),
                        Forms\Components\Textarea::make('description')->label(__('Description'))->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
                    ])
                    ->action(function (array $data): void {
                        app(PropertyAcquisitionService::class)->createForProperty(
                            auth()->user(),
                            $this->getOwnerRecord(),
                            $data,
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
