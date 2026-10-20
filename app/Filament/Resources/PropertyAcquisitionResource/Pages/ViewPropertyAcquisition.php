<?php 
namespace App\Filament\Resources\PropertyAcquisitionResource\Pages; 

use App\Filament\Resources\PropertyAcquisitionResource; 
use App\Services\PropertyAcquisitionService; 
use Filament\Notifications\Notification;
use Filament\Actions; 
use Filament\Resources\Pages\ViewRecord; 
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ViewPropertyAcquisition extends ViewRecord 
{ 
    protected static string $resource = PropertyAcquisitionResource::class; 

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('startDueDiligence')
                ->label(__('Start due diligence'))
                ->visible(fn() => $this->record->status === 'draft')
                ->authorize('update')
                ->action(function() {
                    $this->performTransition('under_due_diligence', __('Due Diligence Started'));
                }),

            Actions\Action::make('approve')
                ->label(__('Approve acquisition'))
                ->visible(fn() => $this->record->status === 'under_due_diligence')
                ->authorize('approve')
                ->action(function() {
                    $this->performTransition('approved', __('Acquisition Approved'));
                }),

            Actions\Action::make('complete')
                ->label(__('Complete acquisition'))
                ->visible(fn() => $this->record->status === 'approved')
                ->authorize('complete')
                ->action(function() {
                    $this->performTransition('completed', __('Acquisition Completed'));
                }),

            Actions\Action::make('cancel')
                ->label(__('Cancel acquisition'))
                ->visible(fn() => !in_array($this->record->status, ['cancelled', 'completed']))
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')->label(__('Cancellation Reason'))->required()
                ])
                ->authorize('cancel')
                ->action(function(array $data) {
                    $this->performTransition('cancelled', __('Acquisition Cancelled'), $data['reason']);
                })
        ];
    }

    private function performTransition(string $status, string $successTitle, ?string $reason = null): void
    {
        try {
            $this->record = app(PropertyAcquisitionService::class)->transition(
                auth()->user(),
                $this->record,
                $status,
                $reason,
            );

            Notification::make()
                ->success()
                ->title($successTitle)
                ->body(__('Current status: :status', ['status' => __($this->record->status)]))
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('Cannot change acquisition status'))
                ->body(collect($exception->errors())->flatten()->first())
                ->send();
        } catch (AuthorizationException) {
            Notification::make()
                ->danger()
                ->title(__('Unauthorized'))
                ->body(__('You do not have permission for this action.'))
                ->send();
        }
    }
}
