<?php

namespace App\Filament\Resources\OpportunityResource\Pages;

use App\Filament\Resources\OpportunityResource;
use App\Models\Opportunity;
use App\Services\OpportunityService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class CreateOpportunity extends CreateRecord
{
    protected static string $resource = OpportunityResource::class;

    protected function handleRecordCreation(array $data): Opportunity
    {
        try {
            return app(OpportunityService::class)->create(auth()->user(), $data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('crm.errors.action_failed'))
                ->body(collect($exception->errors())->flatten()->first())
                ->persistent()
                ->send();

            // Domain services use bare field names (e.g. `lead_id`); a Resource
            // form stores its state under the `data` path, so map the error at
            // this presentation boundary to highlight the actual input too.
            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field) => [
                        str_starts_with($field, 'data.') ? $field : "data.{$field}" => $messages,
                    ])
                    ->all(),
            );
        } catch (AuthorizationException) {
            Notification::make()
                ->danger()
                ->title(__('crm.errors.unauthorized'))
                ->body(__('crm.errors.unauthorized_body_generic'))
                ->persistent()
                ->send();

            $this->halt();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()
                ->danger()
                ->title(__('crm.errors.action_failed'))
                ->body($exception->getMessage())
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
