<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Services\ProjectPlanningService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label(__('Approve'))
                ->visible(fn() => $this->record->status === 'planning')
                ->authorize('approve')
                ->action(fn() => $this->transition('approved', __('Project Approved'))),
            Actions\Action::make('start')
                ->label(__('Start Project'))
                ->visible(fn() => $this->record->status === 'approved')
                ->authorize('update')
                ->action(fn() => $this->transition('in_progress', __('Project Started'))),
            Actions\Action::make('complete')
                ->label(__('Complete Project'))
                ->visible(fn() => $this->record->status === 'in_progress')
                ->authorize('update')
                ->action(fn() => $this->transition('completed', __('Project Completed'))),
            Actions\Action::make('close')
                ->label(__('Close Project'))
                ->visible(fn() => $this->record->status === 'completed')
                ->authorize('close')
                ->action(fn() => $this->transition('closed', __('Project Closed'))),
            Actions\Action::make('cancel')
                ->label(__('Cancel Project'))
                ->visible(fn() => in_array($this->record->status, ['planning', 'approved', 'in_progress']))
                ->authorize('cancel')
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label(__('Reason'))
                        ->required()
                ])
                ->action(fn(array $data) => $this->transition('cancelled', __('Project Cancelled'), $data['reason']))
        ];
    }

    private function transition(string $to, string $title, ?string $reason = null): void
    {
        try {
            $this->record = app(ProjectPlanningService::class)->transitionProject(auth()->user(), $this->record, $to, $reason);
            $statusLabel = match ($to) {
                'approved' => __('Approved'),
                'in_progress' => __('In Progress'),
                'completed' => __('Completed'),
                'closed' => __('Closed'),
                'cancelled' => __('Cancelled'),
                default => __(ucwords(str_replace('_', ' ', $to))),
            };
            Notification::make()->success()->title($title)->body(__('Current status: :status.', ['status' => $statusLabel]))->send();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(__('Cannot change project status'))->body(collect($e->errors())->flatten()->first())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('Unauthorized'))->send();
        }
    }
}
