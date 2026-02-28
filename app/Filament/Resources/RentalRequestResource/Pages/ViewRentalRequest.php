<?php

namespace App\Filament\Resources\RentalRequestResource\Pages;

use App\Filament\Resources\RentalRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRentalRequest extends ViewRecord
{
    protected static string $resource = RentalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
