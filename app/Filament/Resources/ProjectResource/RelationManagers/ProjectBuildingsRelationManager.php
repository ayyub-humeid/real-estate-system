<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\{PlannedUnitSpecification, ProjectBuilding, ProjectBuildingFloor, ProjectPlannedUnit};
use App\Services\ProjectPlanningService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ProjectBuildingsRelationManager extends RelationManager
{
    protected static string $relationship = 'buildings';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Planning Structure');
    }

    public function isReadOnly(): bool { return false; }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('Start the planning structure'))
            ->emptyStateDescription(__('Add a building first, then create its floors, planned units, and specifications from one place.'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('code')->label(__('Code'))->badge()->color('gray')->placeholder(__('No code')),
                Tables\Columns\TextColumn::make('building_type')
                    ->label(__('Building Type'))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => $state ? __('project.building_types.'.$state) : __('Not specified')),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->colors(['gray' => 'planned', 'warning' => 'in_progress', 'success' => 'approved', 'danger' => 'cancelled'])
                    ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                Tables\Columns\TextColumn::make('floors_count')->label(__('Floors'))->counts('floors')->alignCenter(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addBuilding')->label(__('Add Building'))->icon('heroicon-o-building-office-2')
                    ->visible(fn () => auth()->user()->can('update', $this->getOwnerRecord()) && auth()->user()->can('create', ProjectBuilding::class))
                    ->form($this->buildingForm())
                    ->action(function (array $data): void {
                        try {
                            app(ProjectPlanningService::class)->createBuilding(auth()->user(), $this->getOwnerRecord(), $data);
                            Notification::make()->success()->title(__('Building added'))->body(__('You can now add floors, planned units, and specifications.'))->send();
                        } catch (ValidationException $e) { $this->error(__('Cannot add building'), $e); }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('viewStructure')->label(__('View structure'))->icon('heroicon-o-squares-2x2')
                    ->tooltip(__('View the full building structure'))
                    ->slideOver()->modalWidth('7xl')->modalSubmitAction(false)->modalCancelActionLabel(__('Close'))
                    ->infolist($this->structureInfolist()),
                Tables\Actions\Action::make('editBuilding')->label(__('Edit building'))->icon('heroicon-o-pencil-square')
                    ->visible(fn (ProjectBuilding $record) => auth()->user()->can('update', $record))
                    ->fillForm(fn (ProjectBuilding $record) => $record->only(['name', 'code', 'building_type', 'description', 'sort_order', 'status']))
                    ->form($this->buildingForm(true))
                    ->action(function (ProjectBuilding $record, array $data): void {
                        try { app(ProjectPlanningService::class)->updateBuilding(auth()->user(), $record, $data); Notification::make()->success()->title(__('Building updated'))->send(); }
                        catch (ValidationException $e) { $this->error(__('Cannot edit building'), $e); }
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('addFloor')->label(__('Add Floor'))->icon('heroicon-o-plus')
                        ->visible(fn (ProjectBuilding $record) => $this->canCreateFloor($record))
                        ->form($this->floorForm())
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->createFloor(auth()->user(), $record, $data); Notification::make()->success()->title(__('Floor added'))->send(); }
                            catch (ValidationException $e) { $this->error(__('Cannot add floor'), $e); }
                        }),
                    Tables\Actions\Action::make('editFloor')->label(__('Edit Floor'))->icon('heroicon-o-pencil-square')
                        ->visible(fn (ProjectBuilding $record) => $record->floors()->exists() && auth()->user()->can('update_project_building_floor'))
                        ->form(fn (ProjectBuilding $record) => $this->selectFloorForm($record))
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->updateFloor(auth()->user(), $this->floorForBuilding($record, $data['floor_id']), $data); Notification::make()->success()->title(__('Floor updated'))->send(); }
                            catch (ValidationException $e) { $this->error(__('Cannot edit floor'), $e); }
                        }),
                    Tables\Actions\Action::make('deleteFloor')->label(__('Delete Floor'))->icon('heroicon-o-trash')->color('danger')
                        ->visible(fn (ProjectBuilding $record) => $record->floors()->exists() && auth()->user()->can('delete_project_building_floor'))
                        ->form(fn (ProjectBuilding $record) => [Forms\Components\Select::make('floor_id')->label(__('Floor'))->options($this->floorOptions($record))->required()])
                        ->requiresConfirmation()->modalDescription(__('A floor can be deleted only when it has no planned units.'))
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->deleteFloor(auth()->user(), $this->floorForBuilding($record, $data['floor_id'])); Notification::make()->success()->title(__('Floor deleted'))->send(); }
                            catch (ValidationException $e) { $this->error(__('Cannot delete floor'), $e); }
                        }),
                ])->label(__('Floors'))->icon('heroicon-o-building-storefront')->tooltip(__('Floors')),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('addPlannedUnit')->label(__('Add Planned Unit'))->icon('heroicon-o-plus')
                        ->visible(fn (ProjectBuilding $record) => $record->floors()->exists() && $this->canCreatePlannedUnit($record))
                        ->form(fn (ProjectBuilding $record) => array_merge([Forms\Components\Select::make('floor_id')->label(__('Floor'))->options($this->floorOptions($record))->required()], $this->plannedUnitForm()))
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->createPlannedUnit(auth()->user(), $this->floorForBuilding($record, $data['floor_id']), collect($data)->except('floor_id')->all()); Notification::make()->success()->title(__('Planned unit added'))->send(); }
                            catch (ValidationException $e) { $this->error(__('Cannot add planned unit'), $e); }
                        }),
                    Tables\Actions\Action::make('editPlannedUnit')->label(__('Edit Planned Unit'))->icon('heroicon-o-pencil-square')
                        ->visible(fn (ProjectBuilding $record) => $this->hasPlannedUnits($record) && auth()->user()->can('update_project_planned_unit'))
                        ->form(fn (ProjectBuilding $record) => $this->selectPlannedUnitForm($record))
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->updatePlannedUnit(auth()->user(), $this->unitForBuilding($record, $data['planned_unit_id']), $data); Notification::make()->success()->title(__('Planned unit updated'))->send(); }
                            catch (ValidationException $e) { $this->error(__('Cannot edit planned unit'), $e); }
                        }),
                    Tables\Actions\Action::make('approvePlannedUnit')->label(__('Approve Planned Unit'))->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn (ProjectBuilding $record) => $this->hasPlannedUnits($record) && auth()->user()->can('approve_planned_unit'))
                        ->form(fn (ProjectBuilding $record) => [Forms\Components\Select::make('planned_unit_id')->label(__('Planned unit'))->options($this->plannedUnitOptions($record, 'planned'))->required()])
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->transitionPlannedUnit(auth()->user(), $this->unitForBuilding($record, $data['planned_unit_id']), 'approved'); Notification::make()->success()->title(__('Planned unit approved'))->send(); }
                            catch (ValidationException $e) { $this->error(__('Cannot approve planned unit'), $e); }
                        }),
                    Tables\Actions\Action::make('cancelPlannedUnit')->label(__('Cancel Planned Unit'))->icon('heroicon-o-x-circle')->color('danger')
                        ->visible(fn (ProjectBuilding $record) => $this->hasPlannedUnits($record) && auth()->user()->can('cancel_planned_unit'))
                        ->form(fn (ProjectBuilding $record) => [Forms\Components\Select::make('planned_unit_id')->label(__('Planned unit'))->options($this->plannedUnitOptions($record, ['planned', 'approved']))->required()])
                        ->requiresConfirmation()
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->transitionPlannedUnit(auth()->user(), $this->unitForBuilding($record, $data['planned_unit_id']), 'cancelled'); Notification::make()->success()->title(__('Planned unit cancelled'))->send(); }
                            catch (ValidationException $e) { $this->error(__('Cannot cancel planned unit'), $e); }
                        }),
                    Tables\Actions\Action::make('deletePlannedUnit')->label(__('Delete Planned Unit'))->icon('heroicon-o-trash')->color('danger')
                        ->visible(fn (ProjectBuilding $record) => $this->hasPlannedUnits($record) && auth()->user()->can('delete_project_planned_unit'))
                        ->form(fn (ProjectBuilding $record) => [Forms\Components\Select::make('planned_unit_id')->label(__('Planned unit'))->options($this->plannedUnitOptions($record, 'planned'))->required()])
                        ->requiresConfirmation()->modalDescription(__('A planned unit can be deleted only while it is planned and has no specifications.'))
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->deletePlannedUnit(auth()->user(), $this->unitForBuilding($record, $data['planned_unit_id'])); Notification::make()->success()->title(__('Planned unit deleted'))->send(); }
                            catch (ValidationException $e) { $this->error(__('Cannot delete planned unit'), $e); }
                        }),
                ])->label(__('Planned units'))->icon('heroicon-o-home-modern')->tooltip(__('Planned units')),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('addSpecification')->label(__('Add Specification'))->icon('heroicon-o-plus')
                        ->visible(fn (ProjectBuilding $record) => $this->hasPlannedUnits($record) && $this->canCreateSpecification($record))
                        ->form(fn (ProjectBuilding $record) => array_merge([Forms\Components\Select::make('planned_unit_id')->label(__('Planned unit'))->options($this->plannedUnitOptions($record, 'planned'))->required()], $this->specificationForm()))
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->createSpecification(auth()->user(), $this->unitForBuilding($record, $data['planned_unit_id']), collect($data)->except('planned_unit_id')->all()); Notification::make()->success()->title(__('Specification added'))->send(); }
                            catch (ValidationException $e) { $this->error(__('Cannot add specification'), $e); }
                        }),
                    Tables\Actions\Action::make('editSpecification')->label(__('Edit Specification'))->icon('heroicon-o-pencil-square')
                        ->visible(fn (ProjectBuilding $record) => $this->hasSpecifications($record) && auth()->user()->can('update_planned_unit_specification'))
                        ->form(fn (ProjectBuilding $record) => $this->selectSpecificationForm($record))
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->updateSpecification(auth()->user(), $this->specificationForBuilding($record, $data['specification_id']), $data); Notification::make()->success()->title(__('Specification updated'))->send(); }
                            catch (ValidationException $e) { $this->error(__('Cannot edit specification'), $e); }
                        }),
                    Tables\Actions\Action::make('deleteSpecification')->label(__('Delete Specification'))->icon('heroicon-o-trash')->color('danger')
                        ->visible(fn (ProjectBuilding $record) => $this->hasSpecifications($record) && auth()->user()->can('delete_planned_unit_specification'))
                        ->form(fn (ProjectBuilding $record) => [Forms\Components\Select::make('specification_id')->label(__('Specification'))->options($this->specificationOptions($record))->required()])
                        ->requiresConfirmation()
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->deleteSpecification(auth()->user(), $this->specificationForBuilding($record, $data['specification_id'])); Notification::make()->success()->title(__('Specification deleted'))->send(); }
                            catch (ValidationException $e) { $this->error(__('Cannot delete specification'), $e); }
                        }),
                ])->label(__('Specifications'))->icon('heroicon-o-list-bullet')->tooltip(__('Specifications')),
                Tables\Actions\Action::make('deleteBuilding')->label(__('Delete building'))->icon('heroicon-o-trash')->color('danger')
                    ->visible(fn (ProjectBuilding $record) => auth()->user()->can('delete', $record))
                    ->requiresConfirmation()->modalDescription(__('A building can be deleted only when it has no floors. This avoids accidentally losing planning records.'))
                    ->action(function (ProjectBuilding $record): void {
                        try { app(ProjectPlanningService::class)->deleteBuilding(auth()->user(), $record); Notification::make()->success()->title(__('Building deleted'))->send(); }
                        catch (ValidationException $e) { $this->error(__('Cannot delete building'), $e); }
                    }),
            ]);
    }

    private function buildingForm(bool $includeStatus = false): array
    {
        $fields = [
            Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
            Forms\Components\TextInput::make('code')->label(__('Code'))->maxLength(255),
            Forms\Components\Select::make('building_type')->label(__('Building Type'))->options(collect(ProjectBuilding::TYPES)->mapWithKeys(fn ($label, $key) => [$key => __('project.building_types.'.$key)]))->searchable()->placeholder(__('Select a building type')),
            Forms\Components\TextInput::make('sort_order')->label(__('Order'))->numeric()->default(0),
            Forms\Components\Textarea::make('description')->label(__('Description'))->columnSpanFull(),
        ];
        if ($includeStatus) $fields[] = Forms\Components\Select::make('status')->label(__('Status'))->options(collect(['planned', 'in_progress', 'approved', 'cancelled'])->mapWithKeys(fn (string $status) => [$status => __('project.statuses.'.$status)]))->required();
        return $fields;
    }

    private function floorForm(): array
    {
        return [
            Forms\Components\TextInput::make('floor_number')->label(__('Floor Number'))->numeric()->required(),
            Forms\Components\TextInput::make('label')->label(__('Label'))->placeholder(__('e.g. Ground floor')),
            Forms\Components\TextInput::make('sort_order')->label(__('Order'))->numeric()->default(0),
            Forms\Components\Textarea::make('description')->label(__('Description'))->columnSpanFull()
        ];
    }

    private function plannedUnitForm(): array
    {
        return [
            Forms\Components\TextInput::make('code')->label(__('Code'))->required(),
            Forms\Components\Select::make('unit_type')->label(__('Unit type'))->options(\App\Enums\UnitType::options())->searchable()->required()->placeholder(__('Select a unit type')),
            Forms\Components\TextInput::make('planned_area')->label(__('Planned Area'))->numeric()->suffix('m²'),
            Forms\Components\TextInput::make('sort_order')->label(__('Order'))->numeric()->default(0),
            Forms\Components\Textarea::make('description')->label(__('Description'))->columnSpanFull()
        ];
    }

    private function specificationForm(): array
    {
        return [
            Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
            Forms\Components\TextInput::make('value')->label(__('Value'))->required(),
            Forms\Components\TextInput::make('unit')->label(__('Unit'))->placeholder(__('e.g. m²')),
            Forms\Components\TextInput::make('sort_order')->label(__('Order'))->numeric()->default(0),
            Forms\Components\Textarea::make('notes')->label(__('Notes'))->columnSpanFull()
        ];
    }

    private function selectFloorForm(ProjectBuilding $building): array
    {
        return array_merge([$this->floorSelect($building)], $this->floorForm());
    }

    private function selectPlannedUnitForm(ProjectBuilding $building): array
    {
        return array_merge([$this->plannedUnitSelect($building)], $this->plannedUnitForm());
    }

    private function selectSpecificationForm(ProjectBuilding $building): array
    {
        return array_merge([$this->specificationSelect($building)], $this->specificationForm());
    }

    private function floorSelect(ProjectBuilding $building): Forms\Components\Select
    {
        return Forms\Components\Select::make('floor_id')->label(__('Floor'))->options($this->floorOptions($building))->live()->required()
            ->afterStateUpdated(function ($state, Forms\Set $set) use ($building): void {
                $floor = $state ? $this->floorForBuilding($building, $state) : null;
                if (! $floor) return;
                $set('floor_number', $floor->floor_number); $set('label', $floor->label); $set('sort_order', $floor->sort_order); $set('description', $floor->description);
            });
    }

    private function plannedUnitSelect(ProjectBuilding $building): Forms\Components\Select
    {
        return Forms\Components\Select::make('planned_unit_id')->label(__('Planned unit'))->options($this->plannedUnitOptions($building))->live()->required()
            ->afterStateUpdated(function ($state, Forms\Set $set) use ($building): void {
                $unit = $state ? $this->unitForBuilding($building, $state) : null;
                if (! $unit) return;
                $set('code', $unit->code); $set('unit_type', $unit->unit_type); $set('planned_area', $unit->planned_area); $set('sort_order', $unit->sort_order); $set('description', $unit->description);
            });
    }

    private function specificationSelect(ProjectBuilding $building): Forms\Components\Select
    {
        return Forms\Components\Select::make('specification_id')->label(__('Specification'))->options($this->specificationOptions($building))->live()->required()
            ->afterStateUpdated(function ($state, Forms\Set $set) use ($building): void {
                $specification = $state ? $this->specificationForBuilding($building, $state) : null;
                if (! $specification) return;
                $set('name', $specification->name); $set('value', $specification->value); $set('unit', $specification->unit); $set('sort_order', $specification->sort_order); $set('notes', $specification->notes);
            });
    }

    private function structureInfolist(): array
    {
        return [
            Infolists\Components\TextEntry::make('name')->label(__('Building')),
            Infolists\Components\TextEntry::make('code')->label(__('Code'))->badge()->placeholder(__('No code')),
            Infolists\Components\TextEntry::make('building_type')->label(__('Building Type'))->placeholder(__('Not specified'))->formatStateUsing(fn (?string $state): string => $state ? __('project.building_types.'.$state) : __('Not specified')),
            Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
            Infolists\Components\TextEntry::make('description')->label(__('Description'))->columnSpanFull()->placeholder(__('No description')),
            Infolists\Components\RepeatableEntry::make('floors')->label(__('Floors, planned units, and specifications'))->schema([
                Infolists\Components\TextEntry::make('floor_number')->label(__('Floor'))->badge(),
                Infolists\Components\TextEntry::make('label')->label(__('Label'))->placeholder(__('No label')),
                Infolists\Components\TextEntry::make('description')->label(__('Description'))->columnSpanFull()->placeholder(__('No floor description')),
                Infolists\Components\RepeatableEntry::make('plannedUnits')->label(__('Planned units'))->schema([
                    Infolists\Components\TextEntry::make('code')->label(__('Unit'))->badge(),
                    Infolists\Components\TextEntry::make('unit_type')->label(__('Unit type'))->formatStateUsing(fn ($state) => $state ? (\App\Enums\UnitType::tryFrom($state)?->getLabel() ?? __($state)) : __('—')),
                    Infolists\Components\TextEntry::make('planned_area')->label(__('Planned Area'))->suffix(' m²')->placeholder(__('Area not set')),
                    Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                    Infolists\Components\TextEntry::make('description')->label(__('Description'))->columnSpanFull()->placeholder(__('No unit description')),
                    Infolists\Components\RepeatableEntry::make('specifications')->label(__('Specifications'))->schema([
                        Infolists\Components\TextEntry::make('name')->label(__('Name'))->weight('medium'),
                        Infolists\Components\TextEntry::make('value')->label(__('Value')),
                        Infolists\Components\TextEntry::make('unit')->label(__('Unit'))->placeholder(__('No unit')),
                        Infolists\Components\TextEntry::make('notes')->label(__('Notes'))->columnSpanFull()->placeholder(__('No notes')),
                    ])->columns(2),
                ])->columns(4),
            ])->columns(2),
        ];
    }

    private function floorOptions(ProjectBuilding $building): array
    {
        return $building->floors()->orderBy('floor_number')->get()->mapWithKeys(fn (ProjectBuildingFloor $floor) => [$floor->id => __('Floor') . " {$floor->floor_number}" . ($floor->label ? " — {$floor->label}" : '')])->all();
    }

    private function plannedUnitOptions(ProjectBuilding $building, string|array|null $statuses = null): array
    {
        $query = ProjectPlannedUnit::withoutGlobalScopes()->whereIn('project_building_floor_id', $building->floors()->select('id'));
        if ($statuses) $query->whereIn('status', (array) $statuses);
        return $query->with('floor')->orderBy('code')->get()->mapWithKeys(fn (ProjectPlannedUnit $unit) => [$unit->id => "{$unit->code} — " . __('Floor') . " {$unit->floor->floor_number} (" . __('project.statuses.'.$unit->status) . ")"])->all();
    }

    private function specificationOptions(ProjectBuilding $building): array
    {
        return PlannedUnitSpecification::withoutGlobalScopes()->whereIn('project_planned_unit_id', ProjectPlannedUnit::withoutGlobalScopes()->whereIn('project_building_floor_id', $building->floors()->select('id'))->select('id'))->with('plannedUnit')->orderBy('name')->get()->mapWithKeys(fn (PlannedUnitSpecification $spec) => [$spec->id => "{$spec->plannedUnit->code} — {$spec->name}"])->all();
    }

    private function floorForBuilding(ProjectBuilding $building, int|string $id): ProjectBuildingFloor
    {
        $floor = ProjectBuildingFloor::withoutGlobalScopes()->find($id);
        if (! $floor || (int) $floor->project_building_id !== (int) $building->id) throw ValidationException::withMessages(['floor_id' => 'The selected floor does not belong to this building.']);
        return $floor;
    }

    private function unitForBuilding(ProjectBuilding $building, int|string $id): ProjectPlannedUnit
    {
        $unit = ProjectPlannedUnit::withoutGlobalScopes()->with('floor')->find($id);
        if (! $unit || (int) $unit->floor->project_building_id !== (int) $building->id) throw ValidationException::withMessages(['planned_unit_id' => 'The selected planned unit does not belong to this building.']);
        return $unit;
    }

    private function specificationForBuilding(ProjectBuilding $building, int|string $id): PlannedUnitSpecification
    {
        $specification = PlannedUnitSpecification::withoutGlobalScopes()->with('plannedUnit.floor')->find($id);
        if (! $specification || (int) $specification->plannedUnit->floor->project_building_id !== (int) $building->id) throw ValidationException::withMessages(['specification_id' => 'The selected specification does not belong to this building.']);
        return $specification;
    }

    private function canCreateFloor(ProjectBuilding $building): bool { return auth()->user()->can('update', $building->project) && auth()->user()->can('create', ProjectBuildingFloor::class); }
    private function canCreatePlannedUnit(ProjectBuilding $building): bool { return auth()->user()->can('update', $building->project) && auth()->user()->can('create', ProjectPlannedUnit::class); }
    private function canCreateSpecification(ProjectBuilding $building): bool { return auth()->user()->can('update', $building->project) && auth()->user()->can('create', PlannedUnitSpecification::class); }
    private function hasPlannedUnits(ProjectBuilding $building): bool { return ProjectPlannedUnit::withoutGlobalScopes()->whereIn('project_building_floor_id', $building->floors()->select('id'))->exists(); }
    private function hasSpecifications(ProjectBuilding $building): bool { return PlannedUnitSpecification::withoutGlobalScopes()->whereIn('project_planned_unit_id', ProjectPlannedUnit::withoutGlobalScopes()->whereIn('project_building_floor_id', $building->floors()->select('id'))->select('id'))->exists(); }
    private function error(string $title, ValidationException $exception): void { Notification::make()->danger()->title($title)->body(collect($exception->errors())->flatten()->first())->send(); }
}
