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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()->isSuperAdmin()) {
            $data['company_id'] = auth()->user()->company_id;
        }

        return $data;
    }
}
