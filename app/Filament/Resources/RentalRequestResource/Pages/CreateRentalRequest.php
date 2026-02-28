<?php

namespace App\Filament\Resources\RentalRequestResource\Pages;

use App\Filament\Resources\RentalRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRentalRequest extends CreateRecord
{
    protected static string $resource = RentalRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()->isSuperAdmin()) {
            $data['company_id'] = auth()->user()->company_id;
        }

        return $data;
    }
}
