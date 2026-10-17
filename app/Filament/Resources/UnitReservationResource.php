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
            Forms\Components\Select::make('unit_id')->label(__('reservations.fields.unit'))->options(fn (Forms\Get $get) => Unit::withoutGlobalScopes()->where('company_id', static::companyId($get) ?: 0)->where('status', UnitStatus::Ready->value)->orderBy('unit_number')->pluck('unit_number', 'id'))->searchable()->visible(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) || ($is($get, ReservationSource::AcceptedOffer) && static::acceptedOfferRequiresManualUnit($get('offer_id'))))->required(fn (Forms\Get $get) => $is($get, ReservationSource::Direct) || ($is($get, ReservationSource::AcceptedOffer) && static::acceptedOfferRequiresManualUnit($get('offer_id')))),
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
            Tables\Actions\ViewAction::make()->label(__('crm.actions.details')), Tables\Actions\EditAction::make()->label(__('crm.actions.edit'))->visible(fn (UnitReservation $record) => $record->status === ReservationStatus::Draft), Tables\Actions\DeleteAction::make()->label(__('crm.actions.delete'))->visible(fn (UnitReservation $record) => $record->status === ReservationStatus::Draft),
            static::workflowAction('send', __('reservations.actions.send_for_approval'), 'info'), static::workflowAction('approve', __('reservations.actions.approve_activate'), 'success'), static::workflowAction('reject', __('reservations.actions.reject'), 'danger', true), static::workflowAction('cancel', __('reservations.actions.cancel'), 'danger', true), static::workflowAction('extend', __('reservations.actions.extend'), 'warning', false, true), static::workflowAction('expire', __('reservations.actions.expire'), 'warning'),
        ])->defaultSort('created_at', 'desc');
    }
    public static function infolist(Infolist $infolist): Infolist { return $infolist->schema([
        Components\Section::make(__('reservations.sections.overview'))->schema([Components\TextEntry::make('reservation_number')->label(__('reservations.fields.number'))->weight('bold'), Components\TextEntry::make('status')->label(__('crm.fields.status'))->badge()->formatStateUsing(fn ($state) => $state instanceof ReservationStatus ? $state->getLabel() : __('reservations.statuses.'.$state))->color(fn ($state) => $state instanceof ReservationStatus ? $state->color() : ReservationStatus::tryFrom((string) $state)?->color()), Components\TextEntry::make('customer.name')->label(__('reservations.fields.customer')), Components\TextEntry::make('unit.unit_number')->label(__('reservations.fields.unit')), Components\TextEntry::make('valid_until')->label(__('reservations.fields.valid_until'))->dateTime()->placeholder('—')])->columns(3),
        Components\Section::make(__('reservations.sections.commercial_terms'))->schema([Components\TextEntry::make('transaction_type')->label(__('reservations.fields.transaction_type'))->formatStateUsing(fn ($state) => $state instanceof OpportunityType ? $state->getLabel() : __('crm.opportunity_types.'.$state)), Components\TextEntry::make('agreed_amount')->label(__('reservations.fields.agreed_amount'))->money(fn (UnitReservation $record) => $record->currency), Components\TextEntry::make('payment_method')->label(__('reservations.fields.payment_method'))->placeholder('—'), Components\TextEntry::make('rent_frequency')->label(__('reservations.fields.rent_frequency'))->placeholder('—')])->columns(4),
        Components\Section::make(__('reservations.sections.snapshot'))->visible(fn (UnitReservation $record) => $record->snapshot !== null)->schema([Components\TextEntry::make('snapshot.created_at')->label(__('reservations.fields.snapshot_created_at'))->dateTime(), Components\TextEntry::make('snapshot.unit_data')->label(__('reservations.fields.unit_snapshot'))->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->columnSpanFull(), Components\TextEntry::make('snapshot.commercial_terms')->label(__('reservations.fields.commercial_snapshot'))->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->columnSpanFull()]),
    ]); }
    public static function getRelations(): array { return [RelationManagers\ReservationStatusHistoriesRelationManager::class]; }
    public static function getPages(): array { return ['index' => Pages\ListUnitReservations::route('/'), 'create' => Pages\CreateUnitReservation::route('/create'), 'view' => Pages\ViewUnitReservation::route('/{record}'), 'edit' => Pages\EditUnitReservation::route('/{record}/edit')]; }
    public static function companyId(Forms\Get $get): ?int { return auth()->user()?->isSuperAdmin() ? ($get('company_id') ?: null) : auth()->user()?->company_id; }
    public static function acceptedOfferOptions(?int $companyId): array
    {
        if (! $companyId) return [];
        $offers = CrmOffer::withoutGlobalScopes()->where('company_id', $companyId)->where('status', OfferStatus::Accepted->value)->with(['listing.listable', 'opportunity.lead'])->orderByDesc('closed_at')->get();
        $plannedIds = $offers->filter(fn (CrmOffer $offer) => $offer->listing?->listable_type === 'planned_unit')->pluck('listing.listable_id');
        $actualUnits = Unit::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('planned_unit_id', $plannedIds)->pluck('unit_number', 'planned_unit_id');

        return $offers->mapWithKeys(function (CrmOffer $offer) use ($actualUnits): array {
            $listing = $offer->listing;
            $actualUnit = $listing?->listable_type === 'unit'
                ? $listing->listable?->unit_number
                : $actualUnits->get($listing?->listable_id);
            $unit = $actualUnit
                ? __('reservations.labels.actual_unit', ['unit' => $actualUnit])
                : ($listing?->listable_type === 'planned_unit'
                    ? __('reservations.labels.planned_unit_not_converted', ['unit' => $listing->listable?->code ?? '—'])
                    : __('reservations.labels.no_listing_select_unit'));

            return [$offer->id => __('reservations.offer_option', [
                'reference' => $offer->reference_number,
                'listing' => $listing?->title ?? __('reservations.labels.no_listing'),
                'unit' => $unit,
                'lead' => $offer->opportunity?->lead?->name ?? '—',
            ])];
        })->all();
    }
    public static function acceptedOfferRequiresManualUnit(?int $offerId): bool
    {
        return $offerId && ! CrmOffer::withoutGlobalScopes()->find($offerId)?->listing_id;
    }
    public static function workflowAction(string $name, string $label, string $color, bool $reason = false, bool $extend = false): Tables\Actions\Action { return Tables\Actions\Action::make($name)->label($label)->modalHeading($label)->color($color)->tooltip($label)->requiresConfirmation()->form($extend ? [Forms\Components\DateTimePicker::make('valid_until')->label(__('reservations.fields.valid_until'))->required()->minDate(now())] : ($reason ? [Forms\Components\Textarea::make('reason')->label(__('reservations.fields.reason'))->required()] : []))->visible(fn (UnitReservation $record) => auth()->user()->can(match ($name) {'send' => 'sendForApproval', 'approve' => 'approveAndActivate', 'reject' => 'rejectRequest', 'cancel' => 'cancelReservation', 'extend' => 'extendReservation', 'expire' => 'expireReservation'}, $record))->action(function (UnitReservation $record, array $data) use ($name, $label): void { try { $service = app(ReservationWorkflowService::class); match ($name) {'send' => $service->sendForApproval(auth()->user(), $record), 'approve' => $service->approveAndActivate(auth()->user(), $record), 'reject' => $service->rejectRequest(auth()->user(), $record, $data['reason'] ?? null), 'cancel' => $service->cancelReservation(auth()->user(), $record, $data['reason'] ?? null), 'extend' => $service->extendReservation(auth()->user(), $record, $data['valid_until']), 'expire' => $service->expireReservation(auth()->user(), $record)}; Notification::make()->success()->title($label)->send(); } catch (ValidationException $exception) { Notification::make()->danger()->title($label)->body(collect($exception->errors())->flatten()->first())->send(); throw $exception; } catch (AuthorizationException) { Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send(); } }); }
}
