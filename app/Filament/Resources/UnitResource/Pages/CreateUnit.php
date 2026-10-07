<?php

namespace App\Filament\Resources\UnitResource\Pages;

use App\Filament\Resources\UnitResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Unit;
use App\Services\UnitSetupService;

class CreateUnit extends CreateRecord
{
    protected static string $resource = UnitResource::class;
      public function mount(): void
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->company?->canAddUnit()) {
            Notification::make()
                ->title('Limit Reached')
                ->body('Your plan does not allow adding more units.')
                ->danger()
                ->persistent()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
            return;
        }

        parent::mount();
    }

    protected function handleRecordCreation(array $data): Unit
    {
        return app(UnitSetupService::class)->create(auth()->user(), $data);
    }
}
