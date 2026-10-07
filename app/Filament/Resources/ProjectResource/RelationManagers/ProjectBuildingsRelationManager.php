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
use Illuminate\Validation\ValidationException;

class ProjectBuildingsRelationManager extends RelationManager
{
    protected static string $relationship = 'buildings';
    protected static ?string $title = 'Planning Structure';

    public function isReadOnly(): bool { return false; }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->emptyStateHeading('Start the planning structure')
            ->emptyStateDescription('Add a building first, then create its floors, planned units, and specifications from one place.')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('code')->badge()->color('gray')->placeholder('No code'),
                Tables\Columns\TextColumn::make('building_type')->label('Type')->badge()->color('info')->placeholder('Not specified'),
                Tables\Columns\TextColumn::make('status')->badge()->colors(['gray' => 'planned', 'success' => 'approved', 'danger' => 'cancelled']),
                Tables\Columns\TextColumn::make('floors_count')->label('Floors')->counts('floors')->alignCenter(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addBuilding')->label('Add Building')->icon('heroicon-o-building-office-2')
                    ->visible(fn () => auth()->user()->can('update', $this->getOwnerRecord()) && auth()->user()->can('create', ProjectBuilding::class))
                    ->form($this->buildingForm())
                    ->action(function (array $data): void {
                        try {
                            app(ProjectPlanningService::class)->createBuilding(auth()->user(), $this->getOwnerRecord(), $data);
                            Notification::make()->success()->title('Building added')->body('You can now add floors, planned units, and specifications.')->send();
                        } catch (ValidationException $e) { $this->error('Cannot add building', $e); }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('viewStructure')->label('View structure')->icon('heroicon-o-squares-2x2')
                    ->tooltip('View the full building structure')
                    ->slideOver()->modalWidth('7xl')->modalSubmitAction(false)->modalCancelActionLabel('Close')
                    ->infolist($this->structureInfolist()),
                Tables\Actions\Action::make('editBuilding')->label('Edit building')->icon('heroicon-o-pencil-square')
                    ->visible(fn (ProjectBuilding $record) => auth()->user()->can('update', $record))
                    ->fillForm(fn (ProjectBuilding $record) => $record->only(['name', 'code', 'building_type', 'description', 'sort_order', 'status']))
                    ->form($this->buildingForm(true))
                    ->action(function (ProjectBuilding $record, array $data): void {
                        try { app(ProjectPlanningService::class)->updateBuilding(auth()->user(), $record, $data); Notification::make()->success()->title('Building updated')->send(); }
                        catch (ValidationException $e) { $this->error('Cannot edit building', $e); }
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('addFloor')->label('Add Floor')->icon('heroicon-o-plus')
                        ->visible(fn (ProjectBuilding $record) => $this->canCreateFloor($record))
                        ->form($this->floorForm())
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->createFloor(auth()->user(), $record, $data); Notification::make()->success()->title('Floor added')->send(); }
                            catch (ValidationException $e) { $this->error('Cannot add floor', $e); }
                        }),
                    Tables\Actions\Action::make('editFloor')->label('Edit Floor')->icon('heroicon-o-pencil-square')
                        ->visible(fn (ProjectBuilding $record) => $record->floors()->exists() && auth()->user()->can('update_project_building_floor'))
                        ->form(fn (ProjectBuilding $record) => $this->selectFloorForm($record))
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->updateFloor(auth()->user(), $this->floorForBuilding($record, $data['floor_id']), $data); Notification::make()->success()->title('Floor updated')->send(); }
                            catch (ValidationException $e) { $this->error('Cannot edit floor', $e); }
                        }),
                    Tables\Actions\Action::make('deleteFloor')->label('Delete Floor')->icon('heroicon-o-trash')->color('danger')
                        ->visible(fn (ProjectBuilding $record) => $record->floors()->exists() && auth()->user()->can('delete_project_building_floor'))
                        ->form(fn (ProjectBuilding $record) => [Forms\Components\Select::make('floor_id')->label('Floor')->options($this->floorOptions($record))->required()])
                        ->requiresConfirmation()->modalDescription('A floor can be deleted only when it has no planned units.')
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->deleteFloor(auth()->user(), $this->floorForBuilding($record, $data['floor_id'])); Notification::make()->success()->title('Floor deleted')->send(); }
                            catch (ValidationException $e) { $this->error('Cannot delete floor', $e); }
                        }),
                ])->label('Floors')->icon('heroicon-o-building-storefront')->tooltip('Floors'),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('addPlannedUnit')->label('Add Planned Unit')->icon('heroicon-o-plus')
                        ->visible(fn (ProjectBuilding $record) => $record->floors()->exists() && $this->canCreatePlannedUnit($record))
                        ->form(fn (ProjectBuilding $record) => array_merge([Forms\Components\Select::make('floor_id')->label('Floor')->options($this->floorOptions($record))->required()], $this->plannedUnitForm()))
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->createPlannedUnit(auth()->user(), $this->floorForBuilding($record, $data['floor_id']), collect($data)->except('floor_id')->all()); Notification::make()->success()->title('Planned unit added')->send(); }
                            catch (ValidationException $e) { $this->error('Cannot add planned unit', $e); }
                        }),
                    Tables\Actions\Action::make('editPlannedUnit')->label('Edit Planned Unit')->icon('heroicon-o-pencil-square')
                        ->visible(fn (ProjectBuilding $record) => $this->hasPlannedUnits($record) && auth()->user()->can('update_project_planned_unit'))
                        ->form(fn (ProjectBuilding $record) => $this->selectPlannedUnitForm($record))
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->updatePlannedUnit(auth()->user(), $this->unitForBuilding($record, $data['planned_unit_id']), $data); Notification::make()->success()->title('Planned unit updated')->send(); }
                            catch (ValidationException $e) { $this->error('Cannot edit planned unit', $e); }
                        }),
                    Tables\Actions\Action::make('approvePlannedUnit')->label('Approve Planned Unit')->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn (ProjectBuilding $record) => $this->hasPlannedUnits($record) && auth()->user()->can('approve_planned_unit'))
                        ->form(fn (ProjectBuilding $record) => [Forms\Components\Select::make('planned_unit_id')->label('Planned unit')->options($this->plannedUnitOptions($record, 'planned'))->required()])
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->transitionPlannedUnit(auth()->user(), $this->unitForBuilding($record, $data['planned_unit_id']), 'approved'); Notification::make()->success()->title('Planned unit approved')->send(); }
                            catch (ValidationException $e) { $this->error('Cannot approve planned unit', $e); }
                        }),
                    Tables\Actions\Action::make('cancelPlannedUnit')->label('Cancel Planned Unit')->icon('heroicon-o-x-circle')->color('danger')
                        ->visible(fn (ProjectBuilding $record) => $this->hasPlannedUnits($record) && auth()->user()->can('cancel_planned_unit'))
                        ->form(fn (ProjectBuilding $record) => [Forms\Components\Select::make('planned_unit_id')->label('Planned unit')->options($this->plannedUnitOptions($record, ['planned', 'approved']))->required()])
                        ->requiresConfirmation()
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->transitionPlannedUnit(auth()->user(), $this->unitForBuilding($record, $data['planned_unit_id']), 'cancelled'); Notification::make()->success()->title('Planned unit cancelled')->send(); }
                            catch (ValidationException $e) { $this->error('Cannot cancel planned unit', $e); }
                        }),
                    Tables\Actions\Action::make('deletePlannedUnit')->label('Delete Planned Unit')->icon('heroicon-o-trash')->color('danger')
                        ->visible(fn (ProjectBuilding $record) => $this->hasPlannedUnits($record) && auth()->user()->can('delete_project_planned_unit'))
                        ->form(fn (ProjectBuilding $record) => [Forms\Components\Select::make('planned_unit_id')->label('Planned unit')->options($this->plannedUnitOptions($record, 'planned'))->required()])
                        ->requiresConfirmation()->modalDescription('A planned unit can be deleted only while it is planned and has no specifications.')
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->deletePlannedUnit(auth()->user(), $this->unitForBuilding($record, $data['planned_unit_id'])); Notification::make()->success()->title('Planned unit deleted')->send(); }
                            catch (ValidationException $e) { $this->error('Cannot delete planned unit', $e); }
                        }),
                ])->label('Planned units')->icon('heroicon-o-home-modern')->tooltip('Planned units'),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('addSpecification')->label('Add Specification')->icon('heroicon-o-plus')
                        ->visible(fn (ProjectBuilding $record) => $this->hasPlannedUnits($record) && $this->canCreateSpecification($record))
                        ->form(fn (ProjectBuilding $record) => array_merge([Forms\Components\Select::make('planned_unit_id')->label('Planned unit')->options($this->plannedUnitOptions($record, 'planned'))->required()], $this->specificationForm()))
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->createSpecification(auth()->user(), $this->unitForBuilding($record, $data['planned_unit_id']), collect($data)->except('planned_unit_id')->all()); Notification::make()->success()->title('Specification added')->send(); }
                            catch (ValidationException $e) { $this->error('Cannot add specification', $e); }
                        }),
                    Tables\Actions\Action::make('editSpecification')->label('Edit Specification')->icon('heroicon-o-pencil-square')
                        ->visible(fn (ProjectBuilding $record) => $this->hasSpecifications($record) && auth()->user()->can('update_planned_unit_specification'))
                        ->form(fn (ProjectBuilding $record) => $this->selectSpecificationForm($record))
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->updateSpecification(auth()->user(), $this->specificationForBuilding($record, $data['specification_id']), $data); Notification::make()->success()->title('Specification updated')->send(); }
                            catch (ValidationException $e) { $this->error('Cannot edit specification', $e); }
                        }),
                    Tables\Actions\Action::make('deleteSpecification')->label('Delete Specification')->icon('heroicon-o-trash')->color('danger')
                        ->visible(fn (ProjectBuilding $record) => $this->hasSpecifications($record) && auth()->user()->can('delete_planned_unit_specification'))
                        ->form(fn (ProjectBuilding $record) => [Forms\Components\Select::make('specification_id')->label('Specification')->options($this->specificationOptions($record))->required()])
                        ->requiresConfirmation()
                        ->action(function (ProjectBuilding $record, array $data): void {
                            try { app(ProjectPlanningService::class)->deleteSpecification(auth()->user(), $this->specificationForBuilding($record, $data['specification_id'])); Notification::make()->success()->title('Specification deleted')->send(); }
                            catch (ValidationException $e) { $this->error('Cannot delete specification', $e); }
                        }),
                ])->label('Specifications')->icon('heroicon-o-list-bullet')->tooltip('Specifications'),
                Tables\Actions\Action::make('deleteBuilding')->label('Delete building')->icon('heroicon-o-trash')->color('danger')
                    ->visible(fn (ProjectBuilding $record) => auth()->user()->can('delete', $record))
                    ->requiresConfirmation()->modalDescription('A building can be deleted only when it has no floors. This avoids accidentally losing planning records.')
                    ->action(function (ProjectBuilding $record): void {
                        try { app(ProjectPlanningService::class)->deleteBuilding(auth()->user(), $record); Notification::make()->success()->title('Building deleted')->send(); }
                        catch (ValidationException $e) { $this->error('Cannot delete building', $e); }
                    }),
            ]);
    }

    private function buildingForm(bool $includeStatus = false): array
    {
        $fields = [
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('code')->maxLength(255),
            Forms\Components\Select::make('building_type')->label('Building type')->options(ProjectBuilding::TYPES)->searchable()->placeholder('Select a building type'),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
        ];
        if ($includeStatus) $fields[] = Forms\Components\Select::make('status')->options(['planned' => 'Planned', 'approved' => 'Approved', 'cancelled' => 'Cancelled'])->required();
        return $fields;
    }

    private function floorForm(): array
    {
        return [Forms\Components\TextInput::make('floor_number')->numeric()->required(), Forms\Components\TextInput::make('label')->placeholder('e.g. Ground floor'), Forms\Components\TextInput::make('sort_order')->numeric()->default(0), Forms\Components\Textarea::make('description')->columnSpanFull()];
    }

    private function plannedUnitForm(): array
    {
        return [Forms\Components\TextInput::make('code')->required(), Forms\Components\Select::make('unit_type')->label('Unit type')->options(\App\Enums\UnitType::options())->searchable()->required()->placeholder('Select a unit type'), Forms\Components\TextInput::make('planned_area')->numeric()->suffix('m²'), Forms\Components\TextInput::make('sort_order')->numeric()->default(0), Forms\Components\Textarea::make('description')->columnSpanFull()];
    }

    private function specificationForm(): array
    {
        return [Forms\Components\TextInput::make('name')->required(), Forms\Components\TextInput::make('value')->required(), Forms\Components\TextInput::make('unit')->placeholder('e.g. m²'), Forms\Components\TextInput::make('sort_order')->numeric()->default(0), Forms\Components\Textarea::make('notes')->columnSpanFull()];
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
        return Forms\Components\Select::make('floor_id')->label('Floor')->options($this->floorOptions($building))->live()->required()
            ->afterStateUpdated(function ($state, Forms\Set $set) use ($building): void {
                $floor = $state ? $this->floorForBuilding($building, $state) : null;
                if (! $floor) return;
                $set('floor_number', $floor->floor_number); $set('label', $floor->label); $set('sort_order', $floor->sort_order); $set('description', $floor->description);
            });
    }

    private function plannedUnitSelect(ProjectBuilding $building): Forms\Components\Select
    {
        return Forms\Components\Select::make('planned_unit_id')->label('Planned unit')->options($this->plannedUnitOptions($building))->live()->required()
            ->afterStateUpdated(function ($state, Forms\Set $set) use ($building): void {
                $unit = $state ? $this->unitForBuilding($building, $state) : null;
                if (! $unit) return;
                $set('code', $unit->code); $set('unit_type', $unit->unit_type); $set('planned_area', $unit->planned_area); $set('sort_order', $unit->sort_order); $set('description', $unit->description);
            });
    }

    private function specificationSelect(ProjectBuilding $building): Forms\Components\Select
    {
        return Forms\Components\Select::make('specification_id')->label('Specification')->options($this->specificationOptions($building))->live()->required()
            ->afterStateUpdated(function ($state, Forms\Set $set) use ($building): void {
                $specification = $state ? $this->specificationForBuilding($building, $state) : null;
                if (! $specification) return;
                $set('name', $specification->name); $set('value', $specification->value); $set('unit', $specification->unit); $set('sort_order', $specification->sort_order); $set('notes', $specification->notes);
            });
    }

    private function structureInfolist(): array
    {
        return [
            Infolists\Components\TextEntry::make('name')->label('Building'),
            Infolists\Components\TextEntry::make('code')->badge()->placeholder('No code'),
            Infolists\Components\TextEntry::make('building_type')->label('Type')->placeholder('Not specified'),
            Infolists\Components\TextEntry::make('status')->badge(),
            Infolists\Components\TextEntry::make('description')->columnSpanFull()->placeholder('No description'),
            Infolists\Components\RepeatableEntry::make('floors')->label('Floors, planned units, and specifications')->schema([
                Infolists\Components\TextEntry::make('floor_number')->label('Floor')->badge(),
                Infolists\Components\TextEntry::make('label')->placeholder('No label'),
                Infolists\Components\TextEntry::make('description')->columnSpanFull()->placeholder('No floor description'),
                Infolists\Components\RepeatableEntry::make('plannedUnits')->label('Planned units')->schema([
                    Infolists\Components\TextEntry::make('code')->label('Unit')->badge(),
                    Infolists\Components\TextEntry::make('unit_type')->label('Type'),
                    Infolists\Components\TextEntry::make('planned_area')->suffix(' m²')->placeholder('Area not set'),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('description')->columnSpanFull()->placeholder('No unit description'),
                    Infolists\Components\RepeatableEntry::make('specifications')->label('Specifications')->schema([
                        Infolists\Components\TextEntry::make('name')->weight('medium'),
                        Infolists\Components\TextEntry::make('value'),
                        Infolists\Components\TextEntry::make('unit')->placeholder('No unit'),
                        Infolists\Components\TextEntry::make('notes')->columnSpanFull()->placeholder('No notes'),
                    ])->columns(2),
                ])->columns(4),
            ])->columns(2),
        ];
    }

    private function floorOptions(ProjectBuilding $building): array
    {
        return $building->floors()->orderBy('floor_number')->get()->mapWithKeys(fn (ProjectBuildingFloor $floor) => [$floor->id => "Floor {$floor->floor_number}" . ($floor->label ? " — {$floor->label}" : '')])->all();
    }

    private function plannedUnitOptions(ProjectBuilding $building, string|array|null $statuses = null): array
    {
        $query = ProjectPlannedUnit::withoutGlobalScopes()->whereIn('project_building_floor_id', $building->floors()->select('id'));
        if ($statuses) $query->whereIn('status', (array) $statuses);
        return $query->with('floor')->orderBy('code')->get()->mapWithKeys(fn (ProjectPlannedUnit $unit) => [$unit->id => "{$unit->code} — Floor {$unit->floor->floor_number} ({$unit->status})"])->all();
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
