<?php

namespace App\Filament\Resources\PartyResource\Pages;

use App\Filament\Resources\PartyResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Validator;

class CreateParty extends CreateRecord
{
    protected static string $resource = PartyResource::class;

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
}
