<?php
namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Services\LeadIntakeService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    /** The form's "source" field maps to the lead's `first_source` column, which Filament cannot guess. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $source = $this->record->first_source;
        $data['source'] = $source instanceof \BackedEnum ? $source->value : $source;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(LeadIntakeService::class)->updateLead(auth()->user(), $record, $data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('crm.navigation.leads'))
                ->body(collect($exception->errors())->flatten()->first())
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
