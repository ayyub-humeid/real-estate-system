<?php

namespace App\Filament\Resources\ProjectConstructionResource\RelationManagers;

use App\Filament\Support\RunsWorkflowAction;
use App\Models\ConstructionIssue;
use App\Services\ConstructionService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Problems on site. They are reported from a stage's row, and worked through here. */
class IssuesRelationManager extends RelationManager
{
    use RunsWorkflowAction;

    protected static string $relationship = 'issues';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project.construction.tab_issues');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function perform(ConstructionIssue $issue, string $to, ?string $notes = null): void
    {
        $this->runWorkflowAction(
            operation: fn () => app(ConstructionService::class)->transitionIssue(auth()->user(), $issue, $to, $notes),
            successTitle: __('project.construction.done.issue_updated'),
            onSuccess: fn () => $this->getOwnerRecord()->refresh(),
            rethrowValidation: $to === 'resolved',
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['workPackage', 'task']))
            ->defaultSort('opened_at', 'desc')
            ->emptyStateHeading(__('project.construction.issues_empty'))
            ->columns([
                Tables\Columns\TextColumn::make('title')->label(__('project.construction.fields.title'))->searchable()->weight('medium')
                    ->description(fn (ConstructionIssue $record): ?string => $record->description)->wrap(),
                Tables\Columns\TextColumn::make('workPackage.name')->label(__('project.construction.fields.package')),
                Tables\Columns\TextColumn::make('task.name')->label(__('project.construction.fields.task'))->placeholder('—'),
                Tables\Columns\TextColumn::make('severity')->label(__('project.construction.fields.severity'))->badge()
                    ->formatStateUsing(fn (?string $state): string => __('project.construction.severities.'.$state))
                    ->color(fn (?string $state): string => match ($state) { 'critical' => 'danger', 'high' => 'warning', default => 'gray' }),
                Tables\Columns\TextColumn::make('status')->label(__('project.construction.fields.status'))->badge()
                    ->formatStateUsing(fn (?string $state): string => __('project.statuses.'.$state))
                    ->color(fn (?string $state): string => match ($state) { 'open' => 'warning', 'in_progress' => 'info', 'resolved', 'closed' => 'success', default => 'gray' }),
                Tables\Columns\TextColumn::make('opened_at')->label(__('project.construction.fields.opened_at'))->date(),
            ])
            ->actions([
                Tables\Actions\Action::make('startIssue')
                    ->label(__('project.construction.actions.start_issue'))->icon('heroicon-o-play')->color('info')
                    ->visible(fn (ConstructionIssue $record): bool => $record->status === 'open' && auth()->user()->can('start', $record))
                    ->action(fn (ConstructionIssue $record) => $this->perform($record, 'in_progress')),
                Tables\Actions\Action::make('resolveIssue')
                    ->label(__('project.construction.actions.resolve_issue'))->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (ConstructionIssue $record): bool => in_array($record->status, ['open', 'in_progress'], true) && auth()->user()->can('resolve', $record))
                    ->form([Forms\Components\Textarea::make('resolution_notes')->label(__('project.construction.fields.resolution'))->required()])
                    ->action(fn (ConstructionIssue $record, array $data) => $this->perform($record, 'resolved', $data['resolution_notes'])),
                Tables\Actions\Action::make('closeIssue')
                    ->label(__('project.construction.actions.close_issue'))->icon('heroicon-o-lock-closed')
                    ->requiresConfirmation()
                    ->visible(fn (ConstructionIssue $record): bool => $record->status === 'resolved' && auth()->user()->can('close', $record))
                    ->action(fn (ConstructionIssue $record) => $this->perform($record, 'closed')),
            ]);
    }
}
