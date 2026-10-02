<?php 
namespace App\Filament\Resources\PropertyAcquisitionResource\Pages; 

use App\Filament\Resources\PropertyAcquisitionResource; 
use App\Services\PropertyAcquisitionService; 
use Filament\Actions; 
use Filament\Resources\Pages\ViewRecord; 

class ViewPropertyAcquisition extends ViewRecord 
{ 
    protected static string $resource = PropertyAcquisitionResource::class; 

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('startDueDiligence')
                ->label('Start due diligence')
                ->visible(fn() => $this->record->status === 'draft')
                ->action(function() {
                    app(PropertyAcquisitionService::class)->transition(auth()->user(), $this->record, 'under_due_diligence');
                    \Filament\Notifications\Notification::make()->success()->title('Due Diligence Started')->send();
                }),

            Actions\Action::make('approve')
                ->visible(fn() => $this->record->status === 'under_due_diligence')
                ->authorize('approve')
                ->action(function() {
                    app(PropertyAcquisitionService::class)->transition(auth()->user(), $this->record, 'approved');
                    \Filament\Notifications\Notification::make()->success()->title('Acquisition Approved')->send();
                }),

            Actions\Action::make('complete')
                ->visible(fn() => $this->record->status === 'approved')
                ->authorize('complete')
                ->action(function() {
                    app(PropertyAcquisitionService::class)->transition(auth()->user(), $this->record, 'completed');
                    \Filament\Notifications\Notification::make()->success()->title('Acquisition Completed')->send();
                }),

            Actions\Action::make('cancel')
                ->visible(fn() => !in_array($this->record->status, ['cancelled', 'completed']))
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')->required()
                ])
                ->authorize('cancel')
                ->action(function(array $data) {
                    app(PropertyAcquisitionService::class)->transition(auth()->user(), $this->record, 'cancelled', $data['reason']);
                    \Filament\Notifications\Notification::make()->success()->title('Acquisition Cancelled')->send();
                })
        ];
    } 
}
