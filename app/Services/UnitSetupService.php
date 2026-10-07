<?php

namespace App\Services;

use App\Enums\UnitStatus;
use App\Models\{Document, DocumentVersion, Party, Project, ProjectPlannedUnit, Property, Unit, UnitOwnership, UnitStatusHistory, User};
use App\Notifications\ProjectWorkflowNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitSetupService
{
    public const FEATURE_CATALOGUE = [
        'furnished' => ['Furnished', 'boolean'],
        'balcony' => ['Balcony', 'boolean'],
        'parking_spaces' => ['Parking spaces', 'number'],
        'storage_room' => ['Storage room', 'boolean'],
        'maid_room' => ['Maid room', 'boolean'],
        'central_ac' => ['Central AC', 'boolean'],
        'smart_home' => ['Smart home', 'boolean'],
        'sea_view' => ['Sea view', 'boolean'],
        'garden' => ['Garden', 'boolean'],
        'orientation' => ['Orientation', 'select'],
        'kitchen_type' => ['Kitchen type', 'select'],
        'parking_type' => ['Parking type', 'select'],
    ];

    public function create(User $actor, array $attributes): Unit
    {
        $property = Property::withoutGlobalScopes()->findOrFail($attributes['property_id'] ?? 0);
        $this->authorize($actor, 'create', new Unit(['company_id' => $property->company_id]));
        return DB::transaction(function () use ($actor, $property, $attributes): Unit {
            $data = $this->validatedData($property, $attributes);
            $unit = Unit::withoutGlobalScopes()->create($data + ['company_id' => $property->company_id, 'status' => UnitStatus::Draft->value]);
            return $unit;
        });
    }

    public function update(User $actor, Unit $unit, array $attributes): Unit
    {
        $this->authorize($actor, 'update', $unit);
        if ($unit->status === UnitStatus::Inactive->value)
            $this->invalid('unit', 'Inactive Units cannot be edited; reactivate them first.');
        $property = Property::withoutGlobalScopes()->findOrFail($attributes['property_id'] ?? $unit->property_id);
        $data = $this->validatedData($property, $attributes, $unit);
        $unit->update($data);
        return $unit->refresh();
    }

    public function convertPlannedUnit(User $actor, ProjectPlannedUnit $planned, array $attributes): Unit
    {
        $project = $planned->floor->building->project;
        $this->authorize($actor, 'create', new Unit(['company_id' => $planned->company_id]));
        if (!$actor->can('create_actual_unit_from_planned_unit'))
            throw new AuthorizationException;
        if ($planned->status !== 'approved')
            $this->invalid('planned_unit', 'Only an approved planned unit may be converted.');
        if (!in_array($project->status, ['in_progress', 'completed', 'closed'], true))
            $this->invalid('project', 'The project must be in progress, completed, or closed.');
        return DB::transaction(function () use ($actor, $planned, $project, $attributes): Unit {
            $locked = ProjectPlannedUnit::withoutGlobalScopes()->lockForUpdate()->findOrFail($planned->id);
            if ($locked->actualUnit()->withoutGlobalScopes()->exists())
                $this->invalid('planned_unit', 'This planned unit already has an actual Unit.');
            $property = Property::withoutGlobalScopes()->findOrFail($attributes['property_id'] ?? 0);
            $this->sameCompany($locked, $property);
            if (!$project->activeProjectProperties()->where('property_id', $property->id)->exists())
                $this->invalid('property_id', 'The property must be actively attached to this project.');
            $data = $this->validatedData($property, $attributes + ['project_id' => $project->id, 'planned_unit_id' => $locked->id]);
            $unit = Unit::withoutGlobalScopes()->create($data + ['company_id' => $locked->company_id, 'project_id' => $project->id, 'planned_unit_id' => $locked->id, 'status' => UnitStatus::Draft->value]);
            $locked->update(['status' => 'converted']);
            DB::afterCommit(fn() => $this->notify($actor, $project, 'Actual Unit created', "Actual Unit {$unit->unit_number} was created from plan {$locked->code}."));
            return $unit;
        });
    }

    public function transitionStatus(User $actor, Unit $unit, string $to, ?string $reason = null): Unit
    {
        $this->authorize($actor, $to === 'draft' ? 'reactivate' : 'changeStatus', $unit);
        return DB::transaction(function () use ($actor, $unit, $to, $reason): Unit {
            $unit = Unit::withoutGlobalScopes()->lockForUpdate()->findOrFail($unit->id);
            $target = UnitStatus::tryFrom($to);
            if (!$target)
                $this->invalid('status', 'Select a valid physical Unit status.');
            $current = UnitStatus::tryFrom($unit->status);
            if (!$current || !$current->canTransitionTo($target))
                $this->invalid('status', "Cannot move a Unit from {$unit->status} to {$to}.");
            if ($to === 'ready' && (!$unit->property_id || !$unit->unit_number || !$unit->type || !$unit->actual_area || !$unit->area_unit))
                $this->invalid('status', 'A property, unit number, type, actual area, and area unit are required before a Unit is ready.');
            if ($to === 'inactive' && blank($reason))
                $this->invalid('reason', 'An inactive reason is required.');
            if ($to === 'draft' && blank($reason))
                $this->invalid('reason', 'A reactivation reason is required.');
            $from = $unit->status;
            $unit->update(['status' => $to, 'ready_at' => $to === 'ready' ? ($unit->ready_at ?? now()) : $unit->ready_at, 'inactive_at' => $to === 'inactive' ? now() : null, 'inactive_reason' => $to === 'inactive' ? $reason : null]);
            UnitStatusHistory::withoutGlobalScopes()->create(['company_id' => $unit->company_id, 'unit_id' => $unit->id, 'from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'changed_by' => $actor->id, 'changed_at' => now()]);
            if ($to === 'ready' || $to === 'inactive')
                DB::afterCommit(fn() => $this->notify($actor, $unit->project, "Unit {$to}", "Unit {$unit->unit_number} is now {$to}."));
            return $unit->refresh();
        });
    }

    public function replaceOwnership(User $actor, Unit $unit, array $allocations, string $startDate, ?string $notes = null): void
    {
        $this->authorize($actor, 'changeOwnership', $unit);
        if (empty($allocations))
            $this->invalid('ownerships', 'At least one owner is required.');
        if (round(array_sum(array_map(fn($row) => (float) ($row['ownership_percentage'] ?? 0), $allocations)), 2) !== 100.0)
            $this->invalid('ownerships', 'Active ownership must total exactly 100%.');
        DB::transaction(function () use ($actor, $unit, $allocations, $startDate, $notes): void {
            $locked = Unit::withoutGlobalScopes()->lockForUpdate()->findOrFail($unit->id);
            $locked->activeOwnerships()->lockForUpdate()->update(['end_date' => $startDate]);
            foreach ($allocations as $row) {
                $party = Party::withoutGlobalScopes()->findOrFail($row['party_id']);
                $this->sameCompany($locked, $party);
                UnitOwnership::withoutGlobalScopes()->create(['company_id' => $locked->company_id, 'unit_id' => $locked->id, 'party_id' => $party->id, 'ownership_percentage' => $row['ownership_percentage'], 'start_date' => $startDate, 'notes' => $notes, 'changed_by' => $actor->id]);
            }
        });
    }

    public function addDocumentVersion(User $actor, Unit $unit, array $attributes): DocumentVersion
    {
        $this->authorize($actor, 'manageDocuments', $unit);
        return DB::transaction(function () use ($actor, $unit, $attributes): DocumentVersion {
            $document = isset($attributes['document_id']) ? Document::findOrFail($attributes['document_id']) : $unit->documents()->create(['title' => $attributes['title'], 'description' => $attributes['description'] ?? null, 'created_by' => $actor->id]);
            if ($document->documentable_type !== Unit::class || (int) $document->documentable_id !== (int) $unit->id)
                $this->invalid('document_id', 'The document must belong to this Unit.');
            $version = $document->versions()->create(['company_id' => $unit->company_id, 'version_number' => ((int) $document->versions()->max('version_number')) + 1, 'file_name' => $attributes['file_name'], 'file_path' => $attributes['file_path'], 'mime_type' => $attributes['mime_type'] ?? null, 'file_size' => $attributes['file_size'] ?? null, 'notes' => $attributes['notes'] ?? null, 'uploaded_by' => $actor->id]);
            return $version;
        });
    }

    private function validatedData(Property $property, array $attributes, ?Unit $unit = null): array
    {
        if (isset($attributes['company_id']) && (int) $attributes['company_id'] !== (int) $property->company_id)
            $this->invalid('company_id', 'Company is derived from the Property.');
        if ($unit && (int) $unit->company_id !== (int) $property->company_id)
            $this->invalid('property_id', 'A Unit cannot move to a Property from another company.');
        $projectId = $attributes['project_id'] ?? $unit?->project_id;
        $plannedId = $attributes['planned_unit_id'] ?? $unit?->planned_unit_id;
        if ($projectId) {
            $project = Project::withoutGlobalScopes()->findOrFail($projectId);
            $this->sameCompany($property, $project);
            if (!$project->activeProjectProperties()->where('property_id', $property->id)->exists())
                $this->invalid('property_id', 'The Property is not actively attached to this Project.');
        }
        if ($plannedId) {
            $planned = ProjectPlannedUnit::withoutGlobalScopes()->findOrFail($plannedId);
            $this->sameCompany($property, $planned);
            $plannedProjectId = $planned->floor->building->project_id;
            if ((int) $projectId !== (int) $plannedProjectId)
                $this->invalid('planned_unit_id', 'The planned Unit must belong to the selected Project.');
        }
        return collect($attributes)->only(['property_id', 'project_id', 'planned_unit_id', 'unit_number', 'type', 'rent_price', 'bedrooms', 'bathrooms', 'sqft', 'actual_area', 'area_unit', 'location_label', 'description', 'is_featured'])->all();
    }
    private function authorize(User $actor, string $ability, object $record): void
    {
        if (!$actor->can($ability, $record))
            throw new AuthorizationException;
    }
    private function sameCompany(object $left, object $right): void
    {
        if ((int) $left->company_id !== (int) $right->company_id)
            $this->invalid('company_id', 'Records from different companies cannot be associated.');
    }
    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
    private function notify(User $actor, ?Project $project, string $title, string $body): void
    {
        if (!$project) {
            $actor->notify(new ProjectWorkflowNotification($title, $body));
            return;
        }
        $project->activeMembers()->with('user')->get()->pluck('user')->filter()->push($actor)->unique('id')->each->notify(new ProjectWorkflowNotification($title, $body, $project->id));
    }
}
