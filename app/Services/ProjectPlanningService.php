<?php

namespace App\Services;

use App\Models\{PlannedUnitSpecification, Project, ProjectBuilding, ProjectBuildingFloor, ProjectMember, ProjectPlannedUnit, ProjectProperty, Property, User};
use App\Notifications\ProjectWorkflowNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectPlanningService
{
    public function createProject(User $actor, array $attributes): Project
    {
        $this->authorize($actor, 'create', new Project(['company_id' => $attributes['company_id'] ?? null]));
        $companyId = $this->resolveCompanyId($actor, $attributes['company_id'] ?? null);
        unset($attributes['company_id']);

        return Project::withoutGlobalScopes()->create(array_merge($attributes, ['company_id' => $companyId, 'status' => 'planning']));
    }

    public function attachProperty(User $actor, Project $project, Property $property, array $attributes = []): ProjectProperty
    {
        $this->authorize($actor, 'attachProperty', $project);
        $this->assertProjectMutable($project);
        $this->sameCompany($project, $property);
        if ($project->projectProperties()->where('property_id', $property->id)->exists()) {
            throw ValidationException::withMessages(['property_id' => 'This property has already been attached to the project.']);
        }
        return DB::transaction(function () use ($actor, $project, $property, $attributes) {
            $link = ProjectProperty::create(array_merge($attributes, ['company_id' => $project->company_id, 'project_id' => $project->id, 'property_id' => $property->id, 'attached_at' => now()]));
            if (in_array($project->status, ['approved', 'in_progress'], true))
                DB::afterCommit(fn() => $this->notify($actor, $project, 'Project property attached', "{$property->name} was attached to project {$project->name}."));
            return $link;
        });
    }

    public function detachProperty(User $actor, ProjectProperty $link): ProjectProperty
    {
        $this->authorize($actor, 'detachProperty', $link->project);
        $this->assertProjectMutable($link->project);
        if ($link->detached_at)
            throw ValidationException::withMessages(['property_id' => 'This property is already detached from the project.']);
        return DB::transaction(function () use ($actor, $link) {
            $link->update(['detached_at' => now()]);
            if (in_array($link->project->status, ['approved', 'in_progress'], true))
                DB::afterCommit(fn() => $this->notify($actor, $link->project, 'Project property detached', "A property was detached from project {$link->project->name}."));
            return $link->refresh();
        });
    }

    public function updateProjectProperty(User $actor, ProjectProperty $link, array $attributes): ProjectProperty
    {
        $this->authorize($actor, 'update', $link);
        $this->assertProjectMutable($link->project);
        if ($link->detached_at) {
            throw ValidationException::withMessages(['property_id' => 'A detached property link is historical and cannot be edited.']);
        }

        $link->update(collect($attributes)->only(['role', 'notes'])->all());

        return $link->refresh();
    }

    public function assignMember(User $actor, Project $project, User $member, array $attributes): ProjectMember
    {
        $this->authorize($actor, 'manageMembers', $project);
        $this->assertProjectMutable($project);
        $this->sameCompany($project, $member);
        return DB::transaction(function () use ($actor, $project, $member, $attributes) {
            $existing = ProjectMember::withoutGlobalScopes()->where('project_id', $project->id)->where('user_id', $member->id)->first();
            if ($existing && !$existing->ended_at)
                throw ValidationException::withMessages(['user_id' => 'This user is already an active project member.']);
            $data = array_merge($attributes, ['company_id' => $project->company_id, 'project_id' => $project->id, 'user_id' => $member->id, 'started_at' => $attributes['started_at'] ?? now(), 'ended_at' => null]);
            $link = $existing ? tap($existing)->update($data)->refresh() : ProjectMember::create($data);
            DB::afterCommit(fn() => $member->notify(new ProjectWorkflowNotification('Project membership assigned', "You were assigned to project {$project->name} as {$link->role}.", $project->id)));
            return $link;
        });
    }

    public function endMember(User $actor, ProjectMember $member): ProjectMember
    {
        $this->authorize($actor, 'manageMembers', $member->project);
        $this->assertProjectMutable($member->project);
        if ($member->ended_at)
            throw ValidationException::withMessages(['user_id' => 'This project membership has already ended.']);
        $member->update(['ended_at' => now()]);
        DB::afterCommit(fn() => $member->user->notify(new ProjectWorkflowNotification('Project membership ended', "Your assignment to project {$member->project->name} has ended.", $member->project_id)));
        return $member->refresh();
    }

    public function updateMember(User $actor, ProjectMember $member, array $attributes): ProjectMember
    {
        $this->authorize($actor, 'update', $member);
        $this->assertProjectMutable($member->project);
        if ($member->ended_at) {
            throw ValidationException::withMessages(['user_id' => 'An ended membership is historical and cannot be edited.']);
        }

        $member->update(collect($attributes)->only(['role', 'started_at', 'notes'])->all());

        return $member->refresh();
    }

    public function createBuilding(User $actor, Project $project, array $attributes): ProjectBuilding
    {
        $this->assertProjectMutable($project);
        $this->authorize($actor, 'update', $project);
        $this->authorize($actor, 'create', new ProjectBuilding(['company_id' => $project->company_id]));
        $this->assertKnownType($attributes, 'building_type', ProjectBuilding::TYPES);
        return ProjectBuilding::create(array_merge($attributes, ['company_id' => $project->company_id, 'project_id' => $project->id, 'status' => $attributes['status'] ?? 'planned']));
    }

    public function updateBuilding(User $actor, ProjectBuilding $building, array $attributes): ProjectBuilding
    {
        $this->assertProjectMutable($building->project);
        $this->authorize($actor, 'update', $building);
        $this->assertKnownType($attributes, 'building_type', ProjectBuilding::TYPES);
        $building->update(collect($attributes)->only(['name', 'code', 'building_type', 'description', 'sort_order', 'status'])->all());

        return $building->refresh();
    }

    public function deleteBuilding(User $actor, ProjectBuilding $building): void
    {
        $this->assertProjectMutable($building->project);
        $this->authorize($actor, 'delete', $building);
        if ($building->floors()->exists()) {
            throw ValidationException::withMessages(['building' => 'Delete its floors first; a building with planning records cannot be deleted.']);
        }

        $building->delete();
    }
    public function createFloor(User $actor, ProjectBuilding $building, array $attributes): ProjectBuildingFloor
    {
        $this->assertProjectMutable($building->project);
        $this->authorize($actor, 'update', $building->project);
        $this->authorize($actor, 'create', new ProjectBuildingFloor(['company_id' => $building->company_id]));
        return ProjectBuildingFloor::create(array_merge($attributes, ['company_id' => $building->company_id, 'project_building_id' => $building->id]));
    }

    public function updateFloor(User $actor, ProjectBuildingFloor $floor, array $attributes): ProjectBuildingFloor
    {
        $this->assertProjectMutable($floor->building->project);
        $this->authorize($actor, 'update', $floor);
        $floor->update(collect($attributes)->only(['floor_number', 'label', 'description', 'sort_order'])->all());

        return $floor->refresh();
    }

    public function deleteFloor(User $actor, ProjectBuildingFloor $floor): void
    {
        $this->assertProjectMutable($floor->building->project);
        $this->authorize($actor, 'delete', $floor);
        if ($floor->plannedUnits()->exists()) {
            throw ValidationException::withMessages(['floor' => 'Delete its planned units first; a floor with planning records cannot be deleted.']);
        }

        $floor->delete();
    }
    public function createPlannedUnit(User $actor, ProjectBuildingFloor $floor, array $attributes): ProjectPlannedUnit
    {
        $this->assertProjectMutable($floor->building->project);
        $this->authorize($actor, 'update', $floor->building->project);
        $this->authorize($actor, 'create', new ProjectPlannedUnit(['company_id' => $floor->company_id]));
        $this->assertKnownType($attributes, 'unit_type', ProjectPlannedUnit::TYPES, true);
        return ProjectPlannedUnit::create(array_merge($attributes, ['company_id' => $floor->company_id, 'project_building_floor_id' => $floor->id, 'status' => $attributes['status'] ?? 'planned']));
    }

    public function updatePlannedUnit(User $actor, ProjectPlannedUnit $unit, array $attributes): ProjectPlannedUnit
    {
        $this->assertProjectMutable($unit->floor->building->project);
        $this->authorize($actor, 'update', $unit);
        $this->assertPlannedUnitEditable($unit);
        $this->assertKnownType($attributes, 'unit_type', ProjectPlannedUnit::TYPES);
        $unit->update(collect($attributes)->only(['code', 'unit_type', 'planned_area', 'description', 'sort_order'])->all());

        return $unit->refresh();
    }

    public function deletePlannedUnit(User $actor, ProjectPlannedUnit $unit): void
    {
        $this->assertProjectMutable($unit->floor->building->project);
        $this->authorize($actor, 'delete', $unit);
        $this->assertPlannedUnitEditable($unit);
        if ($unit->specifications()->exists()) {
            throw ValidationException::withMessages(['planned_unit' => 'Delete its specifications first; a planned unit with specifications cannot be deleted.']);
        }

        $unit->delete();
    }
    public function createSpecification(User $actor, ProjectPlannedUnit $unit, array $attributes): PlannedUnitSpecification
    {
        $this->assertProjectMutable($unit->floor->building->project);
        $this->authorize($actor, 'update', $unit->floor->building->project);
        $this->authorize($actor, 'create', new PlannedUnitSpecification(['company_id' => $unit->company_id]));
        $this->assertPlannedUnitEditable($unit);
        return PlannedUnitSpecification::create(array_merge($attributes, ['company_id' => $unit->company_id, 'project_planned_unit_id' => $unit->id]));
    }

    public function updateSpecification(User $actor, PlannedUnitSpecification $specification, array $attributes): PlannedUnitSpecification
    {
        $unit = $specification->plannedUnit;
        $this->assertProjectMutable($unit->floor->building->project);
        $this->authorize($actor, 'update', $specification);
        $this->assertPlannedUnitEditable($unit);
        $specification->update(collect($attributes)->only(['name', 'value', 'unit', 'notes', 'sort_order'])->all());

        return $specification->refresh();
    }

    public function deleteSpecification(User $actor, PlannedUnitSpecification $specification): void
    {
        $unit = $specification->plannedUnit;
        $this->assertProjectMutable($unit->floor->building->project);
        $this->authorize($actor, 'delete', $specification);
        $this->assertPlannedUnitEditable($unit);
        $specification->delete();
    }

    public function transitionProject(User $actor, Project $project, string $to, ?string $reason = null): Project
    {
        $ability = ['approved' => 'approve', 'cancelled' => 'cancel', 'closed' => 'close'][$to] ?? 'update';
        $this->authorize($actor, $ability, $project);
        $allowed = ['planning' => ['approved', 'cancelled'], 'approved' => ['in_progress', 'cancelled'], 'in_progress' => ['completed', 'cancelled'], 'completed' => ['closed']];
        if (!in_array($to, $allowed[$project->status] ?? [], true))
            throw ValidationException::withMessages(['status' => "Cannot transition a project from {$project->status} to {$to}."]);
        if ($to === 'approved' && !$project->activeProjectProperties()->exists())
            throw ValidationException::withMessages(['status' => 'At least one active property is required before project approval.']);
        return DB::transaction(function () use ($actor, $project, $to, $reason) {
            $data = ['status' => $to];
            if ($to === 'approved')
                $data += ['approved_at' => now(), 'approved_by' => $actor->id];
            if ($to === 'completed')
                $data += ['completed_at' => now()];
            if ($to === 'closed')
                $data += ['closed_at' => now()];
            if ($to === 'cancelled')
                $data += ['cancelled_at' => now(), 'cancellation_reason' => $reason];
            $project->update($data);
            if (in_array($to, ['approved', 'cancelled'], true))
                DB::afterCommit(fn() => $this->notify($actor, $project, 'Project ' . str_replace('_', ' ', $to), "Project {$project->name} is {$to}."));
            return $project->refresh();
        });
    }

    public function transitionPlannedUnit(User $actor, ProjectPlannedUnit $unit, string $to): ProjectPlannedUnit
    {
        $ability = ['approved' => 'approvePlannedUnit', 'cancelled' => 'cancelPlannedUnit'][$to] ?? 'update';
        $this->authorize($actor, $ability, $unit);
        $allowed = ['planned' => ['approved', 'cancelled'], 'approved' => ['cancelled']];
        if (!in_array($to, $allowed[$unit->status] ?? [], true))
            throw ValidationException::withMessages(['status' => "Cannot transition a planned unit from {$unit->status} to {$to}."]);
        $unit->update(['status' => $to]);
        return $unit->refresh();
    }

    private function assertProjectMutable(Project $project): void
    {
        if (in_array($project->status, ['closed', 'cancelled'], true))
            throw ValidationException::withMessages(['status' => "Project planning records cannot be changed while the project is {$project->status}."]);
    }
    private function assertPlannedUnitEditable(ProjectPlannedUnit $unit): void
    {
        if ($unit->status !== 'planned') {
            throw ValidationException::withMessages(['planned_unit' => 'Only planned units may be edited. Approved or cancelled units retain their approved planning record.']);
        }
    }
    private function assertKnownType(array $attributes, string $field, array $options, bool $required = false): void
    {
        $value = $attributes[$field] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                throw ValidationException::withMessages([$field => 'Select a valid type.']);
            }
            return;
        }
        if (!array_key_exists($value, $options)) {
            throw ValidationException::withMessages([$field => 'The selected type is not supported.']);
        }
    }
    private function resolveCompanyId(User $actor, mixed $companyId): int
    {
        if ($actor->isSuperAdmin()) {
            if (!$companyId)
                throw ValidationException::withMessages(['company_id' => 'A target company is required for Super Admin project creation.']);
            return (int) $companyId;
        }
        return (int) $actor->company_id;
    }
    private function sameCompany(object $left, object $right): void
    {
        if ((int) $left->company_id !== (int) $right->company_id)
            throw ValidationException::withMessages(['company_id' => 'Records from different companies cannot be associated.']);
    }
    private function authorize(User $user, string $ability, object $record): void
    {
        if (!$user->can($ability, $record))
            throw new AuthorizationException;
    }
    private function notify(User $actor, Project $project, string $title, string $body): void
    {
        $project->activeMembers()->with('user')->get()->pluck('user')->filter()->unique('id')->each->notify(new ProjectWorkflowNotification($title, $body, $project->id));
        if (!$project->activeMembers()->where('user_id', $actor->id)->exists())
            $actor->notify(new ProjectWorkflowNotification($title, $body, $project->id));
    }
}
