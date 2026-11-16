<?php

namespace App\Filament\Resources\CrmOfferResource\Pages;

use App\Enums\{OfferRevisionStatus, OfferStatus, ReservationSource};
use App\Filament\Resources\CrmOfferResource;
use App\Filament\Resources\CrmOfferResource\RelationManagers\{OfferApprovalsRelationManager, OfferRevisionsRelationManager};
use App\Models\CrmOffer;
use App\Services\OfferNegotiationService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ViewCrmOffer extends ViewRecord
{
    protected static string $resource = CrmOfferResource::class;

    public function getTitle(): string { return $this->record->reference_number; }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label(__('crm.actions.edit'))->visible(fn () => in_array($this->record->status, [OfferStatus::Draft, OfferStatus::Negotiating], true)),
            $this->acceptOfferAction(),
            $this->convertToReservationAction(),
        ];
    }

    public function getRelationManagers(): array { return [OfferRevisionsRelationManager::class, OfferApprovalsRelationManager::class]; }

    public function infolist(Infolist $infolist): Infolist { return CrmOfferResource::infolist($infolist); }

    private function acceptOfferAction(): Action
    {
        return Action::make('accept_offer')
            ->label(__('crm.offers.actions.accept_offer'))
            ->modalHeading(__('crm.offers.actions.accept_offer'))
            ->modalDescription(__('crm.offers.actions.accept_offer_description'))
            ->color('success')
            ->requiresConfirmation()
            ->modalSubmitActionLabel(__('crm.offers.actions.accept_offer_submit'))
            ->visible(fn (): bool => $this->record->status === OfferStatus::Negotiating && auth()->user()->can('accept', $this->record))
            ->action(function (): void {
                $revision = $this->record->revisions()->where('status', OfferRevisionStatus::Submitted->value)->latest('revision_number')->first();
                if (! $revision) {
                    Notification::make()->danger()->title(__('crm.offers.errors.action_failed'))->body(__('crm.offers.errors.agreement_requires_submitted'))->send();
                    return;
                }
                try {
                    app(OfferNegotiationService::class)->confirmAgreement(auth()->user(), $revision);
                    $this->record = $this->record->fresh();
                    Notification::make()->success()->title(__('crm.offers.actions.accept_offer'))->send();
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send();
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title(__('crm.offers.errors.action_failed'))->body(collect($exception->errors())->flatten()->first())->send();
                }
            });
    }

    private function convertToReservationAction(): Action
    {
        return Action::make('convert_to_reservation')
            ->label(__('crm.offers.actions.convert_to_reservation'))
            ->modalHeading(__('crm.offers.actions.convert_to_reservation'))
            ->modalDescription(__('crm.offers.actions.convert_to_reservation_description'))
            ->color('primary')
            ->requiresConfirmation()
            ->modalSubmitActionLabel(__('crm.offers.actions.convert_to_reservation_submit'))
            ->visible(fn (): bool => $this->record->status === OfferStatus::Accepted && auth()->user()->can('convert_offer_to_reservation', $this->record))
            ->action(function (): void {
                $partyId = optional($this->record->opportunity?->lead?->party)->id ?? null;
                $url = \App\Filament\Resources\UnitReservationResource::getUrl('create') . '?' . http_build_query([
                    'source' => ReservationSource::AcceptedOffer->value,
                    'offer_id' => $this->record->id,
                    'party_id' => $partyId,
                ]);
                $this->redirect($url);
            });
    }
}
