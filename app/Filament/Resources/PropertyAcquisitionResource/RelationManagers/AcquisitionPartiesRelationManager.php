<?php

namespace App\Filament\Resources\PropertyAcquisitionResource\RelationManagers;

use App\Enums\AcquisitionPartyRole;
use App\Models\AcquisitionParty;
use App\Models\Company;
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

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Parties');
    }

    private function externalPartyOptions(): array
    {
        $company = Company::query()->findOrFail($this->getOwnerRecord()->company_id);

        return Party::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->when($company->self_party_id, fn ($query) => $query->whereKeyNot($company->self_party_id))
            ->pluck('name', 'id')
            ->all();
    }

    private function allocatedShare(string $role, ?int $exceptId = null): float
    {
        return (float) $this->getOwnerRecord()
            ->acquisitionParties()
            ->where('role', $role)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->sum('share_percentage');
    }

    private function roleField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('role')
            ->label(__('Role'))
            ->options(AcquisitionPartyRole::options())
            ->required()
            ->live();
    }

    private function shareField(?int $exceptId = null): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('share_percentage')
            ->label(__('Share Percentage (%)'))
            ->numeric()
            ->minValue(0)
            ->maxValue(100)
            ->suffix('%')
            ->helperText(function (Forms\Get $get) use ($exceptId): string {
                $role = $get('role');

                if (empty($role)) {
                    return __('acquisition.parties.select_role_first');
                }

                $allocated = $this->allocatedShare($role, $exceptId);

                return __('acquisition.parties.allocation_hint', [
                    'role' => AcquisitionPartyRole::labelFor($role),
                    'allocated' => $allocated,
                    'remaining' => max(0, 100 - $allocated),
                ]);
            })
            ->rules([
                fn (Forms\Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get, $exceptId): void {
                    $role = $get('role');

                    if ($value === null || $value === '' || empty($role)) {
                        return;
                    }

                    $allocated = $this->allocatedShare($role, $exceptId);
                    $total = $allocated + (float) $value;

                    if ($total > 100) {
                        $fail(__('acquisition.parties.share_exceeded', [
                            'role' => AcquisitionPartyRole::labelFor($role),
                            'total' => $total,
                            'allocated' => $allocated,
                            'remaining' => max(0, 100 - $allocated),
                        ]));
                    }
                },
            ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('party_id')
                ->label(__('Party'))
                ->options(fn (): array => $this->externalPartyOptions())
                ->searchable()
                ->preload()
                ->required(),
            $this->roleField(),
            $this->shareField(),
            Forms\Components\Textarea::make('notes')->label(__('Notes')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('party.name')
                    ->label(__('Party')),
                Tables\Columns\TextColumn::make('role')
                    ->label(__('Role'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => AcquisitionPartyRole::labelFor($state)),
                Tables\Columns\TextColumn::make('share_percentage')
                    ->label(__('Share %'))
                    ->suffix('%'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addParty')
                    ->label(__('Add Party'))
                    ->visible(fn (): bool => auth()->user()->can('create_acquisition_party'))
                    ->form([
                        Forms\Components\Select::make('party_source')
                            ->label(__('Party Source'))
                            ->options([
                                'existing_party' => __('Existing Party'),
                                'current_company' => __('Current Company'),
                            ])
                            ->default('existing_party')
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('party_id')
                            ->label(__('Party'))
                            ->options(fn (): array => $this->externalPartyOptions())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get): bool => $get('party_source') !== 'current_company')
                            ->required(fn (Forms\Get $get): bool => $get('party_source') !== 'current_company')
                            ->rules([
                                fn (Forms\Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    $role = $get('role');

                                    if ($role && $this->getOwnerRecord()->acquisitionParties()->where('party_id', $value)->where('role', $role)->exists()) {
                                        $fail(__('acquisition.errors.party_already_attached', ['role' => AcquisitionPartyRole::labelFor($role)]));
                                    }
                                },
                            ]),
                        $this->roleField(),
                        $this->shareField(),
                        Forms\Components\Textarea::make('notes')->label(__('Notes')),
                    ])
                    ->action(function (array $data) {
                        try {
                            $acquisition = $this->getOwnerRecord();
                            $company = Company::query()->findOrFail($acquisition->company_id);
                            if (($data['party_source'] ?? 'existing_party') === 'existing_party'
                                && (int) ($data['party_id'] ?? 0) === (int) $company->self_party_id) {
                                throw ValidationException::withMessages([
                                    'party_id' => __('Select Current Company to add your company to this acquisition.'),
                                ]);
                            }
                            $party = ($data['party_source'] ?? 'existing_party') === 'current_company'
                                ? app(PropertyAcquisitionService::class)->selfPartyForCompany($company)
                                : Party::withoutGlobalScopes()->findOrFail($data['party_id']);

                            return app(PropertyAcquisitionService::class)->attachParty(
                                auth()->user(),
                                $acquisition,
                                $party,
                                $data['role'],
                                collect($data)->except(['party_source', 'party_id', 'role'])->all(),
                            );
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title(__('Cannot Add Party'))
                                ->body(collect($e->errors())->flatten()->first())
                                ->send();

                            return null;
                        } catch (AuthorizationException) {
                            Notification::make()
                                ->danger()
                                ->title(__('Unauthorized'))
                                ->body(__('You do not have permission to add parties to this acquisition.'))
                                ->send();

                            return null;
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('editParty')
                    ->label(__('Edit'))
                    ->visible(fn (): bool => auth()->user()->can('update_acquisition_party'))
                    ->fillForm(fn (AcquisitionParty $record): array => $record->only(['role', 'share_percentage', 'notes']))
                    ->form(fn (AcquisitionParty $record): array => [
                        $this->roleField(),
                        $this->shareField($record->id),
                        Forms\Components\Textarea::make('notes')->label(__('Notes')),
                    ])
                    ->action(function (AcquisitionParty $record, array $data) {
                        try {
                            return app(PropertyAcquisitionService::class)->updateAcquisitionParty(auth()->user(), $record, $data);
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title(__('Cannot Update Party'))
                                ->body(collect($e->errors())->flatten()->first())->send();
                            return null;
                        } catch (AuthorizationException) {
                            Notification::make()->danger()->title(__('Unauthorized'))
                                ->body(__('You do not have permission to update parties in this acquisition.'))->send();
                            return null;
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
