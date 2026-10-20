<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Models\Contract;
use App\Services\ContractDraftService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    public function getTitle(): string
    {
        return __('Create contract');
    }

    protected function handleRecordCreation(array $data): Contract
    {
        try {
            return app(ContractDraftService::class)->create(auth()->user(), $data);
        } catch (ValidationException $e) {
            // Surface the first validation message as a prominent notification
            // so the user knows exactly which field failed.
            $first = collect($e->errors())->flatten()->first();
            Notification::make()
                ->danger()
                ->title(__('Cannot create contract'))
                ->body($first)
                ->persistent()
                ->send();

            // Re-throw so Filament can also highlight field-level errors.
            throw $e;
        } catch (AuthorizationException) {
            Notification::make()
                ->danger()
                ->title(__('Unauthorized'))
                ->body(__('You do not have permission to create a contract.'))
                ->persistent()
                ->send();

            $this->halt();
        } catch (\Throwable $e) {
            // Catch-all: never let the user see a silent failure.
            Notification::make()
                ->danger()
                ->title(__('An unexpected error occurred'))
                ->body($e->getMessage())
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
