<?php

namespace App\Filament\Resources\UnitReservationResource\Pages;

use App\Enums\{OpportunityType, ReservationSource};
use App\Filament\Resources\UnitReservationResource;
use App\Models\{Party, UnitReservation};
use App\Services\ReservationWorkflowService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class CreateUnitReservation extends CreateRecord
{
    protected static string $resource = UnitReservationResource::class;

    public function getTitle(): string { return __('reservations.actions.create'); }

    public function mount(): void
    {
        parent::mount();

        $source = request('source');
        $offerId = request('offer_id');
        $partyId = request('party_id');

        $data = [];

        if (filled($source)) {
            $data['source'] = $source;
        }

        if (filled($offerId)) {
            $offer = \App\Models\CrmOffer::withoutGlobalScopes()->find($offerId);
            if ($offer) {
                $data['offer_id'] = $offer->id;

                $party = $offer->opportunity?->lead?->party;
                if ($party) {
                    $data['customer_party_id'] = $party->id;
                } elseif (filled($partyId)) {
                    $party = Party::withoutGlobalScopes()->find($partyId);
                    if ($party) {
                        $data['customer_party_id'] = $party->id;
                    }
                }

                if ($offer->listing?->listable_type === 'unit') {
                    $data['unit_id'] = $offer->listing->listable_id;
                } else {
                    $data['unit_id'] = null;
                }
            }
        }

        if (! empty($data)) {
            $this->form->fill($data);
        }
    }

    protected function handleRecordCreation(array $data): UnitReservation
    {
        try {
            return app(ReservationWorkflowService::class)->createDraft(auth()->user(), $data);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('reservations.notifications.create_failed'))
                ->body(collect($exception->errors())->flatten()->first())->send();
            throw $exception;
        } catch (AuthorizationException $exception) {
            Notification::make()->danger()->title(__('crm.errors.unauthorized'))
                ->body(__('reservations.notifications.create_unauthorized'))->send();
            throw $exception;
        }
    }
}
