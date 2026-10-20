<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Models\Company;
use App\Models\Party;
use App\Services\PropertyAcquisitionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OwnershipsRelationManager extends RelationManager
{
    protected static bool $isLazy = true;
    protected static string $relationship = 'ownerships';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Ownership History');
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('party.name')->label(__('Owner')),
            Tables\Columns\TextColumn::make('ownership_percentage')->label(__('Ownership %'))->suffix('%'),
            Tables\Columns\TextColumn::make('start_date')->label(__('Start Date'))->date(),
            Tables\Columns\TextColumn::make('end_date')->label(__('End Date'))->date()->placeholder(__('Active')),
        ])->headerActions([
            Tables\Actions\Action::make('createOwnership')
                ->label(__('Create ownership'))
                ->visible(fn (): bool => auth()->user()->can('create', \App\Models\PropertyOwnership::class))
                ->form([
                    Forms\Components\DatePicker::make('start_date')->label(__('Start Date'))->required(),
                    Forms\Components\Repeater::make('owners')->label(__('Owners'))->schema([
                        Forms\Components\Select::make('owner_type')
                            ->label(__('Owner Type'))
                            ->options(['party' => __('Party'), 'company' => __('Company')])
                            ->default('party')
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('party_id')
                            ->label(__('Party'))
                            ->options(fn (): array => Party::withoutGlobalScopes()
                                ->where('company_id', $this->getOwnerRecord()->company_id)
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get): bool => $get('owner_type') === 'party')
                            ->required(fn (Forms\Get $get): bool => $get('owner_type') === 'party'),
                        Forms\Components\Select::make('company_id')
                            ->label(__('Company'))
                            ->options(fn (): array => Company::query()->whereKey($this->getOwnerRecord()->company_id)->pluck('name', 'id')->all())
                            ->visible(fn (Forms\Get $get): bool => $get('owner_type') === 'company')
                            ->required(fn (Forms\Get $get): bool => $get('owner_type') === 'company'),
                        Forms\Components\TextInput::make('percentage')->label(__('Percentage'))->numeric()->minValue(0.01)->maxValue(100)->required(),
                        Forms\Components\Textarea::make('notes')->label(__('Notes')),
                    ])->required(),
                ])
                ->action(function (array $data) {
                    $service = app(PropertyAcquisitionService::class);
                    $property = $this->getOwnerRecord();
                    $owners = collect($data['owners'])->map(function (array $owner) use ($property, $service): array {
                        $party = $owner['owner_type'] === 'company'
                            ? $service->partyForCompany($property, Company::findOrFail($owner['company_id']))
                            : Party::withoutGlobalScopes()->findOrFail($owner['party_id']);

                        return ['party' => $party, 'percentage' => $owner['percentage'], 'notes' => $owner['notes'] ?? null];
                    })->all();

                    return $service->replaceOwnership(auth()->user(), $property, $owners, $data['start_date']);
                }),
        ]);
    }
}
