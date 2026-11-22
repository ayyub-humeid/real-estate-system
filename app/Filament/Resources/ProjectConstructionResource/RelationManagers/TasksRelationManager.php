<?php

namespace App\Filament\Resources\ProjectConstructionResource\RelationManagers;

use App\Filament\Support\{ConstructionForms, RunsWorkflowAction};
use App\Models\{ConstructionProgressUpdate, ConstructionWorkPackageTask, ProjectConstruction};
use App\Services\ConstructionService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Optional detail below each stage. Every action opens on its own task row. */
class TasksRelationManager extends RelationManager
{
    use RunsWorkflowAction;

    protected static string $relationship = 'tasks';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project.construction.tab_tasks');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function construction(): ProjectConstruction
    {
        return $this->getOwnerRecord();
    }

    private function perform(callable $operation, string $successKey, bool $form = false): mixed
    {
        return $this->runWorkflowAction(
            operation: $operation,
            successTitle: __('project.construction.done.'.$successKey),
            onSuccess: fn () => $this->getOwnerRecord()->refresh(),
            rethrowValidation: $form,
        );
    }

    /** The one progress figure that is currently in force for this task (the latest that was not corrected). */
    private function effectiveProgress(ConstructionWorkPackageTask $task): ?ConstructionProgressUpdate
    {
        return $task->progressUpdates()
            ->whereNotIn('id', ConstructionProgressUpdate::withoutGlobalScopes()->whereNotNull('corrects_progress_update_id')->select('corrects_progress_update_id'))
            ->latest('id')->first();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['workPackage', 'assignedParty']))
            ->defaultSort('planned_end_date')
            ->emptyStateHeading(__('project.construction.tasks_empty'))
            ->columns([
                Tables\Columns\TextColumn::make('workPackage.name')->label(__('project.construction.fields.package'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label(__('project.construction.fields.task'))->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('assignedParty.name')->label(__('project.construction.fields.responsible'))->placeholder('—'),
                Tables\Columns\TextColumn::make('planned_end_date')->label(__('project.construction.fields.planned_end'))->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('progress_percentage')->label(__('project.construction.fields.progress'))
                    ->formatStateUsing(fn ($state): string => (int) $state.'%')->badge()->color('info'),
                Tables\Columns\TextColumn::make('status')->label(__('project.construction.fields.status'))->badge()
                    ->formatStateUsing(fn (?string $state): string => __('project.statuses.'.$state))
                    ->color(fn (?string $state): string => match ($state) { 'in_progress' => 'info', 'awaiting_inspection' => 'warning', 'completed' => 'success', default => 'gray' }),
                Tables\Columns\IconColumn::make('requires_inspection')->label(__('project.construction.fields.requires_inspection'))->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('startTask')
                    ->label(__('project.construction.actions.start_task'))->icon('heroicon-o-play')->color('info')
                    ->visible(fn (ConstructionWorkPackageTask $record): bool => $record->status === 'planned' && auth()->user()->can('start', $record))
                    ->action(fn (ConstructionWorkPackageTask $record) => $this->perform(fn () => app(ConstructionService::class)->startTask(auth()->user(), $record), 'task_started')),
                Tables\Actions\Action::make('recordProgress')
                    ->label(__('project.construction.actions.record_progress'))->icon('heroicon-o-chart-bar')
                    ->visible(fn (ConstructionWorkPackageTask $record): bool => $record->status === 'in_progress' && auth()->user()->can('recordProgress', $record))
                    ->form(fn (ConstructionWorkPackageTask $record): array => ConstructionForms::progress($record))
                    ->action(fn (ConstructionWorkPackageTask $record, array $data) => $this->perform(fn () => app(ConstructionService::class)->recordProgress(auth()->user(), $record, $data), 'progress', true)),
                Tables\Actions\Action::make('recordInspection')
                    ->label(__('project.construction.actions.record_inspection'))->icon('heroicon-o-clipboard-document-check')->color('warning')
                    ->visible(fn (ConstructionWorkPackageTask $record): bool => $record->status === 'awaiting_inspection' && auth()->user()->can('createInspection', $record))
                    ->form(fn (): array => ConstructionForms::inspection($this->construction()))
                    ->action(fn (ConstructionWorkPackageTask $record, array $data) => $this->perform(fn () => app(ConstructionService::class)->recordInspection(auth()->user(), $record, $data), 'inspection', true)),
                Tables\Actions\Action::make('completeTask')
                    ->label(__('project.construction.actions.complete_task'))->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ConstructionWorkPackageTask $record): bool => in_array($record->status, ['in_progress', 'awaiting_inspection'], true) && (float) $record->progress_percentage >= 100 && auth()->user()->can('complete', $record))
                    ->action(fn (ConstructionWorkPackageTask $record) => $this->perform(fn () => app(ConstructionService::class)->completeTask(auth()->user(), $record), 'task_completed')),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('correctProgress')
                        ->label(__('project.construction.actions.correct_progress'))->icon('heroicon-o-arrow-path')
                        ->visible(fn (ConstructionWorkPackageTask $record): bool => in_array($record->status, ['in_progress', 'awaiting_inspection'], true) && $this->effectiveProgress($record) !== null && auth()->user()->can('correctProgress', $this->effectiveProgress($record)))
                        ->form(fn (ConstructionWorkPackageTask $record): array => ConstructionForms::progress($record, true))
                        ->action(fn (ConstructionWorkPackageTask $record, array $data) => $this->perform(fn () => app(ConstructionService::class)->correctProgress(auth()->user(), $this->effectiveProgress($record), $data), 'progress_corrected', true)),
                    Tables\Actions\Action::make('editTask')
                        ->label(__('project.construction.actions.edit'))->icon('heroicon-o-pencil-square')
                        ->visible(fn (ConstructionWorkPackageTask $record): bool => ! in_array($record->status, ['completed', 'cancelled'], true) && auth()->user()->can('update', $record))
                        ->fillForm(fn (ConstructionWorkPackageTask $record): array => $record->only(['name', 'assigned_party_id', 'requires_inspection', 'planned_start_date', 'planned_end_date', 'description', 'notes']))
                        ->form(fn (): array => ConstructionForms::task($this->construction()))
                        ->action(fn (ConstructionWorkPackageTask $record, array $data) => $this->perform(fn () => app(ConstructionService::class)->updateTask(auth()->user(), $record, $data), 'task_updated', true)),
                    Tables\Actions\Action::make('cancelTask')
                        ->label(__('project.construction.actions.cancel_task'))->icon('heroicon-o-x-circle')->color('danger')
                        ->visible(fn (ConstructionWorkPackageTask $record): bool => ! in_array($record->status, ['completed', 'cancelled'], true) && auth()->user()->can('cancel', $record))
                        ->form([Forms\Components\Textarea::make('reason')->label(__('project.construction.fields.reason'))->required()])
                        ->action(fn (ConstructionWorkPackageTask $record, array $data) => $this->perform(fn () => app(ConstructionService::class)->cancelTask(auth()->user(), $record, $data['reason']), 'task_cancelled', true)),
                    Tables\Actions\Action::make('deleteTask')
                        ->label(__('project.construction.actions.delete'))->icon('heroicon-o-trash')->color('danger')
                        ->requiresConfirmation()->modalDescription(__('project.construction.actions.delete_description'))
                        ->visible(fn (ConstructionWorkPackageTask $record): bool => $record->status === 'planned' && auth()->user()->can('delete', $record))
                        ->action(fn (ConstructionWorkPackageTask $record) => $this->perform(fn () => app(ConstructionService::class)->deleteTask(auth()->user(), $record), 'task_deleted')),
                ]),
            ]);
    }
}
