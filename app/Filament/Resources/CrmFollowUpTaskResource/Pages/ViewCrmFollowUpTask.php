<?php

namespace App\Filament\Resources\CrmFollowUpTaskResource\Pages;

use App\Enums\{CrmActivityOutcome, CrmActivityType, CrmTaskStatus};
use App\Filament\Resources\CrmFollowUpTaskResource;
use App\Models\CrmFollowUpTask;
use App\Services\CrmTaskService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ViewCrmFollowUpTask extends ViewRecord
{
    protected static string $resource = CrmFollowUpTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('complete')
                ->label(__('crm.tasks.actions.complete'))
                ->icon('heroicon-o-check')
                ->visible(fn (): bool => auth()->user()->can('complete', $this->record)
                    && in_array($this->record->status, [CrmTaskStatus::Pending, CrmTaskStatus::InProgress], true))
                ->form([
                    Forms\Components\Select::make('outcome')
                        ->label(__('crm.activities.fields.outcome'))
                        ->options(fn () => CrmActivityOutcome::optionsFor($this->record->type))
                        ->required(fn (): bool => $this->record->type !== CrmActivityType::Note),
                    Forms\Components\Textarea::make('notes')->label(__('crm.activities.fields.notes')),
                    Forms\Components\Toggle::make('create_next_follow_up')->label(__('crm.tasks.actions.create_next'))->live(),
                    Forms\Components\Select::make('next_type')->label(__('crm.activities.fields.type'))->options(CrmActivityType::options())->visible(fn (Forms\Get $get) => $get('create_next_follow_up')),
                    Forms\Components\TextInput::make('next_title')->label(__('crm.tasks.fields.title'))->visible(fn (Forms\Get $get) => $get('create_next_follow_up')),
                    Forms\Components\DateTimePicker::make('next_due_at')->label(__('crm.tasks.fields.due_at'))->minDate(now())->visible(fn (Forms\Get $get) => $get('create_next_follow_up')),
                ])
                ->action(function (array $data): void {
                    try {
                        app(CrmTaskService::class)->complete(auth()->user(), $this->record, $data);
                        $this->record->refresh();
                        Notification::make()->success()->title(__('crm.tasks.actions.complete'))->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title(__('crm.tasks.actions.complete'))->body(collect($exception->errors())->flatten()->first())->send();
                        throw $exception;
                    } catch (AuthorizationException) {
                        Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send();
                    }
                }),
            Actions\EditAction::make(),
        ];
    }
}
