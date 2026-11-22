<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Filament\Resources\ProjectConstructionResource;
use App\Filament\Support\{ConstructionForms, RunsWorkflowAction};
use App\Models\ProjectConstruction;
use App\Services\ConstructionService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** A short list of the project's construction runs. All the work happens on the construction page. */
class ProjectConstructionRelationManager extends RelationManager
{
    use RunsWorkflowAction;

    protected static string $relationship = 'constructions';

    public static function getModelLabel(): string
    {
        return __('project.construction.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('project.construction.title');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project.construction.title');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('managerParty:id,name')->withCount('workPackages'))
            ->defaultSort('execution_number', 'desc')
            ->emptyStateHeading(__('project.construction.empty_heading'))
            ->emptyStateDescription(__('project.construction.empty_description'))
            ->columns([
                Tables\Columns\TextColumn::make('execution_number')->label(__('project.construction.fields.execution'))->prefix('#'),
                Tables\Columns\TextColumn::make('status')->label(__('project.construction.fields.status'))->badge()
                    ->formatStateUsing(fn (?string $state): string => __('project.statuses.'.$state))
                    ->color(fn (?string $state): string => match ($state) { 'in_progress' => 'info', 'completed' => 'success', default => 'gray' }),
                Tables\Columns\TextColumn::make('progress_percentage')->label(__('project.construction.fields.progress'))
                    ->formatStateUsing(fn ($state): string => (int) $state.'%')->badge()->color('info'),
                Tables\Columns\TextColumn::make('managerParty.name')->label(__('project.construction.fields.manager'))->placeholder('—'),
                Tables\Columns\TextColumn::make('work_packages_count')->label(__('project.construction.fields.packages')),
                Tables\Columns\TextColumn::make('expected_completion_date')->label(__('project.construction.fields.expected'))->date()->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createConstruction')
                    ->label(__('project.construction.actions.create'))->icon('heroicon-o-plus')
                    ->visible(fn (): bool => ! $this->getOwnerRecord()->activeConstruction()->exists() && auth()->user()->can('create', ProjectConstruction::class))
                    ->form(fn (): array => ConstructionForms::construction(new ProjectConstruction(['company_id' => $this->getOwnerRecord()->company_id, 'project_id' => $this->getOwnerRecord()->id])))
                    ->action(fn (array $data) => $this->runWorkflowAction(
                        operation: fn () => app(ConstructionService::class)->createConstruction(auth()->user(), $this->getOwnerRecord(), $data),
                        successTitle: __('project.construction.done.created'),
                        rethrowValidation: true,
                    )),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label(__('project.construction.actions.open'))->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (ProjectConstruction $record): bool => auth()->user()->can('view', $record))
                    ->url(fn (ProjectConstruction $record): string => ProjectConstructionResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make()->label(__('project.construction.actions.delete'))
                    ->modalDescription(__('project.construction.actions.delete_description'))
                    ->visible(fn (ProjectConstruction $record): bool => auth()->user()->can('delete', $record)),
            ]);
    }
}
