<?php

namespace App\Filament\Resources\OpportunityResource\RelationManagers;

use App\Enums\{OfferStatus, OpportunityType};
use App\Filament\Resources\CrmOfferResource;
use App\Models\{CrmOffer, Listing, Opportunity, User};
use App\Services\OfferNegotiationService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class OffersRelationManager extends RelationManager
{
    protected static string $relationship = 'offers';
    public static function getModelLabel(): string { return __('crm.offers.models.offer'); }
    public static function getPluralModelLabel(): string { return __('crm.offers.models.offers'); }
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string { return __('crm.offers.navigation'); }
    public function isReadOnly(): bool { return false; }
    public function form(Forms\Form $form): Forms\Form { return $form->schema($this->formSchema()); }
    private function formSchema(): array
    {
        $opportunity = $this->getOwnerRecord();
        return [
            Forms\Components\Select::make('listing_id')->label(__('crm.fields.listing'))->options(Listing::withoutGlobalScopes()->where('company_id', $opportunity->company_id)->orderBy('title')->pluck('title', 'id'))->default($opportunity->listing_id)->searchable()->visible(fn (): bool => (bool) config('features.advertisements')),
            ...\App\Filament\Support\OpportunityUnitFields::schema(
                fn (): ?int => (int) $opportunity->company_id,
                fn () => $opportunity->type,
                withProject: false,
                unitHelp: __('sales.help.offer_unit'),
            ),
            Forms\Components\Select::make('assigned_to')->label(__('crm.offers.fields.negotiator'))->options(User::withoutGlobalScopes()->where('company_id', $opportunity->company_id)->orderBy('name')->pluck('name', 'id'))->searchable(),
            // The deal type always equals the opportunity's, so it is not asked again.
            ...\App\Filament\Support\OfferPriceFields::schema(fn () => $opportunity->type, \App\Filament\Support\OfferPriceFields::defaultsFor($opportunity)),
        ];
    }
    public function table(Tables\Table $table): Tables\Table
    {
        return $table->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => CrmOfferResource::scopeForViewer($query))->columns([
            Tables\Columns\TextColumn::make('reference_number')->label(__('crm.offers.fields.reference_number'))->weight('bold'),
            Tables\Columns\TextColumn::make('transaction_type')->label(__('crm.offers.fields.transaction_type'))->badge()->formatStateUsing(fn ($state) => $state instanceof OpportunityType ? $state->getLabel() : __('crm.opportunity_types.' . $state)),
            Tables\Columns\TextColumn::make('latestRevision.amount')->label(__('crm.offers.fields.latest_amount'))->money(fn (CrmOffer $record) => $record->latestRevision?->currency ?? 'USD')->placeholder(__('—')),
            Tables\Columns\TextColumn::make('status')->label(__('crm.fields.status'))->badge()->formatStateUsing(fn ($state) => $state instanceof OfferStatus ? $state->getLabel() : __('crm.offers.statuses.' . $state))->color(fn ($state) => $state instanceof OfferStatus ? $state->color() : OfferStatus::tryFrom((string) $state)?->color()),
        ])
            ->emptyStateHeading(__('crm.offer_empty.offers_heading'))
            ->emptyStateDescription(__('crm.offer_empty.offers_description'))
            ->emptyStateIcon('heroicon-o-document-currency-dollar')
            ->headerActions([
            Tables\Actions\CreateAction::make()->label(__('crm.offers.actions.create_offer'))
                ->modalHeading(__('crm.offer_modals.create_offer'))
                ->mutateFormDataUsing(function (array $data): array { $data['company_id'] = $this->getOwnerRecord()->company_id; $data['opportunity_id'] = $this->getOwnerRecord()->id; $data['transaction_type'] = $this->getOwnerRecord()->type->value; return $data; })
                ->using(fn (array $data) => app(OfferNegotiationService::class)->createQuickOffer(auth()->user(), $data)),
        ])->actions([
            Tables\Actions\ViewAction::make()->label(__('crm.actions.details'))->url(fn (CrmOffer $record) => CrmOfferResource::getUrl('view', ['record' => $record])),
        ])->defaultSort('created_at', 'desc');
    }
    protected function mutateFormDataBeforeCreate(array $data): array { $data['company_id'] = $this->getOwnerRecord()->company_id; $data['opportunity_id'] = $this->getOwnerRecord()->id; return $data; }
}
