<?php

namespace App\Filament\Resources\CrmOfferResource\Pages;

use App\Filament\Resources\CrmOfferResource;
use App\Services\OfferNegotiationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditCrmOffer extends EditRecord
{
    protected static string $resource = CrmOfferResource::class;

    public function getTitle(): string
    {
        return __('crm.offer_modals.edit_offer');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(OfferNegotiationService::class)->updateOffer(auth()->user(), $record, $data);
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label(__('crm.actions.delete'))
                ->modalHeading(__('crm.offer_modals.delete_offer'))
                ->modalDescription(__('crm.offer_modals.delete_offer_description')),
        ];
    }
}
