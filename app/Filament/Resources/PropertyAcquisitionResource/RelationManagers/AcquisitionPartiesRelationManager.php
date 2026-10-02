<?php

namespace App\Filament\Resources\PropertyAcquisitionResource\RelationManagers;

use App\Models\AcquisitionParty;
use App\Models\Party;
use App\Services\PropertyAcquisitionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class AcquisitionPartiesRelationManager extends RelationManager
{
    protected static string $relationship = 'acquisitionParties';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('party_id')
                ->label('Party')
                ->options(function () {
                    $companyId = $this->getOwnerRecord()->company_id;

                    return Party::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->pluck('name', 'id');
                })
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\Select::make('role')
                ->options([
                    'seller' => 'Seller',
                    'buyer' => 'Buyer',
                    'broker' => 'Broker',
                    'investor' => 'Investor',
                    'guarantor' => 'Guarantor',
                    'other' => 'Other',
                ])
                ->required()
                ->live(),

            Forms\Components\TextInput::make('share_percentage')
                ->label('Share Percentage (%)')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->helperText(function (Forms\Get $get) {
                    $role = $get('role');

                    if (empty($role)) {
                        return 'Select a role first to see current allocation.';
                    }

                    $existing = (float) $this->getOwnerRecord()
                        ->acquisitionParties()
                        ->where('role', $role)
                        ->sum('share_percentage');

                    $remaining = max(0, 100 - $existing);

                    return "Role \"{$role}\" — Currently allocated: {$existing}% — Remaining: {$remaining}%";
                })
                ->rules([
                    function () {
                        return function (string $attribute, $value, \Closure $fail) {
                            if ($value === null || $value === '') {
                                return;
                            }

                            // Get the role from the form data
                            $role = request()->input('data.role')
                                ?? request()->input('mountedTableActionsData.0.role')
                                ?? null;

                            if (empty($role)) {
                                return; // role is required separately; skip percentage check
                            }

                            $existing = (float) $this->getOwnerRecord()
                                ->acquisitionParties()
                                ->where('role', $role)
                                ->sum('share_percentage');

                            $total = $existing + (float) $value;

                            if ($total > 100) {
                                $remaining = max(0, 100 - $existing);
                                $fail("Total \"{$role}\" share would be {$total}%, exceeding 100%. Currently allocated for \"{$role}\": {$existing}%. Maximum you can assign: {$remaining}%.");
                            }
                        };
                    },
                ]),

            Forms\Components\Textarea::make('notes'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('party.name')
                    ->label('Party'),
                Tables\Columns\TextColumn::make('role')
                    ->badge(),
                Tables\Columns\TextColumn::make('share_percentage')
                    ->label('Share %')
                    ->suffix('%'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addParty')
                    ->label('Add Party')
                    ->visible(fn (): bool => auth()->user()->can('create_acquisition_party'))
                    ->form([
                        Forms\Components\Select::make('party_id')
                            ->label('Party')
                            ->options(function () {
                                $companyId = $this->getOwnerRecord()->company_id;

                                return Party::withoutGlobalScopes()
                                    ->where('company_id', $companyId)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $role = request()->input('data.role') ?? request()->input('mountedTableActionsData.0.role');
                                        if ($role && $this->getOwnerRecord()->acquisitionParties()->where('party_id', $value)->where('role', $role)->exists()) {
                                            $fail("This party is already attached as a {$role} to this acquisition.");
                                        }
                                    };
                                }
                            ]),

                        Forms\Components\Select::make('role')
                            ->options([
                                'seller' => 'Seller',
                                'buyer' => 'Buyer',
                                'broker' => 'Broker',
                                'investor' => 'Investor',
                                'guarantor' => 'Guarantor',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('share_percentage')
                            ->label('Share Percentage (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText(function (Forms\Get $get) {
                                $role = $get('role');

                                if (empty($role)) {
                                    return 'Select a role first to see current allocation.';
                                }

                                $existing = (float) $this->getOwnerRecord()
                                    ->acquisitionParties()
                                    ->where('role', $role)
                                    ->sum('share_percentage');

                                $remaining = max(0, 100 - $existing);

                                return "Role \"{$role}\" — Currently allocated: {$existing}% — Remaining: {$remaining}%";
                            })
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        if ($value === null || $value === '') {
                                            return;
                                        }

                                        // Get the role from the form data
                                        $role = request()->input('data.role')
                                            ?? request()->input('mountedTableActionsData.0.role')
                                            ?? null;

                                        if (empty($role)) {
                                            return; // role is required separately; skip percentage check
                                        }

                                        $existing = (float) $this->getOwnerRecord()
                                            ->acquisitionParties()
                                            ->where('role', $role)
                                            ->sum('share_percentage');

                                        $total = $existing + (float) $value;

                                        if ($total > 100) {
                                            $remaining = max(0, 100 - $existing);
                                            $fail("Total \"{$role}\" share would be {$total}%, exceeding 100%. Currently allocated for \"{$role}\": {$existing}%. Maximum you can assign: {$remaining}%.");
                                        }
                                    };
                                },
                            ]),

                        Forms\Components\Textarea::make('notes'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $party = Party::withoutGlobalScopes()->findOrFail($data['party_id']);

                            return app(PropertyAcquisitionService::class)->attachParty(
                                auth()->user(),
                                $this->getOwnerRecord(),
                                $party,
                                $data['role'],
                                collect($data)->except(['party_id', 'role'])->all(),
                            );
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot Add Party')
                                ->body(collect($e->errors())->flatten()->first())
                                ->send();

                            return null;
                        } catch (AuthorizationException) {
                            Notification::make()
                                ->danger()
                                ->title('Unauthorized')
                                ->body('You do not have permission to add parties to this acquisition.')
                                ->send();

                            return null;
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('editParty')
                    ->label('Edit')
                    ->visible(fn (): bool => auth()->user()->can('update_acquisition_party'))
                    ->fillForm(fn (AcquisitionParty $record): array => $record->only(['role', 'share_percentage', 'notes']))
                    ->form([
                        Forms\Components\Select::make('role')
                            ->options([
                                'seller' => 'Seller', 'buyer' => 'Buyer', 'broker' => 'Broker',
                                'investor' => 'Investor', 'guarantor' => 'Guarantor', 'other' => 'Other',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('share_percentage')
                            ->label('Share Percentage (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                        Forms\Components\Textarea::make('notes'),
                    ])
                    ->action(function (AcquisitionParty $record, array $data) {
                        try {
                            return app(PropertyAcquisitionService::class)->updateAcquisitionParty(auth()->user(), $record, $data);
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title('Cannot Update Party')
                                ->body(collect($e->errors())->flatten()->first())->send();
                            return null;
                        } catch (AuthorizationException) {
                            Notification::make()->danger()->title('Unauthorized')
                                ->body('You do not have permission to update parties in this acquisition.')->send();
                            return null;
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
