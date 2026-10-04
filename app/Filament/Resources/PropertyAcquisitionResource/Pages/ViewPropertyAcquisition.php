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
                ->label('Start due diligence')
                ->visible(fn() => $this->record->status === 'draft')
                ->authorize('update')
                ->action(function() {
                    $this->performTransition('under_due_diligence', 'Due Diligence Started');
                }),

            Actions\Action::make('approve')
                ->visible(fn() => $this->record->status === 'under_due_diligence')
                ->authorize('approve')
                ->action(function() {
                    $this->performTransition('approved', 'Acquisition Approved');
                }),

            Actions\Action::make('complete')
                ->visible(fn() => $this->record->status === 'approved')
                ->authorize('complete')
                ->action(function() {
                    $this->performTransition('completed', 'Acquisition Completed');
                }),

            Actions\Action::make('cancel')
                ->visible(fn() => !in_array($this->record->status, ['cancelled', 'completed']))
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')->required()
                ])
                ->authorize('cancel')
                ->action(function(array $data) {
                    $this->performTransition('cancelled', 'Acquisition Cancelled', $data['reason']);
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
                ->body('Current status: '.str_replace('_', ' ', $this->record->status).'.')
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Cannot change acquisition status')
                ->body(collect($exception->errors())->flatten()->first())
                ->send();
        } catch (AuthorizationException) {
            Notification::make()
                ->danger()
                ->title('Unauthorized')
                ->body('You do not have permission for this action.')
                ->send();
        }
    }
}
