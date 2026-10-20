<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractResource\Pages;
use App\Filament\Resources\ContractResource\RelationManagers;
use App\Enums\{ContractStatus, ContractType};
use App\Models\{Company, Contract, ContractTemplateVersion, Party, ProjectPlannedUnit, Property, Unit, UnitReservation};
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = '💼 Sales & Marketing';

    public static function getNavigationGroup(): ?string
    {
        return __('💼 Sales & Marketing');
    }

    public static function getNavigationLabel(): string
    {
        return __('Contracts');
    }

    public static function getModelLabel(): string
    {
        return __('Contract');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Contracts');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['property:id,name', 'unit:id,unit_number', 'plannedUnit:id,code']);
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('company_id')
                ->label(__('Company'))
                ->options(fn () => Company::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id'))
                ->visible(fn (): bool => auth()->user()->isSuperAdmin())
                ->required(fn (): bool => auth()->user()->isSuperAdmin())
                ->live(),
            Forms\Components\Select::make('contract_type')
                ->label(__('Contract type'))
                ->options(ContractType::options())
                ->required()
                ->live(),
            Forms\Components\Select::make('reservation_id')
                ->label(__('Source reservation'))
                ->options(fn (Get $get): array => UnitReservation::withoutGlobalScopes()
                    ->with('unit.property')
                    ->where('company_id', self::formCompanyId($get))
                    ->where('status', \App\Enums\ReservationStatus::Active->value)
                    ->orderByDesc('id')
                    ->get()
                    ->mapWithKeys(fn (UnitReservation $reservation): array => [
                        $reservation->id => trim($reservation->reservation_number.' — '.($reservation->unit?->unit_number ?? __('Actual unit')).' — '.($reservation->unit?->property?->name ?? '')),
                    ])->all())
                ->searchable()
                ->preload()
                ->disabled(fn (Get $get): bool => ! self::formCompanyId($get))
                ->live()
                ->afterStateUpdated(function ($state, Set $set): void {
                    if (! $state) {
                        return;
                    }
                    $reservation = UnitReservation::withoutGlobalScopes()->with('unit')->find($state);
                    if ($reservation?->unit) {
                        $set('unit_id', $reservation->unit_id);
                        $set('property_id', $reservation->unit->property_id);
                        $set('project_id', $reservation->unit->project_id);
                        $set('planned_unit_id', $reservation->unit->planned_unit_id);
                    }
                })
                ->helperText(__('Optional. A reservation supplies its actual unit and commercial snapshot.')),
            Forms\Components\Select::make('property_id')
                ->label(__('Property'))
                ->options(fn (Get $get): array => Property::withoutGlobalScopes()
                    ->where('company_id', self::formCompanyId($get))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->disabled(fn (Get $get): bool => ! self::formCompanyId($get))
                // A Property selection is the only event that reloads the
                // dependent actual-unit options; searching the property field
                // itself does not repeatedly query the Units table.
                ->live()
                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                    $currentUnitId = $get('unit_id');
                    if ($currentUnitId && ! Unit::withoutGlobalScopes()
                        ->whereKey($currentUnitId)
                        ->where('property_id', $state)
                        ->exists()) {
                        $set('unit_id', null);
                    }

                    $reservationId = $get('reservation_id');
                    if ($reservationId && ! UnitReservation::withoutGlobalScopes()
                        ->whereKey($reservationId)
                        ->whereHas('unit', fn (Builder $query) => $query->where('property_id', $state))
                        ->exists()) {
                        $set('reservation_id', null);
                    }
                }),
            Forms\Components\Select::make('unit_id')
                ->label(__('Actual unit'))
                ->options(function (Get $get): array {
                    $propertyId = $get('property_id');
                    if (! $propertyId || ! self::formCompanyId($get)) {
                        return [];
                    }

                    return Unit::withoutGlobalScopes()
                        ->where('company_id', self::formCompanyId($get))
                        ->where('property_id', $propertyId)
                        ->orderBy('unit_number')
                        ->pluck('unit_number', 'id')
                        ->all();
                })
                ->searchable()
                ->required(fn (Get $get): bool => ContractType::tryFrom((string) $get('contract_type'))?->requiresActualUnit() ?? false)
                ->disabled(fn (Get $get): bool => ! self::formCompanyId($get) || ! $get('property_id'))
                ->helperText(__('Select a property to load its actual units.')),
            Forms\Components\Select::make('planned_unit_id')
                ->label(__('Planned unit'))
                ->options(fn (Get $get): array => ProjectPlannedUnit::withoutGlobalScopes()
                    ->with('floor.building.project')
                    ->where('company_id', self::formCompanyId($get))
                    ->orderBy('code')
                    ->get()
                    ->mapWithKeys(fn (ProjectPlannedUnit $unit): array => [
                        $unit->id => trim($unit->code.' — '.($unit->floor?->building?->project?->name ?? __('No project')).' — '.($unit->floor?->building?->name ?? __('No building'))),
                    ])->all())
                ->searchable()
                ->preload()
                ->required(fn (Get $get): bool => ContractType::tryFrom((string) $get('contract_type'))?->requiresPlannedUnit() ?? false)
                ->disabled(fn (Get $get): bool => ! self::formCompanyId($get)),
            Forms\Components\Select::make('template_version_id')
                ->label(__('Template version'))
                ->options(fn (Get $get): array => ContractTemplateVersion::withoutGlobalScopes()
                    ->with('template:id,name')
                    ->where('company_id', self::formCompanyId($get))
                    ->orderByDesc('id')
                    ->get()
                    ->mapWithKeys(fn (ContractTemplateVersion $version): array => [
                        $version->id => ($version->template?->name ?? __('Template')).' — V'.$version->version_number,
                    ])->all())
                ->searchable()
                ->preload()
                ->disabled(fn (Get $get): bool => ! self::formCompanyId($get)),
            Forms\Components\TextInput::make('total_amount')
                ->label(__('Total amount'))
                ->numeric()
                ->minValue(0),
            Forms\Components\TextInput::make('currency')
                ->label(__('Currency'))
                ->default('USD')
                ->length(3),
            Forms\Components\DatePicker::make('start_date')->label(__('Start date')),
            Forms\Components\DatePicker::make('end_date')->label(__('End date')),
            Forms\Components\DatePicker::make('delivery_date')->label(__('Delivery date')),
            Forms\Components\Repeater::make('parties')
                ->label(__('Contract parties'))
                ->schema([
                    Forms\Components\Select::make('party_id')
                        ->label(__('Party'))
                        ->options(fn (Get $get): array => Party::withoutGlobalScopes()
                            ->where('company_id', self::formCompanyId($get))
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    Forms\Components\Select::make('role')
                        ->label(__('Role'))
                        ->options([
                            'buyer' => __('Buyer'),
                            'seller' => __('Seller'),
                            'landlord' => __('Landlord'),
                            'tenant' => __('Tenant'),
                            'guarantor' => __('Guarantor'),
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('share_percentage')
                        ->label(__('Share percentage'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100),
                ])
                ->columns(3)
                ->defaultItems(1)
                ->addActionLabel(__('Add party')),
            Forms\Components\Repeater::make('terms')
                ->label(__('Contract terms'))
                ->schema([
                    Forms\Components\Select::make('term_type')
                        ->label(__('Term type'))
                        ->options(['clause' => __('Clause'), 'commercial' => __('Commercial term'), 'legal' => __('Legal term')])
                        ->default('clause')
                        ->required(),
                    Forms\Components\TextInput::make('title')->label(__('Term title'))->required(),
                    Forms\Components\Textarea::make('text')->label(__('Term text'))->columnSpanFull(),
                ])
                ->columns(2)
                ->addActionLabel(__('Add term')),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contract_number')->label(__('Contract number'))->searchable(),
                Tables\Columns\TextColumn::make('contract_type')->label(__('Contract type'))->formatStateUsing(fn ($state): string => $state instanceof ContractType ? $state->getLabel() : ContractType::tryFrom((string) $state)?->getLabel() ?? (string) $state)->badge(),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->formatStateUsing(fn ($state): string => $state instanceof ContractStatus ? $state->getLabel() : ContractStatus::tryFrom((string) $state)?->getLabel() ?? (string) $state)->color(fn ($state): ?string => $state instanceof ContractStatus ? $state->color() : ContractStatus::tryFrom((string) $state)?->color())->badge(),
                Tables\Columns\TextColumn::make('created_at')->label(__('Created at'))->dateTime(),
            ])
            ->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Tabs::make(__('Contract overview'))
                ->tabs([
                    Infolists\Components\Tabs\Tab::make(__('Overview'))
                        ->schema([
                            Infolists\Components\TextEntry::make('contract_number')->label(__('Contract number'))->copyable(),
                            Infolists\Components\TextEntry::make('contract_type')->label(__('Contract type'))->formatStateUsing(fn ($state): string => $state instanceof ContractType ? $state->getLabel() : ContractType::tryFrom((string) $state)?->getLabel() ?? (string) $state)->badge(),
                            Infolists\Components\TextEntry::make('status')->label(__('Status'))->formatStateUsing(fn ($state): string => $state instanceof ContractStatus ? $state->getLabel() : ContractStatus::tryFrom((string) $state)?->getLabel() ?? (string) $state)->color(fn ($state): ?string => $state instanceof ContractStatus ? $state->color() : ContractStatus::tryFrom((string) $state)?->color())->badge(),
                            Infolists\Components\TextEntry::make('createdBy.name')->label(__('Created by'))->placeholder('—'),
                            Infolists\Components\TextEntry::make('created_at')->label(__('Created at'))->dateTime(),
                        ])->columns(3),
                    Infolists\Components\Tabs\Tab::make(__('Target'))
                        ->schema([
                            Infolists\Components\TextEntry::make('reservation.reservation_number')->label(__('Source reservation'))->placeholder('—'),
                            Infolists\Components\TextEntry::make('property.name')->label(__('Property'))->placeholder('—'),
                            Infolists\Components\TextEntry::make('project.name')->label(__('Project'))->placeholder('—'),
                            Infolists\Components\TextEntry::make('unit.unit_number')->label(__('Actual unit'))->placeholder('—'),
                            Infolists\Components\TextEntry::make('plannedUnit.code')->label(__('Planned unit'))->placeholder('—'),
                        ])->columns(3),
                    Infolists\Components\Tabs\Tab::make(__('Documents'))
                        ->schema([
                            Infolists\Components\RepeatableEntry::make('documents')
                                ->label(__('Attached documents'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('title')->label(__('Document title'))->weight('bold'),
                                    Infolists\Components\TextEntry::make('latestVersion.version_number')->label(__('Version'))->prefix('V')->badge(),
                                    Infolists\Components\TextEntry::make('latestVersion.file_name')->label(__('File name')),
                                    Infolists\Components\TextEntry::make('latestVersion.created_at')->label(__('Uploaded at'))->dateTime(),
                                ])
                                ->columns(4)
                                ->placeholder(__('No legal documents attached to this contract yet.')),
                        ]),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'view' => Pages\ViewContract::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VersionsRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\ApprovalsRelationManager::class,
            RelationManagers\SignaturesRelationManager::class,
            RelationManagers\StatusHistoriesRelationManager::class,
            RelationManagers\AmendmentsRelationManager::class,
        ];
    }

    private static function formCompanyId(Get $get): ?int
    {
        return auth()->user()->isSuperAdmin()
            ? ($get('company_id') ? (int) $get('company_id') : null)
            : auth()->user()->company_id;
    }
}
