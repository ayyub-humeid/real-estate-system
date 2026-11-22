<?php

namespace App\Filament\Resources\ProjectConstructionResource\RelationManagers;

use App\Filament\Support\{ConstructionForms, RunsWorkflowAction};
use App\Models\{ConstructionWorkPackage, ProjectConstruction};
use App\Services\ConstructionService;
use App\Support\ConstructionHealth;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** The stages of the construction (foundation, structure, finishing…): the milestones a developer follows. */
class WorkPackagesRelationManager extends RelationManager
{
    use RunsWorkflowAction;

    protected static string $relationship = 'workPackages';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project.construction.tab_packages');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function construction(): ProjectConstruction
    {
        return $this->getOwnerRecord();
    }

    private function isOpenPackage(ConstructionWorkPackage $package): bool
    {
        return ! in_array($package->status, ['completed', 'cancelled'], true);
    }

    private function run(callable $operation, string $successKey, bool $form = false): mixed
    {
        return $this->runWorkflowAction(
            operation: $operation,
            successTitle: __('project.construction.done.'.$successKey),
            onSuccess: fn () => $this->getOwnerRecord()->refresh(),
            rethrowValidation: $form,
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(__('project.construction.packages_help'))
            ->modifyQueryUsing(fn (Builder $query) => $query->with('responsibleParty:id,name')
                ->withCount([
                    'delays',
                    'tasks',
                    'issues as open_issues_count' => fn ($q) => $q->whereNotIn('status', ['resolved', 'closed']),
                    'issues as serious_open_issues_count' => fn ($q) => $q->whereIn('severity', ['high', 'critical'])->whereNotIn('status', ['resolved', 'closed']),
                ]))
            ->defaultSort('planned_end_date')
            ->emptyStateHeading(__('project.construction.packages_empty_heading'))
            ->emptyStateDescription(__('project.construction.packages_empty_description'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('project.construction.fields.name'))->searchable()->weight('medium')
                    ->description(fn (ConstructionWorkPackage $record): ?string => $record->code),
                Tables\Columns\TextColumn::make('responsibleParty.name')->label(__('project.construction.fields.responsible'))->placeholder('—'),
                Tables\Columns\TextColumn::make('planned_end_date')->label(__('project.construction.fields.planned_end'))->date()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('progress_percentage')->label(__('project.construction.fields.progress'))
                    ->formatStateUsing(fn ($state): string => (int) $state.'%')->badge()->color('info'),
                Tables\Columns\TextColumn::make('status')->label(__('project.construction.fields.status'))->badge()
                    ->formatStateUsing(fn (?string $state): string => __('project.statuses.'.$state))
                    ->color(fn (?string $state): string => match ($state) { 'in_progress' => 'info', 'completed' => 'success', default => 'gray' }),
                Tables\Columns\TextColumn::make('schedule')->label(__('project.construction.fields.schedule'))->badge()
                    ->state(fn (ConstructionWorkPackage $record): string => ConstructionHealth::forPackage($record))
                    ->formatStateUsing(fn (string $state): string => __('project.construction.schedule.'.$state))
                    ->color(fn (string $state): string => ConstructionHealth::color($state))
                    ->tooltip(fn (string $state): string => __('project.construction.schedule_help.'.$state)),
                Tables\Columns\TextColumn::make('open_issues_count')->label(__('project.construction.fields.open_issues'))->alignCenter()
                    ->badge()->color(fn ($state): string => $state > 0 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('delays_count')->label(__('project.construction.fields.delays'))->alignCenter()
                    ->badge()->color(fn ($state): string => $state > 0 ? 'warning' : 'gray'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addPackage')
                    ->label(__('project.construction.actions.add_package'))->icon('heroicon-o-plus')
                    ->modalHeading(__('project.construction.actions.add_package'))
                    ->modalDescription(__('project.construction.actions.add_package_description'))
                    ->visible(fn (): bool => auth()->user()->can('create', ConstructionWorkPackage::class) && ! in_array($this->construction()->status, ['completed', 'cancelled'], true))
                    ->form(fn (): array => ConstructionForms::package($this->construction()))
                    ->action(fn (array $data) => $this->run(fn () => app(ConstructionService::class)->createWorkPackage(auth()->user(), $this->construction(), $data), 'package_created', true)),
            ])
            ->actions([
                Tables\Actions\Action::make('startPackage')
                    ->label(__('project.construction.actions.start_package'))->icon('heroicon-o-play')->color('info')
                    ->visible(fn (ConstructionWorkPackage $record): bool => $record->status === 'planned' && auth()->user()->can('start', $record))
                    ->action(fn (ConstructionWorkPackage $record) => $this->run(fn () => app(ConstructionService::class)->startWorkPackage(auth()->user(), $record), 'package_started')),
                Tables\Actions\Action::make('completePackage')
                    ->label(__('project.construction.actions.complete_package'))->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()->modalDescription(__('project.construction.actions.package_complete_description'))
                    ->visible(fn (ConstructionWorkPackage $record): bool => $record->status === 'in_progress' && auth()->user()->can('complete', $record))
                    ->action(fn (ConstructionWorkPackage $record) => $this->run(fn () => app(ConstructionService::class)->completeWorkPackage(auth()->user(), $record), 'package_completed')),
                Tables\Actions\Action::make('addTask')
                    ->label(__('project.construction.actions.add_task'))->icon('heroicon-o-plus-circle')
                    ->visible(fn (ConstructionWorkPackage $record): bool => $this->isOpenPackage($record) && auth()->user()->can('create', \App\Models\ConstructionWorkPackageTask::class))
                    ->form(fn (): array => ConstructionForms::task($this->construction()))
                    ->action(fn (ConstructionWorkPackage $record, array $data) => $this->run(fn () => app(ConstructionService::class)->createTask(auth()->user(), $record, $data), 'task_created', true)),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('reportIssue')
                        ->label(__('project.construction.actions.report_issue'))->icon('heroicon-o-exclamation-triangle')->color('danger')
                        ->visible(fn (ConstructionWorkPackage $record): bool => $this->isOpenPackage($record) && auth()->user()->can('create', \App\Models\ConstructionIssue::class))
                        ->form(fn (ConstructionWorkPackage $record): array => ConstructionForms::issue($record, $this->construction()))
                        ->action(fn (ConstructionWorkPackage $record, array $data) => $this->run(fn () => app(ConstructionService::class)->createIssue(auth()->user(), $record, $data), 'issue', true)),
                    Tables\Actions\Action::make('recordDelay')
                        ->label(__('project.construction.actions.record_delay'))->icon('heroicon-o-clock')
                        ->visible(fn (ConstructionWorkPackage $record): bool => $this->isOpenPackage($record) && auth()->user()->can('create', \App\Models\ConstructionDelay::class))
                        ->form(fn (ConstructionWorkPackage $record): array => ConstructionForms::delay($record))
                        ->action(fn (ConstructionWorkPackage $record, array $data) => $this->run(fn () => app(ConstructionService::class)->recordDelay(auth()->user(), $record, $data), 'delay', true)),
                    Tables\Actions\Action::make('editPackage')
                        ->label(__('project.construction.actions.edit'))->icon('heroicon-o-pencil-square')
                        ->visible(fn (ConstructionWorkPackage $record): bool => $this->isOpenPackage($record) && auth()->user()->can('update', $record))
                        ->fillForm(fn (ConstructionWorkPackage $record): array => $record->only(['name', 'code', 'responsible_party_id', 'budget_line_id', 'planned_start_date', 'planned_end_date', 'description', 'notes']))
                        ->form(fn (): array => ConstructionForms::package($this->construction()))
                        ->action(fn (ConstructionWorkPackage $record, array $data) => $this->run(fn () => app(ConstructionService::class)->updateWorkPackage(auth()->user(), $record, $data), 'package_updated', true)),
                    Tables\Actions\Action::make('cancelPackage')
                        ->label(__('project.construction.actions.cancel_package'))->icon('heroicon-o-x-circle')->color('danger')
                        ->visible(fn (ConstructionWorkPackage $record): bool => $this->isOpenPackage($record) && auth()->user()->can('cancel', $record))
                        ->form([Forms\Components\Textarea::make('reason')->label(__('project.construction.fields.reason'))->required()])
                        ->action(fn (ConstructionWorkPackage $record, array $data) => $this->run(fn () => app(ConstructionService::class)->cancelWorkPackage(auth()->user(), $record, $data['reason']), 'package_cancelled', true)),
                    Tables\Actions\Action::make('deletePackage')
                        ->label(__('project.construction.actions.delete'))->icon('heroicon-o-trash')->color('danger')
                        ->requiresConfirmation()->modalDescription(__('project.construction.actions.delete_description'))
                        ->visible(fn (ConstructionWorkPackage $record): bool => $record->status === 'planned' && auth()->user()->can('delete', $record))
                        ->action(fn (ConstructionWorkPackage $record) => $this->run(fn () => app(ConstructionService::class)->deleteWorkPackage(auth()->user(), $record), 'package_deleted')),
                ]),
            ]);
    }
}
