<?php

namespace App\Filament\Resources;

use App\Enums\{OfferRevisionStatus, OfferStatus, OpportunityType};
use App\Filament\Resources\CrmOfferResource\{Pages, RelationManagers};
use App\Models\{Company, CrmOffer, Listing, Opportunity, User};
use App\Services\OfferNegotiationService;
use Filament\Forms;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class CrmOfferResource extends Resource
{
    protected static ?string $model = CrmOffer::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';
    protected static ?int $navigationSort = 5;
    public static function getNavigationGroup(): ?string
    {
        return '💼 ' . __('crm.navigation.crm');
    }
    public static function getNavigationLabel(): string
    {
        return __('crm.offers.navigation');
    }
    public static function getModelLabel(): string
    {
        return __('crm.offers.models.offer');
    }
    public static function getPluralModelLabel(): string
    {
        return __('crm.offers.models.offers');
    }
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return static::scopeForViewer(parent::getEloquentQuery())
            ->with(['opportunity.lead', 'listing', 'assignedTo', 'latestRevision'])
            ->withExists(['revisions as has_expired_submitted_revision' => fn (\Illuminate\Database\Eloquent\Builder $query) => $query
                ->where('status', OfferRevisionStatus::Submitted->value)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())]);
    }
    public static function scopeForViewer(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        if ($user && ! $user->isSuperAdmin() && ! $user->can('view_any_crm::offer') && $user->can('view_own_crm_offer')) {
            $query->where('assigned_to', $user->id);
        }
        return $query;
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema(static::formSchema())->columns(2);
    }
    public static function formSchema(): array
    {
        return [
            Forms\Components\Select::make('company_id')
                ->label(__('crm.fields.company'))
                ->options(Company::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id'))
                ->visible(fn() => auth()->user()->isSuperAdmin())
                ->required(fn() => auth()->user()->isSuperAdmin())
                ->live()
                ->disabled(fn(?CrmOffer $record) => $record !== null),
            Forms\Components\Select::make('opportunity_id')
                ->label(__('crm.offers.fields.opportunity'))
                ->options(fn(Forms\Get $get) => Opportunity::withoutGlobalScopes()->where('company_id', static::companyId($get) ?: 0)->with('lead')->orderByDesc('updated_at')->get()->mapWithKeys(fn(Opportunity $opportunity) => [$opportunity->id => static::opportunityLabel($opportunity)]))
                ->searchable()
                ->required()
                ->disabled(fn(?CrmOffer $record) => $record !== null),
            Forms\Components\Select::make('listing_id')
                ->label(__('crm.fields.listing'))
                ->options(fn(Forms\Get $get) => Listing::withoutGlobalScopes()->where('company_id', static::companyId($get) ?: 0)->orderBy('title')->pluck('title', 'id'))
                ->searchable()
                ->disabled(fn(?CrmOffer $record) => $record !== null),
            Forms\Components\Select::make('transaction_type')
                ->label(__('crm.offers.fields.transaction_type'))
                ->options(OpportunityType::options())
                ->required()
                ->disabled(fn(?CrmOffer $record) => $record !== null),
            Forms\Components\Select::make('assigned_to')->label(__('crm.offers.fields.negotiator'))->options(fn(Forms\Get $get) => User::withoutGlobalScopes()->where('company_id', static::companyId($get) ?: 0)->orderBy('name')->pluck('name', 'id'))->searchable(),
            Forms\Components\Placeholder::make('reference_number')->label(__('crm.offers.fields.reference_number'))->content(fn(?CrmOffer $record) => $record?->reference_number ?? __('crm.offers.help.reference_generated'))->visible(fn(?CrmOffer $record) => $record !== null),
            Forms\Components\Placeholder::make('status_display')->label(__('crm.fields.status'))->content(fn(?CrmOffer $record) => $record?->status?->getLabel() ?? OfferStatus::Draft->getLabel())->visible(fn(?CrmOffer $record) => $record !== null),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('reference_number')->label(__('crm.offers.fields.reference_number'))->searchable()->sortable()->weight('bold'),
            Tables\Columns\TextColumn::make('opportunity.lead.name')->label(__('crm.fields.lead'))->searchable(),
            Tables\Columns\TextColumn::make('listing.title')->label(__('crm.fields.listing'))->placeholder('—'),
            Tables\Columns\TextColumn::make('transaction_type')->label(__('crm.offers.fields.transaction_type'))->badge()->formatStateUsing(fn($state) => $state instanceof OpportunityType ? $state->getLabel() : __('crm.opportunity_types.' . $state)),
            Tables\Columns\TextColumn::make('latestRevision.amount')->label(__('crm.offers.fields.latest_amount'))->money(fn(CrmOffer $record) => $record->latestRevision?->currency ?? 'USD')->placeholder('—'),
            Tables\Columns\TextColumn::make('status')->label(__('crm.fields.status'))->badge()->formatStateUsing(fn($state) => $state instanceof OfferStatus ? $state->getLabel() : __('crm.offers.statuses.' . $state))->color(fn($state) => $state instanceof OfferStatus ? $state->color() : OfferStatus::tryFrom((string) $state)?->color()),
            Tables\Columns\TextColumn::make('assignedTo.name')->label(__('crm.offers.fields.negotiator'))->placeholder('—'),
        ])
            ->emptyStateHeading(__('crm.offer_empty.offers_heading'))
            ->emptyStateDescription(__('crm.offer_empty.offers_description'))
            ->emptyStateIcon('heroicon-o-document-currency-dollar')
            ->filters([
                    Tables\Filters\SelectFilter::make('status')->label(__('crm.fields.status'))->options(OfferStatus::options()),
                    Tables\Filters\SelectFilter::make('transaction_type')->label(__('crm.offers.fields.transaction_type'))->options(OpportunityType::options()),
                ])->actions([
                    Tables\Actions\ViewAction::make()->label(__('crm.actions.details')),
                    Tables\Actions\EditAction::make()->label(__('crm.actions.edit'))->modalHeading(__('crm.offer_modals.edit_offer'))->visible(fn(CrmOffer $record) => in_array($record->status, [OfferStatus::Draft, OfferStatus::Negotiating], true)),
                    static::workflowAction('withdraw', __('crm.offers.actions.withdraw'), 'danger'),
                    static::workflowAction('reject', __('crm.offers.actions.close_rejected'), 'danger'),
                    static::workflowAction('expire', __('crm.offers.actions.expire'), 'warning'),
                    Tables\Actions\DeleteAction::make()->label(__('crm.actions.delete'))->modalHeading(__('crm.offer_modals.delete_offer'))->modalDescription(__('crm.offer_modals.delete_offer_description'))->visible(fn (CrmOffer $record): bool => $record->status === OfferStatus::Draft),
                ])->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Components\Section::make(__('crm.offers.sections.overview'))->schema([
                Components\TextEntry::make('reference_number')->label(__('crm.offers.fields.reference_number'))->weight('bold'),
                Components\TextEntry::make('opportunity.lead.name')->label(__('crm.fields.lead')),
                Components\TextEntry::make('listing.title')->label(__('crm.fields.listing'))->placeholder('—'),
                Components\TextEntry::make('transaction_type')->label(__('crm.offers.fields.transaction_type'))->badge()->formatStateUsing(fn($state) => $state instanceof OpportunityType ? $state->getLabel() : __('crm.opportunity_types.' . $state)),
                Components\TextEntry::make('status')->label(__('crm.fields.status'))->badge()->formatStateUsing(fn($state) => $state instanceof OfferStatus ? $state->getLabel() : __('crm.offers.statuses.' . $state))->color(fn($state) => $state instanceof OfferStatus ? $state->color() : OfferStatus::tryFrom((string) $state)?->color()),
                Components\TextEntry::make('assignedTo.name')->label(__('crm.offers.fields.negotiator'))->placeholder('—'),
                Components\TextEntry::make('createdBy.name')->label(__('crm.offers.fields.created_by'))->placeholder('—'),
            ])->columns(3),
            Components\Section::make(__('crm.offers.sections.agreement'))->schema([
                Components\TextEntry::make('agreedRevision.revision_number')->label(__('crm.offers.fields.agreed_revision'))->formatStateUsing(fn($state) => $state ? __('crm.offers.revision_number', ['number' => $state]) : '—'),
                Components\TextEntry::make('agreedRevision.amount')->label(__('crm.offers.fields.agreed_amount'))->money(fn(CrmOffer $record) => $record->agreedRevision?->currency ?? 'USD')->placeholder('—'),
                Components\TextEntry::make('agreedRevision.confirmedBy.name')->label(__('crm.offers.fields.confirmed_by'))->placeholder('—'),
                Components\TextEntry::make('agreedRevision.confirmed_at')->label(__('crm.offers.fields.confirmed_at'))->dateTime()->placeholder('—'),
            ])->columns(4),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OfferRevisionsRelationManager::class,
            RelationManagers\OfferApprovalsRelationManager::class
        ];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmOffers::route('/'),
            'create' => Pages\CreateCrmOffer::route('/create'),
            'view' => Pages\ViewCrmOffer::route('/{record}'),
            'edit' => Pages\EditCrmOffer::route('/{record}/edit')
        ];
    }
    public static function companyId(Forms\Get $get): ?int
    {
        return auth()->user()?->isSuperAdmin() ? ($get('company_id') ?: null) : auth()->user()?->company_id;
    }
    public static function opportunityLabel(Opportunity $opportunity): string
    {
        return collect([$opportunity->lead?->name, $opportunity->listing?->title])->filter()->implode(' — ') ?: __('crm.models.opportunity') . ' #' . $opportunity->id;
    }
    private static function workflowAction(string $action, string $label, string $color): Tables\Actions\Action
    {
        return Tables\Actions\Action::make($action)->label($label)->modalHeading($label)->color($color)->tooltip($label)->requiresConfirmation()
            ->visible(fn(CrmOffer $record) => static::workflowActionAllowed($action, $record)
                && auth()->user()->can($action === 'reject' ? 'close' : $action, $record))
            ->action(function (CrmOffer $record) use ($action, $label): void {
                try {
                    app(OfferNegotiationService::class)->{$action === 'reject' ? 'rejectOffer' : $action . 'Offer'}(auth()->user(), $record);
                    Notification::make()->success()->title($label)->send();
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title($label)->body(collect($exception->errors())->flatten()->first())->send();
                    throw $exception;
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send();
                }
            });
    }

    private static function workflowActionAllowed(string $action, CrmOffer $record): bool
    {
        if (! in_array($record->status, [OfferStatus::Draft, OfferStatus::Negotiating], true)) {
            return false;
        }

        return $action !== 'expire' || (bool) $record->has_expired_submitted_revision;
    }
}
