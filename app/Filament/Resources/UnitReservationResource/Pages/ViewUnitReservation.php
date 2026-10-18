<?php

namespace App\Filament\Resources\UnitReservationResource\Pages;

use App\Filament\Resources\UnitReservationResource;
use App\Filament\Resources\UnitReservationResource\RelationManagers\ReservationStatusHistoriesRelationManager;
use App\Services\ReservationWorkflowService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ViewUnitReservation extends ViewRecord
{
    protected static string $resource = UnitReservationResource::class;

    public function getTitle(): string { return $this->record->reservation_number; }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label(__('crm.actions.edit'))->visible(fn () => $this->record->status->value === 'draft'),
            $this->workflowAction('send', __('reservations.actions.send_for_approval'), 'info'),
            $this->workflowAction('approve', __('reservations.actions.approve_activate'), 'success'),
            $this->workflowAction('reject', __('reservations.actions.reject'), 'danger', true),
            $this->workflowAction('cancel', __('reservations.actions.cancel'), 'danger', true),
            $this->workflowAction('extend', __('reservations.actions.extend'), 'warning', false, true),
            $this->workflowAction('expire', __('reservations.actions.expire'), 'warning'),
        ];
    }

    public function getRelationManagers(): array { return [ReservationStatusHistoriesRelationManager::class]; }
    public function infolist(Infolist $infolist): Infolist { return UnitReservationResource::infolist($infolist); }

    private function workflowAction(string $name, string $label, string $color, bool $reason = false, bool $extend = false): Actions\Action
    {
        return Actions\Action::make($name)
            ->label($label)->modalHeading($label)->color($color)->requiresConfirmation()
            ->form(fn (): array => UnitReservationResource::workflowForm($this->record, $reason, $extend))
            ->visible(fn (): bool => UnitReservationResource::workflowActionAllowed($name, $this->record)
                && auth()->user()->can(UnitReservationResource::workflowAbility($name), $this->record))
            ->action(function (array $data) use ($name, $label): void {
                try {
                    $service = app(ReservationWorkflowService::class);
                    match ($name) {
                        'send' => $service->sendForApproval(auth()->user(), $this->record), 'approve' => $service->approveAndActivate(auth()->user(), $this->record),
                        'reject' => $service->rejectRequest(auth()->user(), $this->record, $data['reason'] ?? null), 'cancel' => $service->cancelReservation(auth()->user(), $this->record, $data['reason'] ?? null),
                        'extend' => $service->extendReservation(auth()->user(), $this->record, $data['valid_until']), 'expire' => $service->expireReservation(auth()->user(), $this->record),
                    };
                    $this->record = $this->record->fresh(['snapshot']);
                    Notification::make()->success()->title($label)->send();
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title($label)->body(collect($exception->errors())->flatten()->first())->send();
                    throw $exception;
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send();
                }
            });
    }
}
