<?php

namespace App\Filament\Resources\ProjectDesignPackageResource\Pages;

use App\Filament\Resources\ProjectDesignPackageResource;
use App\Services\DesignPackageService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditProjectDesignPackage extends EditRecord
{
    protected static string $resource = ProjectDesignPackageResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(DesignPackageService::class)->updatePackage(auth()->user(), $record, $data);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('crm.errors.action_failed'))
                ->body(collect($exception->errors())->flatten()->first())->persistent()->send();

            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field) => [str_starts_with($field, 'data.') ? $field : "data.{$field}" => $messages])
                    ->all(),
            );
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('crm.errors.unauthorized'))
                ->body(__('crm.errors.unauthorized_body_generic'))->persistent()->send();
            $this->halt();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title(__('crm.errors.action_failed'))
                ->body($exception->getMessage())->persistent()->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return ProjectDesignPackageResource::getUrl('view', ['record' => $this->record]);
    }
}
