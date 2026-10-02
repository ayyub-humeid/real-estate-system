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
    protected static ?string $title = 'Ownership History';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('party.name'),
            Tables\Columns\TextColumn::make('ownership_percentage')->suffix('%'),
            Tables\Columns\TextColumn::make('start_date')->date(),
            Tables\Columns\TextColumn::make('end_date')->date()->placeholder('Active'),
        ])->headerActions([
            Tables\Actions\Action::make('createOwnership')
                ->label('Create ownership')
                ->visible(fn (): bool => auth()->user()->can('create', \App\Models\PropertyOwnership::class))
                ->form([
                    Forms\Components\DatePicker::make('start_date')->required(),
                    Forms\Components\Repeater::make('owners')->schema([
                        Forms\Components\Select::make('owner_type')
                            ->options(['party' => 'Party', 'company' => 'Company'])
                            ->default('party')
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('party_id')
                            ->options(fn (): array => Party::withoutGlobalScopes()
                                ->where('company_id', $this->getOwnerRecord()->company_id)
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get): bool => $get('owner_type') === 'party')
                            ->required(fn (Forms\Get $get): bool => $get('owner_type') === 'party'),
                        Forms\Components\Select::make('company_id')
                            ->options(fn (): array => Company::query()->whereKey($this->getOwnerRecord()->company_id)->pluck('name', 'id')->all())
                            ->visible(fn (Forms\Get $get): bool => $get('owner_type') === 'company')
                            ->required(fn (Forms\Get $get): bool => $get('owner_type') === 'company'),
                        Forms\Components\TextInput::make('percentage')->numeric()->minValue(0.01)->maxValue(100)->required(),
                        Forms\Components\Textarea::make('notes'),
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
