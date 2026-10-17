<?php

namespace App\Filament\Resources\CrmOfferResource\Pages;

use App\Filament\Resources\CrmOfferResource;
use App\Models\CrmOffer;
use App\Services\OfferNegotiationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class CreateCrmOffer extends CreateRecord
{
    protected static string $resource = CrmOfferResource::class;

    public function getTitle(): string
    {
        return __('crm.offer_modals.create_offer');
    }

    protected function handleRecordCreation(array $data): CrmOffer
    {
        try {
            return app(OfferNegotiationService::class)->createOffer(auth()->user(), $data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('crm.offers.errors.action_failed'))
                ->body(collect($exception->errors())->flatten()->first())
                ->send();

            throw $exception;
        } catch (AuthorizationException) {
            Notification::make()
                ->danger()
                ->title(__('crm.errors.unauthorized'))
                ->body(__('crm.errors.unauthorized'))
                ->send();

            throw $exception;
        }
    }
}
