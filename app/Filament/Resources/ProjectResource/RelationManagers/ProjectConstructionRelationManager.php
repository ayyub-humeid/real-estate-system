<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\{BudgetItem, ConstructionDelay, ConstructionInspection, ConstructionIssue, ConstructionProgressUpdate, ConstructionWorkPackage, ConstructionWorkPackageTask, Party, ProjectConstruction, User};
use App\Services\ConstructionService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ProjectConstructionRelationManager extends RelationManager
{
    protected static string $relationship = 'constructions';
    protected static ?string $title = 'Construction';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table->emptyStateHeading('No construction lifecycle yet')
            ->emptyStateDescription('Create the project construction plan, then organize work packages and site tasks.')
            ->columns([
                Tables\Columns\TextColumn::make('execution_number')->label('Execution')->prefix('#'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->colors(['gray' => ['planned', 'cancelled'], 'info' => 'in_progress', 'success' => 'completed']),
                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Overall progress')
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('managerParty.name')
                    ->label('Manager')
                    ->placeholder('Not assigned'),
                Tables\Columns\TextColumn::make('work_packages_count')
                    ->counts('workPackages')
                    ->label('Packages'),
                Tables\Columns\TextColumn::make('expected_completion_date')
                    ->label('Expected')
                    ->date()
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createConstruction')
                    ->label('Create Construction')
                    ->icon('heroicon-o-plus')
                    ->visible(fn(): bool => !$this->getOwnerRecord()->activeConstruction()->exists() && auth()->user()->can('create', ProjectConstruction::class))
                    ->form($this->constructionForm())
                    ->action(fn(array $data) => $this->run(fn() => app(ConstructionService::class)->createConstruction(auth()->user(), $this->getOwnerRecord(), $data), 'Construction created')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Details')->icon('heroicon-o-eye')->tooltip('View construction, packages, tasks, and history')->modalWidth('7xl')->infolist($this->constructionInfolist()),
                Tables\Actions\Action::make('editConstruction')->label('Edit')->icon('heroicon-o-pencil-square')->tooltip('Edit construction planning details')
                    ->visible(fn(ProjectConstruction $record): bool => auth()->user()->can('update', $record))
                    ->fillForm(fn(ProjectConstruction $record) => $record->only(['planned_start_date', 'expected_completion_date', 'manager_party_id', 'notes']))->form($this->constructionForm())
                    ->action(fn(ProjectConstruction $record, array $data) => $this->run(fn() => app(ConstructionService::class)->updateConstruction(auth()->user(), $record, $data), 'Construction updated')),
                Tables\Actions\DeleteAction::make()->label('Delete')->tooltip('Delete an empty planned construction')->visible(fn(ProjectConstruction $record): bool => auth()->user()->can('delete', $record)),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('startConstruction')
                        ->icon('heroicon-o-play')->tooltip('Start construction')
                        ->visible(fn(ProjectConstruction $r) => $r->status === 'planned')
                        ->action(fn(ProjectConstruction $r) =>
                            $this->run(fn() =>
                                app(ConstructionService::class)->startConstruction(auth()->user(), $r), 'Construction started')),
                    Tables\Actions\Action::make('completeConstruction')
                        ->icon('heroicon-o-check-circle')->color('success')->tooltip('Complete construction')
                        ->visible(fn(ProjectConstruction $r) => $r->status === 'in_progress')
                        ->action(fn(ProjectConstruction $r) => $this->run(fn() => app(ConstructionService::class)->completeConstruction(auth()->user(), $r), 'Construction completed')),
                    Tables\Actions\Action::make('cancelConstruction')
                        ->icon('heroicon-o-x-circle')->color('danger')->tooltip('Cancel construction')
                        ->visible(fn(ProjectConstruction $r) => in_array($r->status, ['planned', 'in_progress'], true))
                        ->form([Forms\Components\Textarea::make('reason')->required()])
                        ->action(fn(ProjectConstruction $r, array $data) => $this->run(fn() => app(ConstructionService::class)->cancelConstruction(auth()->user(), $r, $data['reason']), 'Construction cancelled')),
                ])->tooltip('Manage construction lifecycle'),
                Tables\Actions\Action::make('createPackage')->label('Add Work Package')->icon(
                    'heroicon-o-wrench-screwdriver'
                )->tooltip('Add a major execution package')
                    ->visible(fn(ProjectConstruction $r) => auth()->user()->can('create', ConstructionWorkPackage::class) && !in_array($r->status, ['completed', 'cancelled'], true))
                    ->form(fn(ProjectConstruction $r) => $this->packageForm($r))
                    ->action(fn(ProjectConstruction $r, array $data) => $this->run(fn() => app(ConstructionService::class)->createWorkPackage(auth()->user(), $r, $data), 'Work package created')),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('managePackage')->label('Package lifecycle')->icon('heroicon-o-wrench-screwdriver')->tooltip('Start, complete, or cancel a work package')
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('package_id')->label('Work package')->options($this->packageOptions($r))->required()->searchable(), Forms\Components\Select::make('operation')->options(['start' => 'Start', 'complete' => 'Complete', 'cancel' => 'Cancel'])->required()->live(), Forms\Components\Textarea::make('reason')->visible(fn(Forms\Get $get) => $get('operation') === 'cancel')->required(fn(Forms\Get $get) => $get('operation') === 'cancel')])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $package = $record->workPackages()->findOrFail($data['package_id']);
                            $this->run(function () use ($package, $data): void {
                                $service = app(ConstructionService::class);
                                match ($data['operation']) {
                                    'start' => $service->startWorkPackage(auth()->user(), $package),
                                    'complete' => $service->completeWorkPackage(auth()->user(), $package),
                                    'cancel' => $service->cancelWorkPackage(auth()->user(), $package, $data['reason']),
                                };
                            }, 'Work package updated');
                        }),
                    Tables\Actions\Action::make('createTask')->label('Add Task')->icon('heroicon-o-plus-circle')->tooltip('Add a task to a work package')
                        ->form(fn(ProjectConstruction $r) => $this->taskForm($r))
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $package = $record->workPackages()->findOrFail($data['package_id']);
                            $this->run(fn() => app(ConstructionService::class)->createTask(auth()->user(), $package, $data), 'Task created');
                        }),
                    Tables\Actions\Action::make('editPackage')->label('Edit Work Package')->icon('heroicon-o-pencil-square')->tooltip('Edit mutable work package metadata')
                        ->form(fn(ProjectConstruction $r) => $this->editPackageForm($r))
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $package = $record->workPackages()->findOrFail($data['package_id']);
                            $this->run(fn() => app(ConstructionService::class)->updateWorkPackage(auth()->user(), $package, $data), 'Work package updated');
                        }),
                    Tables\Actions\Action::make('deletePackage')->label('Delete Work Package')->icon('heroicon-o-trash')->color('danger')->tooltip('Delete an empty planned work package')
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('package_id')->label('Work package')->options($this->packageOptions($r))->required()->searchable()])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $package = $record->workPackages()->findOrFail($data['package_id']);
                            $this->run(fn() => app(ConstructionService::class)->deleteWorkPackage(auth()->user(), $package), 'Work package deleted');
                        }),
                    Tables\Actions\Action::make('editTask')->label('Edit Task')->icon('heroicon-o-pencil')->tooltip('Edit mutable task metadata')
                        ->form(fn(ProjectConstruction $r) => $this->editTaskForm($r))
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $task = $this->taskForConstruction($record, $data['task_id']);
                            $this->run(fn() => app(ConstructionService::class)->updateTask(auth()->user(), $task, $data), 'Task updated');
                        }),
                    Tables\Actions\Action::make('deleteTask')->label('Delete Task')->icon('heroicon-o-trash')->color('danger')->tooltip('Delete an empty planned task')
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('task_id')->label('Task')->options($this->taskOptions($r))->required()->searchable()])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $task = $this->taskForConstruction($record, $data['task_id']);
                            $this->run(fn() => app(ConstructionService::class)->deleteTask(auth()->user(), $task), 'Task deleted');
                        }),
                    Tables\Actions\Action::make('manageTask')->label('Task lifecycle')->icon('heroicon-o-play-circle')->tooltip('Start, complete, or cancel a task')
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('task_id')->label('Task')->options($this->taskOptions($r))->required()->searchable(), Forms\Components\Select::make('operation')->options(['start' => 'Start', 'complete' => 'Complete', 'cancel' => 'Cancel'])->required()->live(), Forms\Components\Textarea::make('reason')->visible(fn(Forms\Get $get) => $get('operation') === 'cancel')->required(fn(Forms\Get $get) => $get('operation') === 'cancel')])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $task = $this->taskForConstruction($record, $data['task_id']);
                            $this->run(function () use ($task, $data): void {
                                $service = app(ConstructionService::class);
                                match ($data['operation']) { 'start' => $service->startTask(auth()->user(), $task), 'complete' => $service->completeTask(auth()->user(), $task), 'cancel' => $service->cancelTask(auth()->user(), $task, $data['reason']), };
                            }, 'Task updated');
                        }),
                    Tables\Actions\Action::make('recordProgress')->label('Record Progress')->icon('heroicon-o-chart-bar')->tooltip('Record immutable task progress')
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('task_id')->label('Task')->options($this->taskOptions($r))->required()->searchable(), Forms\Components\TextInput::make('progress_percentage')->numeric()->minValue(0)->maxValue(100)->required(), Forms\Components\DatePicker::make('reported_at')->default(today())->maxDate(today())->required(), Forms\Components\Textarea::make('notes')])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $task = $this->taskForConstruction($record, $data['task_id']);
                            $this->run(fn() => app(ConstructionService::class)->recordProgress(auth()->user(), $task, $data), 'Progress recorded');
                        }),
                    Tables\Actions\Action::make('correctProgress')->label('Correct Progress')->icon('heroicon-o-arrow-path')->tooltip('Create a controlled progress correction')
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('progress_update_id')->label('Current progress update')->options($this->progressOptions($r))->required()->searchable(), Forms\Components\TextInput::make('progress_percentage')->numeric()->minValue(0)->maxValue(100)->required(), Forms\Components\DatePicker::make('reported_at')->default(today())->maxDate(today())->required(), Forms\Components\Textarea::make('correction_reason')->required(), Forms\Components\Textarea::make('notes')])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $update = $this->progressUpdateForConstruction($record, $data['progress_update_id']);
                            $this->run(fn() => app(ConstructionService::class)->correctProgress(auth()->user(), $update, $data), 'Progress correction recorded');
                        }),
                    Tables\Actions\Action::make('recordInspection')->label('Record Inspection')->icon('heroicon-o-clipboard-document-check')->tooltip('Record an inspection result')
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('task_id')->label('Task awaiting inspection')->options($this->taskOptions($r, ['awaiting_inspection']))->required()->searchable(), Forms\Components\Select::make('inspector_party_id')->label('Inspector party')->options(Party::withoutGlobalScopes()->where('company_id', $r->company_id)->where('is_active', true)->pluck('name', 'id'))->searchable(), Forms\Components\Select::make('result')->options(['passed' => 'Passed', 'failed' => 'Failed', 'passed_with_notes' => 'Passed with notes'])->required(), Forms\Components\DatePicker::make('inspection_date')->default(today())->maxDate(today())->required(), Forms\Components\Textarea::make('notes')])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $task = $this->taskForConstruction($record, $data['task_id']);
                            $this->run(fn() => app(ConstructionService::class)->recordInspection(auth()->user(), $task, $data), 'Inspection recorded');
                        }),
                    Tables\Actions\Action::make('createIssue')->label('Create Issue')->icon('heroicon-o-exclamation-triangle')->color('danger')->tooltip('Record a package or task issue')
                        ->form(fn(ProjectConstruction $r) => $this->issueForm($r))
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $package = $record->workPackages()->findOrFail($data['package_id']);
                            $this->run(fn() => app(ConstructionService::class)->createIssue(auth()->user(), $package, $data), 'Issue created');
                        }),
                    Tables\Actions\Action::make('manageIssue')->label('Issue lifecycle')->icon('heroicon-o-check-badge')->tooltip('Start, resolve, or close an issue')
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('issue_id')->label('Issue')->options($this->issueOptions($r))->required()->searchable(), Forms\Components\Select::make('operation')->options(['in_progress' => 'Start work', 'resolved' => 'Resolve', 'closed' => 'Close'])->required()->live(), Forms\Components\Textarea::make('resolution_notes')->visible(fn(Forms\Get $get) => $get('operation') === 'resolved')->required(fn(Forms\Get $get) => $get('operation') === 'resolved')])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $issue = $this->issueForConstruction($record, $data['issue_id']);
                            $this->run(fn() => app(ConstructionService::class)->transitionIssue(auth()->user(), $issue, $data['operation'], $data['resolution_notes'] ?? null), 'Issue updated');
                        }),
                    Tables\Actions\Action::make('recordDelay')->label('Record Delay')->icon('heroicon-o-clock')->tooltip('Record an immutable schedule delay')
                        ->form(fn(ProjectConstruction $r) => $this->delayForm($r))
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $package = $record->workPackages()->findOrFail($data['package_id']);
                            $this->run(fn() => app(ConstructionService::class)->recordDelay(auth()->user(), $package, $data), 'Delay recorded');
                        }),
                ])->tooltip('Manage packages, tasks, progress, inspections, issues, and delays'),
            ]);
    }

    private function constructionForm(): array
    {
        return [
            Forms\Components\Select::make('manager_party_id')
                ->label('Manager / responsible party')
                ->options(fn() => Party::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->where('is_active', true)->pluck('name', 'id'))
                ->searchable(),
            Forms\Components\DatePicker::make('planned_start_date'),
            Forms\Components\DatePicker::make('expected_completion_date'),
            Forms\Components\Textarea::make('notes'),
        ];
    }

    private function packageForm(ProjectConstruction $construction): array
    {
        return [
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('code'),
            Forms\Components\Select::make('responsible_party_id')->label('Contractor / responsible party')->options(Party::withoutGlobalScopes()->where('company_id', $construction->company_id)->where('is_active', true)->pluck('name', 'id'))->searchable(),
            Forms\Components\Select::make('budget_line_id')->label('Budget line')->options(fn() => BudgetItem::withoutGlobalScopes()->whereHas('category.budget', fn($q) => $q->where('project_id', $construction->project_id)->where('status', 'approved'))->with('category')->get()->mapWithKeys(fn(BudgetItem $item) => [$item->budget_line_id => "{$item->category->name} — {$item->name}"])->all())->helperText('Optional stable budget line from this project’s approved budget.')->searchable(),
            Forms\Components\DatePicker::make('planned_start_date'),
            Forms\Components\DatePicker::make('planned_end_date'),
            Forms\Components\Textarea::make('description'),
            Forms\Components\Textarea::make('notes'),
        ];
    }

    private function constructionInfolist(): array
    {
        return [
            Infolists\Components\Tabs::make('Construction')->tabs([
                Infolists\Components\Tabs\Tab::make('Overview')->schema([
                    Infolists\Components\TextEntry::make('status')->badge()->colors(['gray' => ['planned', 'cancelled'], 'info' => 'in_progress', 'success' => 'completed']),
                    Infolists\Components\TextEntry::make('progress_percentage')->label('Overall progress')->suffix('%'),
                    Infolists\Components\TextEntry::make('managerParty.name')->label('Manager')->placeholder('—'),
                    Infolists\Components\TextEntry::make('planned_start_date')->date()->placeholder('—'),
                    Infolists\Components\TextEntry::make('expected_completion_date')->date()->placeholder('—'),
                    Infolists\Components\TextEntry::make('notes')->columnSpanFull()->placeholder('No notes'),
                ])->columns(3),
                Infolists\Components\Tabs\Tab::make('Work Packages')->schema([
                    Infolists\Components\RepeatableEntry::make('workPackages')->label('')->schema([
                        Infolists\Components\TextEntry::make('name')->weight('bold'),
                        Infolists\Components\TextEntry::make('status')->badge(),
                        Infolists\Components\TextEntry::make('progress_percentage')->suffix('%'),
                        Infolists\Components\TextEntry::make('responsibleParty.name')->label('Responsible party')->placeholder('—'),
                        Infolists\Components\RepeatableEntry::make('tasks')->label('Tasks')->schema([
                            Infolists\Components\TextEntry::make('name')->weight('medium'),
                            Infolists\Components\TextEntry::make('status')->badge(),
                            Infolists\Components\TextEntry::make('progress_percentage')->suffix('%'),
                        ])->columns(3),
                    ])->columns(4),
                ]),
                Infolists\Components\Tabs\Tab::make('Issues')->schema([
                    Infolists\Components\RepeatableEntry::make('workPackages')->label('')->schema([
                        Infolists\Components\TextEntry::make('name')->label('Work package')->weight('bold'),
                        Infolists\Components\RepeatableEntry::make('issues')->label('Issues')->schema([
                            Infolists\Components\TextEntry::make('title')->weight('bold'),
                            Infolists\Components\TextEntry::make('severity')->badge()->colors(['gray' => 'low', 'warning' => ['medium', 'high'], 'danger' => 'critical']),
                            Infolists\Components\TextEntry::make('status')->badge(),
                            Infolists\Components\TextEntry::make('task.name')->label('Task')->placeholder('Package issue'),
                        ])->columns(4),
                    ])->columns(1),
                ]),
                Infolists\Components\Tabs\Tab::make('Delays')->schema([
                    Infolists\Components\RepeatableEntry::make('workPackages')->label('')->schema([
                        Infolists\Components\TextEntry::make('name')->label('Work package')->weight('bold'),
                        Infolists\Components\RepeatableEntry::make('delays')->label('Delay history')->schema([
                            Infolists\Components\TextEntry::make('reason_code')->badge(),
                            Infolists\Components\TextEntry::make('baseline_end_date')->date(),
                            Infolists\Components\TextEntry::make('revised_end_date')->date(),
                            Infolists\Components\TextEntry::make('delay_days')->label('Days')->suffix(' days'),
                        ])->columns(4),
                    ])->columns(1),
                ]),
            ]),
        ];
    }

    private function packageOptions(ProjectConstruction $construction): array
    {
        return $construction->workPackages()->orderBy('name')->pluck('name', 'id')->all();
    }
    private function taskOptions(ProjectConstruction $construction, ?array $statuses = null): array
    {
        $query = ConstructionWorkPackageTask::withoutGlobalScopes()->whereHas('workPackage', fn($q) => $q->where('project_construction_id', $construction->id));
        if ($statuses)
            $query->whereIn('status', $statuses);
        return $query->orderBy('name')->get()->mapWithKeys(fn($task) => [$task->id => "{$task->workPackage->name} — {$task->name}"])->all();
    }
    private function progressOptions(ProjectConstruction $construction): array
    {
        return ConstructionProgressUpdate::withoutGlobalScopes()->whereHas('task.workPackage', fn($q) => $q->where('project_construction_id', $construction->id))->latest('id')->get()->mapWithKeys(fn($update) => [$update->id => "{$update->task->name} — {$update->progress_percentage}%"])->all();
    }
    private function issueOptions(ProjectConstruction $construction): array
    {
        return ConstructionIssue::withoutGlobalScopes()->whereHas('workPackage', fn($q) => $q->where('project_construction_id', $construction->id))->whereNotIn('status', ['closed'])->orderBy('title')->pluck('title', 'id')->all();
    }
    private function editPackageForm(ProjectConstruction $construction): array
    {
        return [
            Forms\Components\Select::make('package_id')->label('Work package')->options($this->packageOptions($construction))->required()->searchable()->live()->afterStateUpdated(function ($state, Forms\Set $set): void {
                $package = ConstructionWorkPackage::withoutGlobalScopes()->find($state);
                if (!$package)
                    return;
                foreach (['name', 'code', 'responsible_party_id', 'budget_line_id', 'planned_start_date', 'planned_end_date', 'description', 'notes'] as $field)
                    $set($field, $package->getAttribute($field));
            }),
            ...$this->packageForm($construction)
        ];
    }
    private function editTaskForm(ProjectConstruction $construction): array
    {
        return [
            Forms\Components\Select::make('task_id')->label('Task')->options($this->taskOptions($construction))->required()->searchable()->live()->afterStateUpdated(function ($state, Forms\Set $set): void {
                $task = ConstructionWorkPackageTask::withoutGlobalScopes()->find($state);
                if (!$task)
                    return;
                foreach (['name', 'assigned_party_id', 'requires_inspection', 'planned_start_date', 'planned_end_date', 'description', 'notes'] as $field)
                    $set($field, $task->getAttribute($field));
            }),
            ...array_slice($this->taskForm($construction), 1)
        ];
    }
    private function taskForConstruction(ProjectConstruction $construction, int $taskId): ConstructionWorkPackageTask
    {
        $task = ConstructionWorkPackageTask::withoutGlobalScopes()->findOrFail($taskId);
        if ((int) $task->workPackage->project_construction_id !== (int) $construction->id)
            throw ValidationException::withMessages(['task_id' => 'The selected task does not belong to this construction.']);
        return $task;
    }
    private function progressUpdateForConstruction(ProjectConstruction $construction, int $updateId): ConstructionProgressUpdate
    {
        $update = ConstructionProgressUpdate::withoutGlobalScopes()->findOrFail($updateId);
        if ((int) $update->task->workPackage->project_construction_id !== (int) $construction->id)
            throw ValidationException::withMessages(['progress_update_id' => 'The selected progress update does not belong to this construction.']);
        return $update;
    }
    private function issueForConstruction(ProjectConstruction $construction, int $issueId): ConstructionIssue
    {
        $issue = ConstructionIssue::withoutGlobalScopes()->findOrFail($issueId);
        if ((int) $issue->workPackage->project_construction_id !== (int) $construction->id)
            throw ValidationException::withMessages(['issue_id' => 'The selected issue does not belong to this construction.']);
        return $issue;
    }
    private function taskForm(ProjectConstruction $construction): array
    {
        return [Forms\Components\Select::make('package_id')->label('Work package')->options($this->packageOptions($construction))->required()->searchable(), Forms\Components\TextInput::make('name')->required(), Forms\Components\Select::make('assigned_party_id')->label('Responsible party')->options(Party::withoutGlobalScopes()->where('company_id', $construction->company_id)->where('is_active', true)->pluck('name', 'id'))->searchable(), Forms\Components\Toggle::make('requires_inspection'), Forms\Components\DatePicker::make('planned_start_date'), Forms\Components\DatePicker::make('planned_end_date'), Forms\Components\Textarea::make('description'), Forms\Components\Textarea::make('notes')];
    }
    private function issueForm(ProjectConstruction $construction): array
    {
        return [Forms\Components\Select::make('package_id')->label('Work package')->options($this->packageOptions($construction))->required()->searchable(), Forms\Components\Select::make('construction_work_package_task_id')->label('Task (optional)')->options($this->taskOptions($construction))->searchable(), Forms\Components\Select::make('assigned_to_user_id')->label('Internal owner')->options(User::query()->where('company_id', $construction->company_id)->pluck('name', 'id'))->searchable(), Forms\Components\TextInput::make('title')->required(), Forms\Components\Textarea::make('description')->required(), Forms\Components\Select::make('severity')->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'])->required(), Forms\Components\DatePicker::make('opened_at')->default(today())->maxDate(today())->required()];
    }
    private function delayForm(ProjectConstruction $construction): array
    {
        return [Forms\Components\Select::make('package_id')->label('Work package')->options($this->packageOptions($construction))->required()->searchable(), Forms\Components\Select::make('construction_work_package_task_id')->label('Task (optional)')->options($this->taskOptions($construction))->searchable(), Forms\Components\DatePicker::make('revised_end_date')->required(), Forms\Components\Select::make('reason_code')->options(['material' => 'Material', 'contractor' => 'Contractor', 'weather' => 'Weather', 'design_change' => 'Design change', 'inspection' => 'Inspection', 'authority' => 'Authority', 'other' => 'Other'])->required(), Forms\Components\DatePicker::make('reported_at')->default(today())->maxDate(today())->required(), Forms\Components\Textarea::make('description')];
    }

    private function run(callable $operation, string $success): void
    {
        try {
            $operation();
            Notification::make()->success()->title($success)->send();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Cannot update construction')->body(collect($exception->errors())->flatten()->first())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title('Unauthorized')->body('You do not have permission to perform this construction action.')->send();
        }
    }
}
