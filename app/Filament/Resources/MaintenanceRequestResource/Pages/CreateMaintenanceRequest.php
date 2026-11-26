<?php

namespace App\Filament\Resources\MaintenanceRequestResource\Pages;

use App\Filament\Resources\MaintenanceRequestResource;
use App\Filament\Support\MaintenanceUnitNotice;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceRequest extends CreateRecord
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function afterCreate(): void
    {
        MaintenanceUnitNotice::send($this->getRecord());
    }
}
