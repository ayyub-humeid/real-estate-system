<?php

namespace App\Filament\Resources\RentalRequestResource\Pages;

use App\Filament\Resources\RentalRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRentalRequest extends EditRecord
{
    protected static string $resource = RentalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
