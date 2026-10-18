<?php

namespace App\Filament\Resources;

use App\Enums\{OfferStatus, OpportunityType, ReservationSource, ReservationStatus, RentFrequency, UnitStatus};
use App\Filament\Resources\UnitReservationResource\{Pages, RelationManagers};
use App\Models\{Company, CrmOffer, Party, Unit, UnitReservation};
use App\Services\ReservationWorkflowService;
use Filament\Forms;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class UnitReservationResource extends Resource
{
    protected static ?string $model = UnitReservation::class;
    protected static ?string $navigationIcon = 'heroicon-o-bookmark-square';
    protected static ?int $navigationSort = 6;
    public static function getNavigationGroup(): ?string { return '💼 '.__('crm.navigation.crm'); }
    public static function getNavigationLabel(): string { return __('reservations.navigation'); }
    public static function getModelLabel(): string { return __('reservations.models.reservation'); }
    public static function getPluralModelLabel(): string { return __('reservations.models.reservations'); }
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder { return parent::getEloquentQuery()->with(['customer', 'unit.property', 'offer', 'createdBy']); }
    public static function form(Forms\Form $form): Forms\Form { return $form->schema(static::formSchema())->columns(2); }
    public static function formSchema(): array
    {
        $is = fn (Forms\Get $get, ReservationSource $source): bool => ($get('source') instanceof ReservationSource ? $get('source')->value : $get('source')) === $source->value;
        return [
            Forms\Components\Select::make('company_id')->label(__('reservations.fields.company'))->options(Company::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id'))->searchable()->visible(fn () => auth()->user()->isSuperAdmin())->required(fn () => auth()->user()->isSuperAdmin())->live()->disabled(fn (?UnitReservation $record) => $record !== null),
            Forms\Components\Select::make('source')->label(__('reservations.fields.source'))->options(ReservationSource::options())->required()->default(ReservationSource::Direct->value)->live()->disabled(fn (?UnitReservation $record) => $record !== null),
            Forms\Components\Select::make('customer_party_id')->label(__('reservations.fields.customer'))->options(fn (Forms\Get $get) => Party::withoutGlobalScopes()->where('company_id', static::companyId($get) ?: 0)->where('is_active', true)->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
            Forms\Components\Select::make('offer_id')->label(__('reservations.fields.offer'))->options(fn (Forms\Get $get) => static::acceptedOfferOptions(static::companyId($get)))->searchable()->live()->visible(fn (Forms\Get $get) => $is($get, ReservationSource::AcceptedOffer))->required(fn (Forms\Get $get) => $is($get, ReservationSource::AcceptedOffer))->disabled(fn (?UnitReservation $record) => $record !== null)->helperText(fn (Forms\Get $get) => static::acceptedOfferRequiresManualUnit($get('offer_id')) ? __('reservations.help.accepted_offer_without_listing') : __('reservations.help.accepted_offer')),
            Forms\Components\Select::make('unit_id')->label(__('reservations.fields.unit'))->options(fn (Forms\Get $get) => static::availableReadyUnitOptions(static::companyId($get)))->searchable()->visible(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) || ($is($get, ReservationSource::AcceptedOffer) && static::acceptedOfferRequiresManualUnit($get('offer_id'))))->required(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) || ($is($get, ReservationSource::AcceptedOffer) && static::acceptedOfferRequiresManualUnit($get('offer_id')))),
            Forms\Components\Select::make('transaction_type')->label(__('reservations.fields.transaction_type'))->options(OpportunityType::options())->required()->visible(fn (Forms\Get $get) => $is($get, ReservationSource::Direct))->live(),
            Forms\Components\TextInput::make('agreed_amount')->label(__('reservations.fields.agreed_amount'))->numeric()->minValue(0.01)->required()->visible(fn (Forms\Get $get) => $is($get, ReservationSource::Direct)),
            Forms\Components\TextInput::make('currency')->label(__('reservations.fields.currency'))->length(3)->default('USD')->required()->visible(fn (Forms\Get $get) => $is($get, ReservationSource::Direct)),
            Forms\Components\TextInput::make('down_payment_amount')->label(__('reservations.fields.down_payment_amount'))->numeric()->minValue(0)->visible(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) && ($get('transaction_type') === OpportunityType::Sale->value)),
            Forms\Components\TextInput::make('payment_method')->label(__('reservations.fields.payment_method'))->visible(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) && ($get('transaction_type') === OpportunityType::Sale->value)),
            Forms\Components\Textarea::make('payment_terms')->label(__('reservations.fields.payment_terms'))->visible(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) && ($get('transaction_type') === OpportunityType::Sale->value))->columnSpanFull(),
            Forms\Components\Select::make('rent_frequency')->label(__('reservations.fields.rent_frequency'))->options(RentFrequency::options())->visible(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) && ($get('transaction_type') === OpportunityType::Rent->value))->required(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) && ($get('transaction_type') === OpportunityType::Rent->value)),
            Forms\Components\TextInput::make('lease_term_value')->label(__('reservations.fields.lease_term_value'))->numeric()->integer()->minValue(1)->visible(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) && ($get('transaction_type') === OpportunityType::Rent->value))->required(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) && ($get('transaction_type') === OpportunityType::Rent->value)),
            Forms\Components\Select::make('lease_term_unit')->label(__('reservations.fields.lease_term_unit'))->options(['month' => __('crm.offers.lease_units.month'), 'year' => __('crm.offers.lease_units.year')])->visible(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) && ($get('transaction_type') === OpportunityType::Rent->value))->required(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) && ($get('transaction_type') === OpportunityType::Rent->value)),
            Forms\Components\TextInput::make('deposit_amount')->label(__('reservations.fields.deposit_amount'))->numeric()->minValue(0)->visible(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) && ($get('transaction_type') === OpportunityType::Rent->value)),
            Forms\Components\DateTimePicker::make('valid_until')->label(__('reservations.fields.valid_until'))->minDate(now()),
            Forms\Components\Placeholder::make('number_display')->label(__('reservations.fields.number'))->content(fn (?UnitReservation $record) => $record?->reservation_number ?? __('reservations.help.number_generated'))->visible(fn (?UnitReservation $record) => $record !== null),
        ];
    }
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('reservation_number')->label(__('reservations.fields.number'))->searchable()->sortable()->weight('bold'),
            Tables\Columns\TextColumn::make('customer.name')->label(__('reservations.fields.customer'))->searchable(),
            Tables\Columns\TextColumn::make('unit.unit_number')->label(__('reservations.fields.unit'))->searchable(),
            Tables\Columns\TextColumn::make('source')->label(__('reservations.fields.source'))->badge()->formatStateUsing(fn ($state) => $state instanceof ReservationSource ? $state->getLabel() : __('reservations.sources.'.$state)),
            Tables\Columns\TextColumn::make('transaction_type')->label(__('reservations.fields.transaction_type'))->badge()->formatStateUsing(fn ($state) => $state instanceof OpportunityType ? $state->getLabel() : __('crm.opportunity_types.'.$state)),
            Tables\Columns\TextColumn::make('agreed_amount')->label(__('reservations.fields.agreed_amount'))->money(fn (UnitReservation $record) => $record->currency),
            Tables\Columns\TextColumn::make('status')->label(__('crm.fields.status'))->badge()->formatStateUsing(fn ($state) => $state instanceof ReservationStatus ? $state->getLabel() : __('reservations.statuses.'.$state))->color(fn ($state) => $state instanceof ReservationStatus ? $state->color() : ReservationStatus::tryFrom((string) $state)?->color()),
            Tables\Columns\TextColumn::make('valid_until')->label(__('reservations.fields.valid_until'))->dateTime()->placeholder('—'),
        ])->filters([Tables\Filters\SelectFilter::make('status')->label(__('crm.fields.status'))->options(ReservationStatus::options()), Tables\Filters\SelectFilter::make('source')->label(__('reservations.fields.source'))->options(ReservationSource::options())])->actions([
            Tables\Actions\ViewAction::make()->label(__('crm.actions.details')),
            Tables\Actions\EditAction::make()->label(__('crm.actions.edit'))->visible(fn (UnitReservation $record) => $record->status === ReservationStatus::Draft),
            Tables\Actions\ActionGroup::make([
                static::workflowAction('send', __('reservations.actions.send_for_approval'), 'info'),
                static::workflowAction('approve', __('reservations.actions.approve_activate'), 'success'),
                static::workflowAction('reject', __('reservations.actions.reject'), 'danger', true),
                static::workflowAction('cancel', __('reservations.actions.cancel'), 'danger', true),
                static::workflowAction('extend', __('reservations.actions.extend'), 'warning', false, true),
                static::workflowAction('expire', __('reservations.actions.expire'), 'warning'),
                Tables\Actions\DeleteAction::make()->label(__('crm.actions.delete'))->visible(fn (UnitReservation $record) => $record->status === ReservationStatus::Draft),
            ])->visible(fn (UnitReservation $record): bool => static::hasAvailableWorkflowAction($record)
                || ($record->status === ReservationStatus::Draft && auth()->user()->can('delete', $record))),
        ])->defaultSort('created_at', 'desc');
    }
    public static function infolist(Infolist $infolist): Infolist { return $infolist->schema([
        Components\Section::make(__('reservations.sections.overview'))->schema([Components\TextEntry::make('reservation_number')->label(__('reservations.fields.number'))->weight('bold'), Components\TextEntry::make('status')->label(__('crm.fields.status'))->badge()->formatStateUsing(fn ($state) => $state instanceof ReservationStatus ? $state->getLabel() : __('reservations.statuses.'.$state))->color(fn ($state) => $state instanceof ReservationStatus ? $state->color() : ReservationStatus::tryFrom((string) $state)?->color()), Components\TextEntry::make('customer.name')->label(__('reservations.fields.customer')), Components\TextEntry::make('unit.unit_number')->label(__('reservations.fields.unit')), Components\TextEntry::make('valid_until')->label(__('reservations.fields.valid_until'))->dateTime()->placeholder('—')])->columns(3),
        Components\Section::make(__('reservations.sections.commercial_terms'))->schema([Components\TextEntry::make('transaction_type')->label(__('reservations.fields.transaction_type'))->formatStateUsing(fn ($state) => $state instanceof OpportunityType ? $state->getLabel() : __('crm.opportunity_types.'.$state)), Components\TextEntry::make('agreed_amount')->label(__('reservations.fields.agreed_amount'))->money(fn (UnitReservation $record) => $record->currency), Components\TextEntry::make('payment_method')->label(__('reservations.fields.payment_method'))->placeholder('—'), Components\TextEntry::make('rent_frequency')->label(__('reservations.fields.rent_frequency'))->placeholder('—')])->columns(4),
        Components\Section::make(__('reservations.sections.snapshot'))->visible(fn (UnitReservation $record) => $record->snapshot !== null)->schema([
            Components\TextEntry::make('snapshot.created_at')->label(__('reservations.fields.snapshot_created_at'))->dateTime(),
            Components\Section::make(__('reservations.sections.unit_at_activation'))->schema([
                static::snapshotEntry('snapshot_property_name', 'unit_data', 'property_name', 'property'),
                static::snapshotEntry('snapshot_project_name', 'unit_data', 'project_name', 'project'),
                static::snapshotEntry('snapshot_building_name', 'unit_data', 'building_name', 'building'),
                static::snapshotEntry('snapshot_floor_label', 'unit_data', 'floor_label', 'floor'),
                static::snapshotEntry('snapshot_unit_number', 'unit_data', 'unit_number', 'unit'),
                static::snapshotEntry('snapshot_unit_type', 'unit_data', 'type', 'unit_type')->formatStateUsing(fn ($state) => filled($state) ? ucfirst(str_replace('_', ' ', $state)) : '—'),
                static::snapshotEntry('snapshot_actual_area', 'unit_data', 'actual_area', 'actual_area')->suffix(fn (UnitReservation $record) => ' '.(data_get($record->snapshot?->unit_data, 'area_unit') ?? '')),
                static::snapshotEntry('snapshot_bedrooms', 'unit_data', 'bedrooms', 'bedrooms'),
                static::snapshotEntry('snapshot_bathrooms', 'unit_data', 'bathrooms', 'bathrooms'),
                static::snapshotEntry('snapshot_features', 'unit_data', 'features', 'features')->formatStateUsing(fn ($state) => collect($state ?? [])->map(fn (array $feature) => filled($feature['value'] ?? null) ? "{$feature['name']}: ".static::snapshotPlainValue($feature['value']) : ($feature['name'] ?? null))->filter()->implode(', '))->placeholder('—')->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),
            Components\Section::make(__('reservations.sections.commercial_terms_at_activation'))->schema([
                static::snapshotEntry('snapshot_transaction_type', 'commercial_terms', 'transaction_type', 'transaction_type')->formatStateUsing(fn ($state) => OpportunityType::tryFrom((string) $state)?->getLabel() ?? '—'),
                Components\TextEntry::make('snapshot_agreed_amount')->label(__('reservations.fields.agreed_amount'))->state(fn (UnitReservation $record) => data_get($record->snapshot?->commercial_terms, 'agreed_amount'))->money(fn (UnitReservation $record) => data_get($record->snapshot?->commercial_terms, 'currency') ?? 'USD'),
                static::snapshotEntry('snapshot_currency', 'commercial_terms', 'currency', 'currency'),
                static::snapshotEntry('snapshot_down_payment', 'commercial_terms', 'down_payment_amount', 'down_payment_amount'),
                static::snapshotEntry('snapshot_payment_method', 'commercial_terms', 'payment_method', 'payment_method'),
                static::snapshotEntry('snapshot_payment_terms', 'commercial_terms', 'payment_terms', 'payment_terms'),
                static::snapshotEntry('snapshot_rent_frequency', 'commercial_terms', 'rent_frequency', 'rent_frequency'),
                static::snapshotEntry('snapshot_lease_term_value', 'commercial_terms', 'lease_term_value', 'lease_term_value'),
                static::snapshotEntry('snapshot_lease_term_unit', 'commercial_terms', 'lease_term_unit', 'lease_term_unit'),
                static::snapshotEntry('snapshot_deposit', 'commercial_terms', 'deposit_amount', 'deposit_amount'),
                static::snapshotEntry('snapshot_valid_until', 'commercial_terms', 'valid_until', 'valid_until')->dateTime(),
                static::snapshotEntry('snapshot_additional_terms', 'commercial_terms', 'additional_terms', 'additional_terms')->formatStateUsing(fn ($state) => static::snapshotPlainValue($state))->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),
        ])->columns(3),
    ]); }
    public static function getRelations(): array { return [RelationManagers\ReservationStatusHistoriesRelationManager::class]; }
    public static function getPages(): array { return ['index' => Pages\ListUnitReservations::route('/'), 'create' => Pages\CreateUnitReservation::route('/create'), 'view' => Pages\ViewUnitReservation::route('/{record}'), 'edit' => Pages\EditUnitReservation::route('/{record}/edit')]; }
    public static function companyId(Forms\Get $get): ?int { return auth()->user()?->isSuperAdmin() ? ($get('company_id') ?: null) : auth()->user()?->company_id; }
    public static function acceptedOfferOptions(?int $companyId): array
    {
        if (! $companyId) return [];
        $offers = CrmOffer::withoutGlobalScopes()->where('company_id', $companyId)->where('status', OfferStatus::Accepted->value)->with(['listing.listable', 'opportunity.lead'])->orderByDesc('closed_at')->get();
        $availableUnits = static::availableReadyUnits($companyId);
        $actualUnitsById = $availableUnits->keyBy('id');
        $actualUnitsByPlannedId = $availableUnits->filter(fn (Unit $unit) => $unit->planned_unit_id !== null)->keyBy('planned_unit_id');

        return $offers->filter(function (CrmOffer $offer) use ($availableUnits, $actualUnitsById, $actualUnitsByPlannedId): bool {
            $listing = $offer->listing;

            if (! $listing) {
                return $availableUnits->isNotEmpty();
            }

            return match ($listing->listable_type) {
                'unit' => $actualUnitsById->has($listing->listable_id),
                'planned_unit' => $actualUnitsByPlannedId->has($listing->listable_id),
                default => false,
            };
        })->mapWithKeys(function (CrmOffer $offer) use ($actualUnitsById, $actualUnitsByPlannedId): array {
            $listing = $offer->listing;
            $actualUnit = $listing?->listable_type === 'unit'
                ? $actualUnitsById->get($listing?->listable_id)?->unit_number
                : $actualUnitsByPlannedId->get($listing?->listable_id)?->unit_number;
            $unit = $actualUnit
                ? __('reservations.labels.actual_unit', ['unit' => $actualUnit])
                : __('reservations.labels.no_listing_select_unit');

            return [$offer->id => __('reservations.offer_option', [
                'reference' => $offer->reference_number,
                'unit' => $unit,
                'lead' => $offer->opportunity?->lead?->name ?? '—',
            ])];
        })->all();
    }

    public static function availableReadyUnitOptions(?int $companyId): array
    {
        if (! $companyId) return [];

        return static::availableReadyUnits($companyId)
            ->sortBy('unit_number')
            ->mapWithKeys(fn (Unit $unit): array => [$unit->id => $unit->unit_number])
            ->all();
    }

    private static function availableReadyUnits(int $companyId): \Illuminate\Support\Collection
    {
        return Unit::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', UnitStatus::Ready->value)
            ->whereDoesntHave('reservations', fn (\Illuminate\Database\Eloquent\Builder $query) => $query
                ->withoutGlobalScopes()
                ->where('status', ReservationStatus::Active->value))
            ->get(['id', 'planned_unit_id', 'unit_number']);
    }

    private static function snapshotEntry(string $name, string $group, string $key, string $label): Components\TextEntry
    {
        return Components\TextEntry::make($name)
            ->label(__('reservations.fields.'.$label))
            ->state(fn (UnitReservation $record) => data_get($record->snapshot?->{$group} ?? [], $key))
            ->placeholder('—');
    }

    private static function snapshotPlainValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? __('reservations.labels.yes') : __('reservations.labels.no');
        }

        if (! is_array($value)) {
            return (string) $value;
        }

        return collect($value)
            ->map(fn ($item, $key): string => is_string($key)
                ? "{$key}: ".static::snapshotPlainValue($item)
                : static::snapshotPlainValue($item))
            ->implode(', ');
    }
    public static function acceptedOfferRequiresManualUnit(?int $offerId): bool
    {
        return $offerId && ! CrmOffer::withoutGlobalScopes()->find($offerId)?->listing_id;
    }
    public static function workflowAction(string $name, string $label, string $color, bool $reason = false, bool $extend = false): Tables\Actions\Action
    {
        return Tables\Actions\Action::make($name)
            ->label($label)
            ->modalHeading($label)
            ->color($color)
            ->tooltip($label)
            ->requiresConfirmation()
            ->form(fn (UnitReservation $record): array => static::workflowForm($record, $reason, $extend))
            ->visible(fn (UnitReservation $record): bool => static::workflowActionAllowed($name, $record)
                && auth()->user()->can(static::workflowAbility($name), $record))
            ->action(function (UnitReservation $record, array $data) use ($name, $label): void {
                try {
                    $service = app(ReservationWorkflowService::class);
                    match ($name) {
                        'send' => $service->sendForApproval(auth()->user(), $record),
                        'approve' => $service->approveAndActivate(auth()->user(), $record),
                        'reject' => $service->rejectRequest(auth()->user(), $record, $data['reason'] ?? null),
                        'cancel' => $service->cancelReservation(auth()->user(), $record, $data['reason'] ?? null),
                        'extend' => $service->extendReservation(auth()->user(), $record, $data['valid_until']),
                        'expire' => $service->expireReservation(auth()->user(), $record),
                    };
                    Notification::make()->success()->title($label)->send();
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title($label)->body(collect($exception->errors())->flatten()->first())->send();
                    throw $exception;
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send();
                }
            });
    }

    public static function workflowAbility(string $name): string
    {
        return match ($name) {
            'send' => 'sendForApproval', 'approve' => 'approveAndActivate', 'reject' => 'rejectRequest',
            'cancel' => 'cancelReservation', 'extend' => 'extendReservation', 'expire' => 'expireReservation',
        };
    }

    public static function workflowActionAllowed(string $name, UnitReservation $record): bool
    {
        $status = $record->status instanceof ReservationStatus
            ? $record->status
            : ReservationStatus::tryFrom((string) $record->status);

        return match ($name) {
            'send' => $status === ReservationStatus::Draft,
            'approve', 'reject' => $status === ReservationStatus::Pending,
            'cancel', 'extend' => $status === ReservationStatus::Active,
            'expire' => $status === ReservationStatus::Active && $record->valid_until !== null && $record->valid_until->isPast(),
        };
    }

    public static function workflowForm(UnitReservation $record, bool $reason, bool $extend): array
    {
        if ($extend) {
            return [Forms\Components\DateTimePicker::make('valid_until')
                ->label(__('reservations.fields.valid_until'))
                ->helperText(__('reservations.help.extension_after_current_expiry', ['date' => $record->valid_until?->format('Y-m-d H:i') ?? '—']))
                ->required()
                ->after($record->valid_until ?? now())];
        }

        return $reason ? [Forms\Components\Textarea::make('reason')->label(__('reservations.fields.reason'))->required()] : [];
    }

    public static function hasAvailableWorkflowAction(UnitReservation $record): bool
    {
        return collect(['send', 'approve', 'reject', 'cancel', 'extend', 'expire'])
            ->contains(fn (string $name): bool => static::workflowActionAllowed($name, $record)
                && auth()->user()->can(static::workflowAbility($name), $record));
    }
}
