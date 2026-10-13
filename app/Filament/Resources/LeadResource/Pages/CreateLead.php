<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use App\Services\LeadIntakeService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

    protected function handleRecordCreation(array $data): Lead
    {
        try {
            return app(LeadIntakeService::class)->createLead(auth()->user(), $data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('crm.navigation.leads'))
                ->body(collect($exception->errors())->flatten()->first())
                ->send();

            // Domain services deliberately use field names such as `phone`.
            // A Resource form is stored under Filament's `data` state path,
            // so map the error at this presentation boundary and show it next
            // to the actual input rather than only in a toast.
            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field) => [
                        str_starts_with($field, 'data.') ? $field : "data.{$field}" => $messages,
                    ])
                    ->all(),
            );
        }
    }
}
