<?php

namespace App\Filament\Resources\CrmOfferResource\RelationManagers;

use App\Enums\{OfferApprovalStatus, OfferPaymentMethod, OfferProposedBySide, OfferRevisionStatus, OpportunityType, RentFrequency};
use App\Models\{CrmOffer, CrmOfferRevision};
use App\Services\OfferNegotiationService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class OfferRevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';
    public static function getModelLabel(): string { return __('crm.resource_models.offer_revision'); }
    public static function getPluralModelLabel(): string { return __('sales.price_history.title'); }
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('sales.price_history.title');
    }
    /** Lets the offer page refresh this tab right after one of its buttons changes something. */
    #[\Livewire\Attributes\On('offer-changed')]
    public function refreshAfterOfferChange(): void
    {
    }

    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema($this->formSchema());
    }
    private function formSchema(): array
    {
        $offer = $this->getOwnerRecord();
        return [
            Forms\Components\Select::make('proposed_by_side')
                ->label(__('crm.offers.fields.proposed_by_side'))
                ->options(OfferProposedBySide::options())
                ->required(),
            Forms\Components\TextInput::make('amount')
                ->label(__('crm.offers.fields.amount'))
                ->numeric()
                ->minValue(0.01)
                ->required(),
            Forms\Components\TextInput::make('currency')
                ->label(__('crm.fields.currency'))
                ->length(3)
                ->required()
                ->default('USD'),
            Forms\Components\DateTimePicker::make('expires_at')
                ->label(__('crm.offers.fields.expires_at'))
                ->minDate(now()),
            Forms\Components\TextInput::make('down_payment_amount')
                ->label(__('crm.offers.fields.down_payment_amount'))
                ->numeric()
                ->minValue(0)
                ->visible($offer->transaction_type === OpportunityType::Sale),
            Forms\Components\Select::make('payment_method')
                ->label(__('crm.offers.fields.payment_method'))
                ->options(OfferPaymentMethod::options())
                ->native(false)
                ->visible($offer->transaction_type === OpportunityType::Sale),
            Forms\Components\Textarea::make('payment_terms')
                ->label(__('crm.offers.fields.payment_terms'))
                ->visible($offer->transaction_type === OpportunityType::Sale)
                ->columnSpanFull(),
            Forms\Components\Select::make('rent_frequency')
                ->label(__('crm.offers.fields.rent_frequency'))
                ->options(RentFrequency::options())
                ->required($offer->transaction_type === OpportunityType::Rent)
                ->visible($offer->transaction_type === OpportunityType::Rent),
            Forms\Components\TextInput::make('lease_term_value')
                ->label(__('crm.offers.fields.lease_term_value'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->required($offer->transaction_type === OpportunityType::Rent)
                ->visible($offer->transaction_type === OpportunityType::Rent),
            Forms\Components\Select::make('lease_term_unit')
                ->label(__('crm.offers.fields.lease_term_unit'))
                ->options(['month' => __('crm.offers.lease_units.month'), 'year' => __('crm.offers.lease_units.year')])
                ->required($offer->transaction_type === OpportunityType::Rent)
                ->visible($offer->transaction_type === OpportunityType::Rent),
            Forms\Components\TextInput::make('deposit_amount')
                ->label(__('crm.offers.fields.deposit_amount'))
                ->numeric()
                ->minValue(0)
                ->visible($offer->transaction_type === OpportunityType::Rent),
            Forms\Components\Textarea::make('notes')->label(__('crm.fields.notes'))->columnSpanFull(),
        ];
    }
    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists([
                'approvals as has_pending_approval' => fn (Builder $query): Builder => $query
                    ->where('status', OfferApprovalStatus::Pending->value),
            ]))
            ->columns([
            Tables\Columns\TextColumn::make('revision_number')
                ->label(__('crm.offers.fields.revision'))
                ->formatStateUsing(fn($state) => __('crm.offers.revision_number', ['number' => $state]))
                ->sortable(),
            Tables\Columns\TextColumn::make('proposed_by_side')
                ->label(__('crm.offers.fields.proposed_by_side'))
                ->badge()
                ->formatStateUsing(fn($state) => $state instanceof OfferProposedBySide ? $state->getLabel() : __('crm.offers.proposed_sides.' . $state)),
            Tables\Columns\TextColumn::make('amount')
                ->label(__('crm.offers.fields.amount'))
                ->money(fn(CrmOfferRevision $record) => $record->currency),
            Tables\Columns\TextColumn::make('status')
                ->label(__('crm.fields.status'))
                ->badge()
                ->formatStateUsing(fn ($state, CrmOfferRevision $record) => $record->has_pending_approval
                    ? __('crm.offers.approval_statuses.pending')
                    : ($state instanceof OfferRevisionStatus ? $state->getLabel() : __('crm.offers.revision_statuses.' . $state)))
                ->color(fn ($state, CrmOfferRevision $record) => $record->has_pending_approval
                    ? OfferApprovalStatus::Pending->color()
                    : ($state instanceof OfferRevisionStatus ? $state->color() : OfferRevisionStatus::tryFrom((string) $state)?->color())),
            Tables\Columns\TextColumn::make('recordedBy.name')
                ->label(__('crm.offers.fields.recorded_by'))
                ->placeholder(__('—')),
            Tables\Columns\TextColumn::make('expires_at')
                ->label(__('crm.offers.fields.expires_at'))
                ->dateTime()
                ->placeholder(__('—')),
        ])
            ->emptyStateHeading(__('crm.offer_empty.revisions_heading'))
            ->emptyStateDescription(__('crm.offer_empty.revisions_description'))
            ->emptyStateIcon('heroicon-o-document-text')
            ->description(__('sales.price_history.help'))
            ->actions([
                    Tables\Actions\ViewAction::make()->label(__('crm.actions.details'))->modalHeading(__('crm.offer_modals.view_revision'))->infolist([
                        Infolists\Components\TextEntry::make('revision_number')->label(__('crm.offers.fields.revision')),
                        Infolists\Components\TextEntry::make('amount')->label(__('crm.offers.fields.amount'))->money(fn(CrmOfferRevision $record) => $record->currency),
                        Infolists\Components\TextEntry::make('notes')->label(__('crm.fields.notes'))->placeholder(__('—'))->columnSpanFull(),
                    ]),
                    Tables\Actions\EditAction::make()->label(__('crm.actions.edit'))->modalHeading(__('crm.offer_modals.edit_revision'))->visible(fn(CrmOfferRevision $record) => $record->status === OfferRevisionStatus::Draft && auth()->user()->can('update', $record))->using(fn(CrmOfferRevision $record, array $data) => app(OfferNegotiationService::class)->updateDraftRevision(auth()->user(), $record, $data)),
                ])->defaultSort('revision_number', 'desc');
    }
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getOwnerRecord()->company_id;
        return $data;
    }
}
