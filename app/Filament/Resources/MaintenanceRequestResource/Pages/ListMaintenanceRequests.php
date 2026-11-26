<?php

namespace App\Filament\Resources\MaintenanceRequestResource\Pages;

use App\Filament\Resources\MaintenanceRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceRequests extends ListRecords
{
    protected static string $resource = MaintenanceRequestResource::class;

    public function getTitle(): string
    {
        return __('maintenance.navigation');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
