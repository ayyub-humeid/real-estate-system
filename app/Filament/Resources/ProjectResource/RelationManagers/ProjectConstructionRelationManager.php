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

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Construction');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table->emptyStateHeading(__('No construction lifecycle yet'))
            ->emptyStateDescription(__('Create the project construction plan, then organize work packages and site tasks.'))
            ->columns([
                Tables\Columns\TextColumn::make('execution_number')->label(__('Execution'))->prefix('#'),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()
                    ->colors(['gray' => ['planned', 'cancelled'], 'info' => 'in_progress', 'success' => 'completed'])
                    ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label(__('Overall progress'))
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('managerParty.name')
                    ->label(__('Manager'))
                    ->placeholder(__('Not assigned')),
                Tables\Columns\TextColumn::make('work_packages_count')
                    ->counts('workPackages')
                    ->label(__('Work Packages')),
                Tables\Columns\TextColumn::make('expected_completion_date')
                    ->label(__('Expected'))
                    ->date()
                    ->placeholder(__('—')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createConstruction')
                    ->label(__('Create Construction'))
                    ->icon('heroicon-o-plus')
                    ->visible(fn(): bool => !$this->getOwnerRecord()->activeConstruction()->exists() && auth()->user()->can('create', ProjectConstruction::class))
                    ->form($this->constructionForm())
                    ->action(fn(array $data) => $this->run(fn() => app(ConstructionService::class)->createConstruction(auth()->user(), $this->getOwnerRecord(), $data), __('Construction created'))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('Details'))->icon('heroicon-o-eye')->tooltip(__('View construction, packages, tasks, and history'))->modalWidth('7xl')->infolist($this->constructionInfolist()),
                Tables\Actions\Action::make('editConstruction')->label(__('Edit'))->icon('heroicon-o-pencil-square')->tooltip(__('Edit construction planning details'))
                    ->visible(fn(ProjectConstruction $record): bool => auth()->user()->can('update', $record))
                    ->fillForm(fn(ProjectConstruction $record) => $record->only(['planned_start_date', 'expected_completion_date', 'manager_party_id', 'notes']))->form($this->constructionForm())
                    ->action(fn(ProjectConstruction $record, array $data) => $this->run(fn() => app(ConstructionService::class)->updateConstruction(auth()->user(), $record, $data), __('Construction updated'))),
                Tables\Actions\DeleteAction::make()->label(__('Delete'))->tooltip(__('Delete an empty planned construction'))->visible(fn(ProjectConstruction $record): bool => auth()->user()->can('delete', $record)),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('startConstruction')
                        ->label(__('Start construction'))
                        ->icon('heroicon-o-play')->tooltip(__('Start construction'))
                        ->visible(fn(ProjectConstruction $r) => $r->status === 'planned')
                        ->action(fn(ProjectConstruction $r) =>
                            $this->run(fn() =>
                                app(ConstructionService::class)->startConstruction(auth()->user(), $r), __('Construction started'))),
                    Tables\Actions\Action::make('completeConstruction')
                        ->label(__('Complete construction'))
                        ->icon('heroicon-o-check-circle')->color('success')->tooltip(__('Complete construction'))
                        ->visible(fn(ProjectConstruction $r) => $r->status === 'in_progress')
                        ->action(fn(ProjectConstruction $r) => $this->run(fn() => app(ConstructionService::class)->completeConstruction(auth()->user(), $r), __('Construction completed'))),
                    Tables\Actions\Action::make('cancelConstruction')
                        ->label(__('Cancel construction'))
                        ->icon('heroicon-o-x-circle')->color('danger')->tooltip(__('Cancel construction'))
                        ->visible(fn(ProjectConstruction $r) => in_array($r->status, ['planned', 'in_progress'], true))
                        ->form([Forms\Components\Textarea::make('reason')->label(__('Reason'))->required()])
                        ->action(fn(ProjectConstruction $r, array $data) => $this->run(fn() => app(ConstructionService::class)->cancelConstruction(auth()->user(), $r, $data['reason']), __('Construction cancelled'))),
                ])->tooltip(__('Manage construction lifecycle')),
                Tables\Actions\Action::make('createPackage')->label(__('Add Work Package'))->icon(
                    'heroicon-o-wrench-screwdriver'
                )->tooltip(__('Add a major execution package'))
                    ->visible(fn(ProjectConstruction $r) => auth()->user()->can('create', ConstructionWorkPackage::class) && !in_array($r->status, ['completed', 'cancelled'], true))
                    ->form(fn(ProjectConstruction $r) => $this->packageForm($r))
                    ->action(fn(ProjectConstruction $r, array $data) => $this->run(fn() => app(ConstructionService::class)->createWorkPackage(auth()->user(), $r, $data), __('Work package created'))),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('managePackage')->label(__('Package lifecycle'))->icon('heroicon-o-wrench-screwdriver')->tooltip(__('Start, complete, or cancel a work package'))
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('package_id')->label(__('Work package'))->options($this->packageOptions($r))->required()->searchable(), Forms\Components\Select::make('operation')->label(__('Action'))->options(['start' => __('Start work'), 'complete' => __('Complete'), 'cancel' => __('Cancel')])->required()->live(), Forms\Components\Textarea::make('reason')->label(__('Reason'))->visible(fn(Forms\Get $get) => $get('operation') === 'cancel')->required(fn(Forms\Get $get) => $get('operation') === 'cancel')])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $package = $record->workPackages()->findOrFail($data['package_id']);
                            $this->run(function () use ($package, $data): void {
                                $service = app(ConstructionService::class);
                                match ($data['operation']) {
                                    'start' => $service->startWorkPackage(auth()->user(), $package),
                                    'complete' => $service->completeWorkPackage(auth()->user(), $package),
                                    'cancel' => $service->cancelWorkPackage(auth()->user(), $package, $data['reason']),
                                };
                            }, __('Work package updated'));
                        }),
                    Tables\Actions\Action::make('createTask')->label(__('Add Task'))->icon('heroicon-o-plus-circle')->tooltip(__('Add a task to a work package'))
                        ->form(fn(ProjectConstruction $r) => $this->taskForm($r))
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $package = $record->workPackages()->findOrFail($data['package_id']);
                            $this->run(fn() => app(ConstructionService::class)->createTask(auth()->user(), $package, $data), __('Task created'));
                        }),
                    Tables\Actions\Action::make('editPackage')->label(__('Edit Work Package'))->icon('heroicon-o-pencil-square')->tooltip(__('Edit mutable work package metadata'))
                        ->form(fn(ProjectConstruction $r) => $this->editPackageForm($r))
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $package = $record->workPackages()->findOrFail($data['package_id']);
                            $this->run(fn() => app(ConstructionService::class)->updateWorkPackage(auth()->user(), $package, $data), __('Work package updated'));
                        }),
                    Tables\Actions\Action::make('deletePackage')->label(__('Delete Work Package'))->icon('heroicon-o-trash')->color('danger')->tooltip(__('Delete an empty planned work package'))
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('package_id')->label(__('Work package'))->options($this->packageOptions($r))->required()->searchable()])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $package = $record->workPackages()->findOrFail($data['package_id']);
                            $this->run(fn() => app(ConstructionService::class)->deleteWorkPackage(auth()->user(), $package), __('Work package deleted'));
                        }),
                    Tables\Actions\Action::make('editTask')->label(__('Edit Task'))->icon('heroicon-o-pencil')->tooltip(__('Edit mutable task metadata'))
                        ->form(fn(ProjectConstruction $r) => $this->editTaskForm($r))
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $task = $this->taskForConstruction($record, $data['task_id']);
                            $this->run(fn() => app(ConstructionService::class)->updateTask(auth()->user(), $task, $data), __('Task updated'));
                        }),
                    Tables\Actions\Action::make('deleteTask')->label(__('Delete Task'))->icon('heroicon-o-trash')->color('danger')->tooltip(__('Delete an empty planned task'))
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('task_id')->label(__('Task'))->options($this->taskOptions($r))->required()->searchable()])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $task = $this->taskForConstruction($record, $data['task_id']);
                            $this->run(fn() => app(ConstructionService::class)->deleteTask(auth()->user(), $task), __('Task deleted'));
                        }),
                    Tables\Actions\Action::make('manageTask')->label(__('Task lifecycle'))->icon('heroicon-o-play-circle')->tooltip(__('Start, complete, or cancel a task'))
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('task_id')->label(__('Task'))->options($this->taskOptions($r))->required()->searchable(), Forms\Components\Select::make('operation')->label(__('Action'))->options(['start' => __('Start work'), 'complete' => __('Complete'), 'cancel' => __('Cancel')])->required()->live(), Forms\Components\Textarea::make('reason')->label(__('Reason'))->visible(fn(Forms\Get $get) => $get('operation') === 'cancel')->required(fn(Forms\Get $get) => $get('operation') === 'cancel')])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $task = $this->taskForConstruction($record, $data['task_id']);
                            $this->run(function () use ($task, $data): void {
                                $service = app(ConstructionService::class);
                                match ($data['operation']) { 'start' => $service->startTask(auth()->user(), $task), 'complete' => $service->completeTask(auth()->user(), $task), 'cancel' => $service->cancelTask(auth()->user(), $task, $data['reason']), };
                            }, __('Task updated'));
                        }),
                    Tables\Actions\Action::make('recordProgress')->label(__('Record Progress'))->icon('heroicon-o-chart-bar')->tooltip(__('Record immutable task progress'))
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('task_id')->label(__('Task'))->options($this->taskOptions($r))->required()->searchable(), Forms\Components\TextInput::make('progress_percentage')->label(__('Overall progress'))->numeric()->minValue(0)->maxValue(100)->required(), Forms\Components\DatePicker::make('reported_at')->label(__('Reported At'))->default(today())->maxDate(today())->required(), Forms\Components\Textarea::make('notes')->label(__('Notes'))])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $task = $this->taskForConstruction($record, $data['task_id']);
                            $this->run(fn() => app(ConstructionService::class)->recordProgress(auth()->user(), $task, $data), __('Progress recorded'));
                        }),
                    Tables\Actions\Action::make('correctProgress')->label(__('Correct Progress'))->icon('heroicon-o-arrow-path')->tooltip(__('Create a controlled progress correction'))
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('progress_update_id')->label(__('Current progress update'))->options($this->progressOptions($r))->required()->searchable(), Forms\Components\TextInput::make('progress_percentage')->label(__('Overall progress'))->numeric()->minValue(0)->maxValue(100)->required(), Forms\Components\DatePicker::make('reported_at')->label(__('Reported At'))->default(today())->maxDate(today())->required(), Forms\Components\Textarea::make('correction_reason')->label(__('Correction reason'))->required(), Forms\Components\Textarea::make('notes')->label(__('Notes'))])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $update = $this->progressUpdateForConstruction($record, $data['progress_update_id']);
                            $this->run(fn() => app(ConstructionService::class)->correctProgress(auth()->user(), $update, $data), __('Progress correction recorded'));
                        }),
                    Tables\Actions\Action::make('recordInspection')->label(__('Record Inspection'))->icon('heroicon-o-clipboard-document-check')->tooltip(__('Record an inspection result'))
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('task_id')->label(__('Task awaiting inspection'))->options($this->taskOptions($r, ['awaiting_inspection']))->required()->searchable(), Forms\Components\Select::make('inspector_party_id')->label(__('Inspector party'))->options(Party::withoutGlobalScopes()->where('company_id', $r->company_id)->where('is_active', true)->pluck('name', 'id'))->searchable(), Forms\Components\Select::make('result')->label(__('Result'))->options(['passed' => __('Passed'), 'failed' => __('Failed'), 'passed_with_notes' => __('Passed with notes')])->required(), Forms\Components\DatePicker::make('inspection_date')->label(__('Inspection date'))->default(today())->maxDate(today())->required(), Forms\Components\Textarea::make('notes')->label(__('Notes'))])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $task = $this->taskForConstruction($record, $data['task_id']);
                            $this->run(fn() => app(ConstructionService::class)->recordInspection(auth()->user(), $task, $data), __('Inspection recorded'));
                        }),
                    Tables\Actions\Action::make('createIssue')->label(__('Create Issue'))->icon('heroicon-o-exclamation-triangle')->color('danger')->tooltip(__('Record a package or task issue'))
                        ->form(fn(ProjectConstruction $r) => $this->issueForm($r))
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $package = $record->workPackages()->findOrFail($data['package_id']);
                            $this->run(fn() => app(ConstructionService::class)->createIssue(auth()->user(), $package, $data), __('Issue created'));
                        }),
                    Tables\Actions\Action::make('manageIssue')->label(__('Issue lifecycle'))->icon('heroicon-o-check-badge')->tooltip(__('Start, resolve, or close an issue'))
                        ->form(fn(ProjectConstruction $r) => [Forms\Components\Select::make('issue_id')->label(__('Issue'))->options($this->issueOptions($r))->required()->searchable(), Forms\Components\Select::make('operation')->label(__('Action'))->options(['in_progress' => __('Start work'), 'resolved' => __('Resolve'), 'closed' => __('Closed')])->required()->live(), Forms\Components\Textarea::make('resolution_notes')->label(__('Resolution notes'))->visible(fn(Forms\Get $get) => $get('operation') === 'resolved')->required(fn(Forms\Get $get) => $get('operation') === 'resolved')])
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $issue = $this->issueForConstruction($record, $data['issue_id']);
                            $this->run(fn() => app(ConstructionService::class)->transitionIssue(auth()->user(), $issue, $data['operation'], $data['resolution_notes'] ?? null), __('Issue updated'));
                        }),
                    Tables\Actions\Action::make('recordDelay')->label(__('Record Delay'))->icon('heroicon-o-clock')->tooltip(__('Record an immutable schedule delay'))
                        ->form(fn(ProjectConstruction $r) => $this->delayForm($r))
                        ->action(function (ProjectConstruction $record, array $data): void {
                            $package = $record->workPackages()->findOrFail($data['package_id']);
                            $this->run(fn() => app(ConstructionService::class)->recordDelay(auth()->user(), $package, $data), __('Delay recorded'));
                        }),
                ])->tooltip(__('Manage packages, tasks, progress, inspections, issues, and delays')),
            ]);
    }

    private function constructionForm(): array
    {
        return [
            Forms\Components\Select::make('manager_party_id')
                ->label(__('Manager / responsible party'))
                ->options(fn() => Party::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->where('is_active', true)->pluck('name', 'id'))
                ->searchable(),
            Forms\Components\DatePicker::make('planned_start_date')->label(__('Planned Start Date')),
            Forms\Components\DatePicker::make('expected_completion_date')->label(__('Expected Completion Date')),
            Forms\Components\Textarea::make('notes')->label(__('Notes')),
        ];
    }

    private function packageForm(ProjectConstruction $construction): array
    {
        return [
            Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
            Forms\Components\TextInput::make('code')->label(__('Code')),
            Forms\Components\Select::make('responsible_party_id')->label(__('Contractor / responsible party'))->options(Party::withoutGlobalScopes()->where('company_id', $construction->company_id)->where('is_active', true)->pluck('name', 'id'))->searchable(),
            Forms\Components\Select::make('budget_line_id')->label(__('Budget line'))->options(fn() => BudgetItem::withoutGlobalScopes()->whereHas('category.budget', fn($q) => $q->where('project_id', $construction->project_id)->where('status', 'approved'))->with('category')->get()->mapWithKeys(fn(BudgetItem $item) => [$item->budget_line_id => "{$item->category->name} — {$item->name}"])->all())->helperText(__('Optional stable budget line from this project’s approved budget.'))->searchable(),
            Forms\Components\DatePicker::make('planned_start_date')->label(__('Planned Start Date')),
            Forms\Components\DatePicker::make('planned_end_date')->label(__('Planned End Date')),
            Forms\Components\Textarea::make('description')->label(__('Description')),
            Forms\Components\Textarea::make('notes')->label(__('Notes')),
        ];
    }

    private function constructionInfolist(): array
    {
        return [
            Infolists\Components\Tabs::make('Construction')->tabs([
                Infolists\Components\Tabs\Tab::make(__('Overview'))->schema([
                    Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()->colors(['gray' => ['planned', 'cancelled'], 'info' => 'in_progress', 'success' => 'completed'])
                        ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                    Infolists\Components\TextEntry::make('progress_percentage')->label(__('Overall progress'))->suffix('%'),
                    Infolists\Components\TextEntry::make('managerParty.name')->label(__('Manager'))->placeholder(__('—')),
                    Infolists\Components\TextEntry::make('planned_start_date')->label(__('Planned Start Date'))->date()->placeholder(__('—')),
                    Infolists\Components\TextEntry::make('expected_completion_date')->label(__('Expected Completion Date'))->date()->placeholder(__('—')),
                    Infolists\Components\TextEntry::make('notes')->label(__('Notes'))->columnSpanFull()->placeholder(__('No notes')),
                ])->columns(3),
                Infolists\Components\Tabs\Tab::make(__('Work Packages'))->schema([
                    Infolists\Components\RepeatableEntry::make('workPackages')->label('')->schema([
                        Infolists\Components\TextEntry::make('name')->label(__('Name'))->weight('bold'),
                        Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()
                            ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                        Infolists\Components\TextEntry::make('progress_percentage')->label(__('Overall progress'))->suffix('%'),
                        Infolists\Components\TextEntry::make('responsibleParty.name')->label(__('Responsible party'))->placeholder(__('—')),
                        Infolists\Components\RepeatableEntry::make('tasks')->label(__('Tasks'))->schema([
                            Infolists\Components\TextEntry::make('name')->label(__('Name'))->weight('medium'),
                            Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()
                                ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                            Infolists\Components\TextEntry::make('progress_percentage')->label(__('Overall progress'))->suffix('%'),
                        ])->columns(3),
                    ])->columns(4),
                ]),
                Infolists\Components\Tabs\Tab::make(__('Issues'))->schema([
                    Infolists\Components\RepeatableEntry::make('workPackages')->label('')->schema([
                        Infolists\Components\TextEntry::make('name')->label(__('Work package'))->weight('bold'),
                        Infolists\Components\RepeatableEntry::make('issues')->label(__('Issues'))->schema([
                            Infolists\Components\TextEntry::make('title')->label(__('Title'))->weight('bold'),
                            Infolists\Components\TextEntry::make('severity')->label(__('Severity'))->badge()->colors(['gray' => 'low', 'warning' => ['medium', 'high'], 'danger' => 'critical']),
                            Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()
                                ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                            Infolists\Components\TextEntry::make('task.name')->label(__('Task'))->placeholder(__('Package issue')),
                        ])->columns(4),
                    ])->columns(1),
                ]),
                Infolists\Components\Tabs\Tab::make(__('Delays'))->schema([
                    Infolists\Components\RepeatableEntry::make('workPackages')->label('')->schema([
                        Infolists\Components\TextEntry::make('name')->label(__('Work package'))->weight('bold'),
                        Infolists\Components\RepeatableEntry::make('delays')->label(__('Delay history'))->schema([
                            Infolists\Components\TextEntry::make('reason_code')->label(__('Reason code'))->badge(),
                            Infolists\Components\TextEntry::make('baseline_end_date')->label(__('Baseline end date'))->date(),
                            Infolists\Components\TextEntry::make('revised_end_date')->label(__('Revised end date'))->date(),
                            Infolists\Components\TextEntry::make('delay_days')->label(__('Days'))->suffix(' days'),
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
            Forms\Components\Select::make('package_id')->label(__('Work package'))->options($this->packageOptions($construction))->required()->searchable()->live()->afterStateUpdated(function ($state, Forms\Set $set): void {
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
            Forms\Components\Select::make('task_id')->label(__('Task'))->options($this->taskOptions($construction))->required()->searchable()->live()->afterStateUpdated(function ($state, Forms\Set $set): void {
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
        return [Forms\Components\Select::make('package_id')->label(__('Work package'))->options($this->packageOptions($construction))->required()->searchable(), Forms\Components\TextInput::make('name')->label(__('Name'))->required(), Forms\Components\Select::make('assigned_party_id')->label(__('Responsible party'))->options(Party::withoutGlobalScopes()->where('company_id', $construction->company_id)->where('is_active', true)->pluck('name', 'id'))->searchable(), Forms\Components\Toggle::make('requires_inspection')->label(__('Requires inspection')), Forms\Components\DatePicker::make('planned_start_date')->label(__('Planned Start Date')), Forms\Components\DatePicker::make('planned_end_date')->label(__('Planned End Date')), Forms\Components\Textarea::make('description')->label(__('Description')), Forms\Components\Textarea::make('notes')->label(__('Notes'))];
    }
    private function issueForm(ProjectConstruction $construction): array
    {
        return [Forms\Components\Select::make('package_id')->label(__('Work package'))->options($this->packageOptions($construction))->required()->searchable(), Forms\Components\Select::make('construction_work_package_task_id')->label(__('Task (optional)'))->options($this->taskOptions($construction))->searchable(), Forms\Components\Select::make('assigned_to_user_id')->label(__('Internal owner'))->options(User::query()->where('company_id', $construction->company_id)->pluck('name', 'id'))->searchable(), Forms\Components\TextInput::make('title')->label(__('Title'))->required(), Forms\Components\Textarea::make('description')->label(__('Description'))->required(), Forms\Components\Select::make('severity')->label(__('Severity'))->options(['low' => __('Low'), 'medium' => __('Medium'), 'high' => __('High'), 'critical' => __('Critical')])->required(), Forms\Components\DatePicker::make('opened_at')->label(__('Opened At'))->default(today())->maxDate(today())->required()];
    }
    private function delayForm(ProjectConstruction $construction): array
    {
        return [Forms\Components\Select::make('package_id')->label(__('Work package'))->options($this->packageOptions($construction))->required()->searchable(), Forms\Components\Select::make('construction_work_package_task_id')->label(__('Task (optional)'))->options($this->taskOptions($construction))->searchable(), Forms\Components\DatePicker::make('revised_end_date')->label(__('Revised end date'))->required(), Forms\Components\Select::make('reason_code')->label(__('Reason code'))->options(['material' => __('Material'), 'contractor' => __('Contractor'), 'weather' => __('Weather'), 'design_change' => __('Design change'), 'inspection' => __('Inspection'), 'authority' => __('Authority'), 'other' => __('Other')])->required(), Forms\Components\DatePicker::make('reported_at')->label(__('Reported At'))->default(today())->maxDate(today())->required(), Forms\Components\Textarea::make('description')->label(__('Description'))];
    }

    private function run(callable $operation, string $success): void
    {
        try {
            $operation();
            Notification::make()->success()->title($success)->send();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('Cannot update construction'))->body(collect($exception->errors())->flatten()->first())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('Unauthorized'))->body(__('You do not have permission to perform this construction action.'))->send();
        }
    }
}
