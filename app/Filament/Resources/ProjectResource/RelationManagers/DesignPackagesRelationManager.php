<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Enums\DesignPackageStatus;
use App\Filament\Resources\ProjectDesignPackageResource;
use App\Filament\Support\RunsWorkflowAction;
use App\Models\ProjectDesignPackage;
use App\Services\DesignPackageService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Design work of a project: one row per package, opened to follow its drawing versions. */
class DesignPackagesRelationManager extends RelationManager
{
    use RunsWorkflowAction;

    protected static string $relationship = 'designPackages';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project.design.title');
    }

    public static function getModelLabel(): string
    {
        return __('project.design.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('project.design.title');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['engineeringOffice:id,name', 'latestVersion']))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('project.design.empty_heading'))
            ->emptyStateDescription(__('project.design.empty_description'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('project.design.fields.name'))->searchable()->weight('medium')
                    ->description(fn (ProjectDesignPackage $record): string => ProjectDesignPackage::disciplineLabel($record->discipline)),
                Tables\Columns\TextColumn::make('engineeringOffice.name')->label(__('project.design.fields.office'))->placeholder(__('project.design.no_office')),
                Tables\Columns\TextColumn::make('status')->label(__('project.design.fields.status'))->badge()
                    ->formatStateUsing(fn (DesignPackageStatus $state): string => $state->getLabel())
                    ->color(fn (DesignPackageStatus $state): string => $state->color())
                    ->tooltip(fn (DesignPackageStatus $state): string => $state->description()),
                Tables\Columns\TextColumn::make('latestVersion.version_number')->label(__('project.design.fields.latest_version'))
                    ->formatStateUsing(fn ($state): string => 'V'.$state)->placeholder('—'),
                Tables\Columns\TextColumn::make('target_date')->label(__('project.design.fields.target_date'))->date()->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createPackage')
                    ->label(__('project.design.actions.create'))
                    ->icon('heroicon-o-plus')
                    ->modalHeading(__('project.design.actions.create'))
                    ->modalDescription(__('project.design.actions.create_description'))
                    ->visible(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord()) && auth()->user()->can('create', ProjectDesignPackage::class))
                    ->form(fn (): array => ProjectDesignPackageResource::packageFields((int) $this->getOwnerRecord()->company_id))
                    ->action(fn (array $data) => $this->runWorkflowAction(
                        operation: fn () => app(DesignPackageService::class)->createPackage(auth()->user(), $this->getOwnerRecord(), $data),
                        successTitle: __('project.design.done.created'),
                        rethrowValidation: true,
                    )),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label(__('project.design.actions.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (ProjectDesignPackage $record): bool => auth()->user()->can('view', $record))
                    ->url(fn (ProjectDesignPackage $record): string => ProjectDesignPackageResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('editPackage')
                    ->label(__('project.design.actions.edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (ProjectDesignPackage $record): bool => auth()->user()->can('update', $record))
                    ->fillForm(fn (ProjectDesignPackage $record): array => $record->only(['name', 'discipline', 'engineering_office_id', 'target_date', 'notes']))
                    ->form(fn (): array => ProjectDesignPackageResource::packageFields((int) $this->getOwnerRecord()->company_id))
                    ->action(fn (ProjectDesignPackage $record, array $data) => $this->runWorkflowAction(
                        operation: fn () => app(DesignPackageService::class)->updatePackage(auth()->user(), $record, $data),
                        successTitle: __('project.design.done.updated'),
                        rethrowValidation: true,
                    )),
                Tables\Actions\DeleteAction::make()
                    ->label(__('project.design.actions.delete'))
                    ->modalDescription(__('project.design.actions.delete_description')),
            ]);
    }
}
