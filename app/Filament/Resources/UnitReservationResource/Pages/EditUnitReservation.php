<?php

namespace App\Filament\Resources\UnitReservationResource\Pages;

use App\Filament\Resources\UnitReservationResource;
use App\Services\ReservationWorkflowService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditUnitReservation extends EditRecord
{
    protected static string $resource = UnitReservationResource::class;

    public function getTitle(): string { return __('crm.actions.edit'); }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ReservationWorkflowService::class)->updateDraft(auth()->user(), $record, $data);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('reservations.notifications.update_failed'))
                ->body(collect($exception->errors())->flatten()->first())->persistent()->send();
            throw $exception;
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('crm.errors.unauthorized'))
                ->body(__('reservations.notifications.update_unauthorized'))->persistent()->send();
            $this->halt();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title(__('crm.errors.action_failed'))
                ->body($exception->getMessage())->persistent()->send();
            $this->halt();
        }
    }

    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()->label(__('crm.actions.delete'))]; }
}
