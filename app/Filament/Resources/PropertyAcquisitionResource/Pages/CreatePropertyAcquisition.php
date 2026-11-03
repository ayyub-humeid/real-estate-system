<?php

namespace App\Filament\Resources\PropertyAcquisitionResource\Pages;

use App\Filament\Resources\PropertyAcquisitionResource;
use App\Models\PropertyAcquisition;
use App\Services\PropertyAcquisitionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Validator;

class CreatePropertyAcquisition extends CreateRecord
{
    protected static string $resource = PropertyAcquisitionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            Validator::make($data, [
                'company_id' => ['required', 'integer', 'exists:companies,id'],
            ])->validate();
        } else {
            // Ignore any forged company ID; the authenticated company is authoritative.
            $data['company_id'] = $user->company_id;
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(PropertyAcquisitionService::class)->create(auth()->user(), $data);
    }
}
