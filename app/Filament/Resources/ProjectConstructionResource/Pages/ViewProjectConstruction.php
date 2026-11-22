<?php

namespace App\Filament\Resources\ProjectConstructionResource\Pages;

use App\Filament\Resources\ProjectConstructionResource;
use App\Filament\Resources\ProjectConstructionResource\RelationManagers\{DelaysRelationManager, IssuesRelationManager, TasksRelationManager, WorkPackagesRelationManager};
use App\Filament\Support\{ConstructionForms, RunsWorkflowAction};
use App\Services\ConstructionService;
use App\Support\ConstructionHealth;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

/** One page to follow a construction: its stages first, with the detail one tab away. */
class ViewProjectConstruction extends ViewRecord
{
    use RunsWorkflowAction;

    protected static string $resource = ProjectConstructionResource::class;

    public function getTitle(): string
    {
        return __('project.construction.title').' #'.$this->record->execution_number.' — '.$this->record->project?->name;
    }

    public function getSubheading(): ?string
    {
        return ConstructionHealth::nextStep($this->record);
    }

    public function getRelationManagers(): array
    {
        return [WorkPackagesRelationManager::class, TasksRelationManager::class, IssuesRelationManager::class, DelaysRelationManager::class];
    }

    private function lifecycle(callable $operation, string $successKey, bool $form = false): void
    {
        $this->runWorkflowAction(
            operation: $operation,
            successTitle: __('project.construction.done.'.$successKey),
            onSuccess: fn () => $this->record->refresh(),
            rethrowValidation: $form,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('startConstruction')
                ->label(__('project.construction.actions.start'))->icon('heroicon-o-play')->color('info')
                ->requiresConfirmation()->modalDescription(__('project.construction.actions.start_description'))
                ->visible(fn (): bool => $this->record->status === 'planned' && auth()->user()->can('start', $this->record))
                ->action(fn () => $this->lifecycle(fn () => app(ConstructionService::class)->startConstruction(auth()->user(), $this->record), 'started')),
            Actions\Action::make('completeConstruction')
                ->label(__('project.construction.actions.complete'))->icon('heroicon-o-check-circle')->color('success')
                ->requiresConfirmation()->modalDescription(__('project.construction.actions.complete_description'))
                ->visible(fn (): bool => $this->record->status === 'in_progress' && auth()->user()->can('complete', $this->record))
                ->action(fn () => $this->lifecycle(fn () => app(ConstructionService::class)->completeConstruction(auth()->user(), $this->record), 'completed')),
            Actions\Action::make('editConstruction')
                ->label(__('project.construction.actions.edit'))->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => ! in_array($this->record->status, ['completed', 'cancelled'], true) && auth()->user()->can('update', $this->record))
                ->fillForm(fn (): array => $this->record->only(['planned_start_date', 'expected_completion_date', 'manager_party_id', 'notes']))
                ->form(fn (): array => ConstructionForms::construction($this->record))
                ->action(fn (array $data) => $this->lifecycle(fn () => app(ConstructionService::class)->updateConstruction(auth()->user(), $this->record, $data), 'updated', true)),
            Actions\Action::make('cancelConstruction')
                ->label(__('project.construction.actions.cancel'))->icon('heroicon-o-x-circle')->color('danger')
                ->modalDescription(__('project.construction.actions.cancel_description'))
                ->visible(fn (): bool => in_array($this->record->status, ['planned', 'in_progress'], true) && auth()->user()->can('cancel', $this->record))
                ->form([Forms\Components\Textarea::make('reason')->label(__('project.construction.fields.reason'))->required()])
                ->action(fn (array $data) => $this->lifecycle(fn () => app(ConstructionService::class)->cancelConstruction(auth()->user(), $this->record, $data['reason']), 'cancelled', true)),
        ];
    }
}
