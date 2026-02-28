<?php

namespace App\Filament\Resources\RentalRequestResource\Pages;

use App\Filament\Resources\RentalRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRentalRequests extends ListRecords
{
    protected static string $resource = RentalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
