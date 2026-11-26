<?php

namespace App\Filament\Resources\MaintenanceRequestResource\Pages;

use App\Filament\Resources\MaintenanceRequestResource;
use App\Filament\Support\MaintenanceUnitNotice;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceRequest extends EditRecord
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        MaintenanceUnitNotice::send($this->getRecord());

        // The completion date is filled in by the status, so show the saved value.
        $this->refreshFormData(['completed_at']);
    }
}
