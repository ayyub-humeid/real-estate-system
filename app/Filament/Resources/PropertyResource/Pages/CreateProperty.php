<?php

namespace App\Filament\Resources\PropertyResource\Pages;

use App\Filament\Resources\PropertyResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProperty extends CreateRecord
{
    protected static string $resource = PropertyResource::class;
      public function mount(): void
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->company?->canAddProperty()) {
            Notification::make()
                ->title(__('Limit Reached'))
                ->body('Your plan does not allow adding more properties.')
                ->danger()
                ->persistent()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
            return;
        }

        parent::mount();
    }
}
