<?php

namespace App\Filament\Resources\UnitReservationResource\Pages;

use App\Filament\Resources\UnitReservationResource;
use App\Models\UnitReservation;
use App\Services\ReservationWorkflowService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class CreateUnitReservation extends CreateRecord
{
    protected static string $resource = UnitReservationResource::class;

    public function getTitle(): string { return __('reservations.actions.create'); }

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
