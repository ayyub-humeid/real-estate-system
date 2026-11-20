<?php

namespace App\Filament\Resources\OpportunityResource\Pages;

use App\Filament\Resources\OpportunityResource;
use App\Services\OpportunityService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditOpportunity extends EditRecord
{
    protected static string $resource = OpportunityResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(OpportunityService::class)->update(auth()->user(), $record, $data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('crm.errors.action_failed'))
                ->body(collect($exception->errors())->flatten()->first())
                ->persistent()
                ->send();

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

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
