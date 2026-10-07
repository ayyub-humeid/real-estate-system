<?php

namespace App\Services;

use App\Models\{BudgetLine, ConstructionDelay, ConstructionInspection, ConstructionIssue, ConstructionProgressUpdate, ConstructionWorkPackage, ConstructionWorkPackageTask, Party, Project, ProjectConstruction, ProjectMember, User};
use App\Notifications\ProjectWorkflowNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConstructionService
{
    public function createConstruction(User $actor, Project $project, array $attributes): ProjectConstruction
    {
        $this->authorize($actor, 'update', $project);
        $this->authorize($actor, 'create', new ProjectConstruction(['company_id' => $project->company_id]));
        if (!in_array($project->status, ['approved', 'in_progress'], true))
            $this->invalid('project', 'Construction can be prepared only for an approved or in-progress project.');
        $this->dates($attributes, 'planned_start_date', 'expected_completion_date');
        $this->party($project, $attributes['manager_party_id'] ?? null, true);

        return DB::transaction(function () use ($project, $attributes) {
            // Lock the stable parent row. This serializes concurrent requests even
            // when no active child exists, on both MySQL and PostgreSQL.
            $lockedProject = Project::withoutGlobalScopes()->lockForUpdate()->findOrFail($project->id);
            $cycles = ProjectConstruction::withoutGlobalScopes()->where('project_id', $lockedProject->id)->lockForUpdate();
            if ((clone $cycles)->whereIn('status', ['planned', 'in_progress'])->exists())
                $this->invalid('project', 'This project already has an active construction execution.');
            // The locked Project row serializes all creation through this service;
            // do not apply FOR UPDATE to the aggregate, which PostgreSQL forbids.
            $executionNumber = ((int) ProjectConstruction::withoutGlobalScopes()->where('project_id', $lockedProject->id)->max('execution_number')) + 1;
            return ProjectConstruction::create(array_merge(collect($attributes)->only(['planned_start_date', 'expected_completion_date', 'manager_party_id', 'notes'])->all(), ['company_id' => $lockedProject->company_id, 'project_id' => $lockedProject->id, 'execution_number' => $executionNumber]));
        });
    }

    public function updateConstruction(User $actor, ProjectConstruction $construction, array $attributes): ProjectConstruction
    {
        $this->authorize($actor, 'update', $construction);
        $this->assertConstructionMutable($construction);
        $this->dates($attributes, 'planned_start_date', 'expected_completion_date');
        $this->party($construction->project, $attributes['manager_party_id'] ?? null, true);
        $construction->update(collect($attributes)->only(['planned_start_date', 'expected_completion_date', 'manager_party_id', 'notes'])->all());
        return $construction->refresh();
    }

    public function startConstruction(User $actor, ProjectConstruction $construction): ProjectConstruction
    {
        $this->authorize($actor, 'start', $construction);
        if ($construction->status !== 'planned')
            $this->transition('construction', $construction->status, 'in_progress');
        if ($construction->project->status !== 'in_progress')
            $this->invalid('project', 'Start the Project before starting site construction.');
        $construction->forceFill(['status' => 'in_progress', 'actual_start_date' => $construction->actual_start_date ?? today(), 'started_by' => $actor->id])->save();
        DB::afterCommit(fn() => $this->notify($actor, $construction->project, 'Construction started', "Construction started for {$construction->project->name}."));
        return $construction->refresh();
    }

    public function cancelConstruction(User $actor, ProjectConstruction $construction, string $reason): ProjectConstruction
    {
        $this->authorize($actor, 'cancel', $construction);
        if (!in_array($construction->status, ['planned', 'in_progress'], true))
            $this->transition('construction', $construction->status, 'cancelled');
        if (blank($reason))
            $this->invalid('reason', 'A cancellation reason is required.');
        $construction->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason])->save();
        return $construction->refresh();
    }

    public function completeConstruction(User $actor, ProjectConstruction $construction): ProjectConstruction
    {
        $this->authorize($actor, 'complete', $construction);
        if ($construction->status !== 'in_progress')
            $this->transition('construction', $construction->status, 'completed');
        return DB::transaction(function () use ($actor, $construction) {
            $record = ProjectConstruction::withoutGlobalScopes()->lockForUpdate()->findOrFail($construction->id);
            $packages = $record->workPackages()->where('status', '!=', 'cancelled')->lockForUpdate()->get();
            if ($packages->isEmpty() || $packages->contains(fn($package) => $package->status !== 'completed'))
                $this->invalid('construction', 'Every active work package must be completed before construction can be completed.');
            if ($this->hasCriticalIssues($record))
                $this->invalid('construction', 'Resolve active critical construction issues before completion.');
            $record->forceFill(['status' => 'completed', 'progress_percentage' => 100, 'actual_completion_date' => today(), 'completed_by' => $actor->id])->save();
            DB::afterCommit(fn() => $this->notify($actor, $record->project, 'Construction completed', "Construction completed for {$record->project->name}."));
            return $record->refresh();
        });
    }

    public function deleteConstruction(User $actor, ProjectConstruction $construction): void
    {
        $this->authorize($actor, 'delete', $construction);
        if ($construction->workPackages()->exists())
            $this->invalid('construction', 'Remove the empty planned construction before adding work packages, or cancel it once work has begun.');
        $construction->delete();
    }

    public function createWorkPackage(User $actor, ProjectConstruction $construction, array $attributes): ConstructionWorkPackage
    {
        $this->authorize($actor, 'create', new ConstructionWorkPackage(['company_id' => $construction->company_id]));
        $this->assertConstructionMutable($construction);
        $this->dates($attributes, 'planned_start_date', 'planned_end_date');
        $line = $this->line($construction->project, $attributes['budget_line_id'] ?? null);
        $party = $this->party($construction->project, $attributes['responsible_party_id'] ?? null, true);
        return ConstructionWorkPackage::create(array_merge(collect($attributes)->only(['name', 'code', 'description', 'planned_start_date', 'planned_end_date', 'notes'])->all(), ['company_id' => $construction->company_id, 'project_construction_id' => $construction->id, 'budget_line_id' => $line?->id, 'responsible_party_id' => $party?->id]));
    }

    public function updateWorkPackage(User $actor, ConstructionWorkPackage $package, array $attributes): ConstructionWorkPackage
    {
        $this->authorize($actor, 'update', $package);
        $this->assertPackageMutable($package);
        $this->dates($attributes, 'planned_start_date', 'planned_end_date');
        $line = $this->line($package->construction->project, $attributes['budget_line_id'] ?? null);
        $party = $this->party($package->construction->project, $attributes['responsible_party_id'] ?? null, true);
        $package->update(array_merge(collect($attributes)->only(['name', 'code', 'description', 'planned_start_date', 'planned_end_date', 'notes'])->all(), ['budget_line_id' => $line?->id, 'responsible_party_id' => $party?->id]));
        return $package->refresh();
    }

    public function deleteWorkPackage(User $actor, ConstructionWorkPackage $package): void
    {
        $this->authorize($actor, 'delete', $package);
        if ($package->tasks()->exists() || $package->issues()->exists() || $package->delays()->exists())
            $this->invalid('work_package', 'A work package with task, issue, or delay history cannot be deleted. Cancel it instead.');
        $package->delete();
    }

    public function startWorkPackage(User $actor, ConstructionWorkPackage $package): ConstructionWorkPackage
    {
        $this->authorize($actor, 'start', $package);
        if ($package->status !== 'planned')
            $this->transition('work package', $package->status, 'in_progress');
        if ($package->construction->status !== 'in_progress')
            $this->invalid('construction', 'Start construction before starting a work package.');
        $package->forceFill(['status' => 'in_progress', 'actual_start_date' => $package->actual_start_date ?? today()])->save();
        DB::afterCommit(fn() => $this->notify($actor, $package->construction->project, 'Work package started', "{$package->name} is now in progress."));
        return $package->refresh();
    }

    public function cancelWorkPackage(User $actor, ConstructionWorkPackage $package, string $reason): ConstructionWorkPackage
    {
        $this->authorize($actor, 'cancel', $package);
        if (!in_array($package->status, ['planned', 'in_progress'], true))
            $this->transition('work package', $package->status, 'cancelled');
        if (blank($reason))
            $this->invalid('reason', 'A cancellation reason is required.');
        $package->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason])->save();
        $this->recalculateConstruction($package->construction);
        return $package->refresh();
    }

    public function completeWorkPackage(User $actor, ConstructionWorkPackage $package): ConstructionWorkPackage
    {
        $this->authorize($actor, 'complete', $package);
        if ($package->status !== 'in_progress')
            $this->transition('work package', $package->status, 'completed');
        return DB::transaction(function () use ($actor, $package) {
            $record = ConstructionWorkPackage::withoutGlobalScopes()->lockForUpdate()->findOrFail($package->id);
            $tasks = $record->tasks()->where('status', '!=', 'cancelled')->lockForUpdate()->get();
            if ($tasks->isEmpty() || $tasks->contains(fn($task) => $task->status !== 'completed'))
                $this->invalid('work package', 'Every active task must be completed before this work package can be completed.');
            if ($this->hasCriticalIssues($record))
                $this->invalid('work package', 'Resolve active critical issues before completing this work package.');
            $record->forceFill(['status' => 'completed', 'progress_percentage' => 100, 'actual_end_date' => today()])->save();
            $this->recalculateConstruction($record->construction);
            DB::afterCommit(fn() => $this->notify($actor, $record->construction->project, 'Work package completed', "{$record->name} is complete."));
            return $record->refresh();
        });
    }

    public function createTask(User $actor, ConstructionWorkPackage $package, array $attributes): ConstructionWorkPackageTask
    {
        $this->authorize($actor, 'create', new ConstructionWorkPackageTask(['company_id' => $package->company_id]));
        $this->assertPackageMutable($package);
        $this->dates($attributes, 'planned_start_date', 'planned_end_date');
        $party = $this->party($package->construction->project, $attributes['assigned_party_id'] ?? null, true);
        return ConstructionWorkPackageTask::create(array_merge(collect($attributes)->only(['name', 'description', 'planned_start_date', 'planned_end_date', 'requires_inspection', 'notes'])->all(), ['company_id' => $package->company_id, 'construction_work_package_id' => $package->id, 'assigned_party_id' => $party?->id]));
    }

    public function updateTask(User $actor, ConstructionWorkPackageTask $task, array $attributes): ConstructionWorkPackageTask
    {
        $this->authorize($actor, 'update', $task);
        $this->assertTaskMutable($task);
        $this->dates($attributes, 'planned_start_date', 'planned_end_date');
        $party = $this->party($task->workPackage->construction->project, $attributes['assigned_party_id'] ?? null, true);
        $task->update(array_merge(collect($attributes)->only(['name', 'description', 'planned_start_date', 'planned_end_date', 'requires_inspection', 'notes'])->all(), ['assigned_party_id' => $party?->id]));
        return $task->refresh();
    }

    public function deleteTask(User $actor, ConstructionWorkPackageTask $task): void
    {
        $this->authorize($actor, 'delete', $task);
        if ($task->progressUpdates()->exists() || $task->inspections()->exists() || $task->issues()->exists() || $task->delays()->exists())
            $this->invalid('task', 'A task with progress, inspection, issue, or delay history cannot be deleted. Cancel it instead.');
        $task->delete();
    }

    public function startTask(User $actor, ConstructionWorkPackageTask $task): ConstructionWorkPackageTask
    {
        $this->authorize($actor, 'start', $task);
        if ($task->status !== 'planned')
            $this->transition('task', $task->status, 'in_progress');
        if ($task->workPackage->status !== 'in_progress')
            $this->invalid('work package', 'Start the work package before starting a task.');
        $task->forceFill(['status' => 'in_progress', 'actual_start_date' => $task->actual_start_date ?? today()])->save();
        return $task->refresh();
    }

    public function cancelTask(User $actor, ConstructionWorkPackageTask $task, string $reason): ConstructionWorkPackageTask
    {
        $this->authorize($actor, 'cancel', $task);
        if (!in_array($task->status, ['planned', 'in_progress', 'awaiting_inspection'], true))
            $this->transition('task', $task->status, 'cancelled');
        if (blank($reason))
            $this->invalid('reason', 'A cancellation reason is required.');
        $task->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason])->save();
        $this->recalculatePackage($task->workPackage);
        return $task->refresh();
    }

    public function recordProgress(User $actor, ConstructionWorkPackageTask $task, array $attributes): ConstructionProgressUpdate
    {
        $this->authorize($actor, 'recordProgress', $task);
        if ($task->status !== 'in_progress')
            $this->invalid('task', 'Progress can be recorded only for an in-progress task.');
        $this->percentage($attributes['progress_percentage'] ?? null);
        $this->pastDate($attributes['reported_at'] ?? null, 'reported_at', 'Progress date');
        if ((float) $attributes['progress_percentage'] < (float) $task->progress_percentage)
            $this->invalid('progress_percentage', 'Progress cannot decrease; use the controlled correction action.');
        return DB::transaction(function () use ($actor, $task, $attributes) {
            $task = ConstructionWorkPackageTask::withoutGlobalScopes()->lockForUpdate()->findOrFail($task->id);
            $update = ConstructionProgressUpdate::create(['company_id' => $task->company_id, 'construction_work_package_task_id' => $task->id, 'progress_percentage' => $attributes['progress_percentage'], 'reported_at' => $attributes['reported_at'], 'notes' => $attributes['notes'] ?? null, 'reported_by' => $actor->id]);
            $this->applyTaskProgress($task, (float) $update->progress_percentage);
            return $update;
        });
    }

    public function correctProgress(User $actor, ConstructionProgressUpdate $current, array $attributes): ConstructionProgressUpdate
    {
        $this->authorize($actor, 'correctProgress', $current);
        $task = $current->task;
        if (in_array($task->status, ['completed', 'cancelled'], true))
            $this->invalid('task', 'Completed or cancelled tasks cannot receive corrections.');
        $this->percentage($attributes['progress_percentage'] ?? null);
        $this->pastDate($attributes['reported_at'] ?? null, 'reported_at', 'Progress date');
        if (blank($attributes['correction_reason'] ?? null))
            $this->invalid('correction_reason', 'A correction reason is required.');
        if ($this->effectiveUpdate($task)?->id !== $current->id)
            $this->invalid('progress_update', 'Only the current effective progress update can be corrected.');
        return DB::transaction(function () use ($actor, $current, $task, $attributes) {
            $task = ConstructionWorkPackageTask::withoutGlobalScopes()->lockForUpdate()->findOrFail($task->id);
            $update = ConstructionProgressUpdate::create(['company_id' => $task->company_id, 'construction_work_package_task_id' => $task->id, 'progress_percentage' => $attributes['progress_percentage'], 'reported_at' => $attributes['reported_at'], 'notes' => $attributes['notes'] ?? null, 'reported_by' => $actor->id, 'corrects_progress_update_id' => $current->id, 'correction_reason' => $attributes['correction_reason']]);
            $this->applyTaskProgress($task, (float) $update->progress_percentage);
            return $update;
        });
    }

    public function recordInspection(User $actor, ConstructionWorkPackageTask $task, array $attributes): ConstructionInspection
    {
        $this->authorize($actor, 'createInspection', $task);
        if ($task->status !== 'awaiting_inspection' || (float) $task->progress_percentage !== 100.0)
            $this->invalid('task', 'Inspections require a task awaiting inspection at 100% progress.');
        if (!in_array($attributes['result'] ?? null, ConstructionInspection::RESULTS, true))
            $this->invalid('result', 'Select a supported inspection result.');
        $this->pastDate($attributes['inspection_date'] ?? null, 'inspection_date', 'Inspection date');
        $party = $this->party($task->workPackage->construction->project, $attributes['inspector_party_id'] ?? null, true);
        return DB::transaction(function () use ($actor, $task, $attributes, $party) {
            $inspection = ConstructionInspection::create(['company_id' => $task->company_id, 'construction_work_package_task_id' => $task->id, 'inspector_party_id' => $party?->id, 'inspection_date' => $attributes['inspection_date'], 'result' => $attributes['result'], 'notes' => $attributes['notes'] ?? null, 'recorded_by' => $actor->id]);
            if ($inspection->result === 'failed')
                DB::afterCommit(fn() => $this->notify($actor, $task->workPackage->construction->project, 'Construction inspection failed', "Inspection failed for {$task->name}."));
            return $inspection;
        });
    }

    public function completeTask(User $actor, ConstructionWorkPackageTask $task): ConstructionWorkPackageTask
    {
        $this->authorize($actor, 'complete', $task);
        if (!in_array($task->status, ['in_progress', 'awaiting_inspection'], true))
            $this->transition('task', $task->status, 'completed');
        if ((float) $task->progress_percentage !== 100.0)
            $this->invalid('progress', 'Task progress must reach 100% before completion.');
        if ($task->requires_inspection && !in_array($task->inspections()->latest('id')->value('result'), ['passed', 'passed_with_notes'], true))
            $this->invalid('inspection', 'A passed inspection is required before completing this task.');
        $task->forceFill(['status' => 'completed', 'progress_percentage' => 100, 'actual_end_date' => today()])->save();
        $this->recalculatePackage($task->workPackage);
        return $task->refresh();
    }

    public function createIssue(User $actor, ConstructionWorkPackage $package, array $attributes): ConstructionIssue
    {
        $this->authorize($actor, 'create', new ConstructionIssue(['company_id' => $package->company_id]));
        $this->assertPackageMutable($package);
        if (!in_array($attributes['severity'] ?? null, ConstructionIssue::SEVERITIES, true))
            $this->invalid('severity', 'Select a supported issue severity.');
        $this->pastDate($attributes['opened_at'] ?? null, 'opened_at', 'Issue opened date');
        $task = $this->taskForPackage($package, $attributes['construction_work_package_task_id'] ?? null);
        $this->projectMember($package->construction->project, $attributes['assigned_to_user_id'] ?? null);
        $issue = ConstructionIssue::create(array_merge(collect($attributes)->only(['assigned_to_user_id', 'title', 'description', 'severity', 'opened_at'])->all(), ['company_id' => $package->company_id, 'construction_work_package_id' => $package->id, 'construction_work_package_task_id' => $task?->id]));
        if ($issue->severity === 'critical')
            DB::afterCommit(fn() => $this->notify($actor, $package->construction->project, 'Critical construction issue', "{$issue->title} requires attention."));
        return $issue;
    }

    public function transitionIssue(User $actor, ConstructionIssue $issue, string $to, ?string $notes = null): ConstructionIssue
    {
        $ability = ['in_progress' => 'start', 'resolved' => 'resolve', 'closed' => 'close'][$to] ?? 'update';
        $this->authorize($actor, $ability, $issue);
        $allowed = ['open' => ['in_progress', 'resolved'], 'in_progress' => ['resolved'], 'resolved' => ['closed']];
        if (!in_array($to, $allowed[$issue->status] ?? [], true))
            $this->transition('issue', $issue->status, $to);
        if ($to === 'resolved' && blank($notes))
            $this->invalid('resolution_notes', 'Resolution notes are required.');
        $data = ['status' => $to];
        if ($to === 'resolved')
            $data += ['resolved_at' => today(), 'resolved_by' => $actor->id, 'resolution_notes' => $notes];
        $issue->forceFill($data)->save();
        return $issue->refresh();
    }

    public function recordDelay(User $actor, ConstructionWorkPackage $package, array $attributes): ConstructionDelay
    {
        $this->authorize($actor, 'create', new ConstructionDelay(['company_id' => $package->company_id]));
        $this->assertPackageMutable($package);
        if (!in_array($attributes['reason_code'] ?? null, ConstructionDelay::REASONS, true))
            $this->invalid('reason_code', 'Select a supported delay reason.');
        $this->pastDate($attributes['reported_at'] ?? null, 'reported_at', 'Delay reported date');
        $task = $this->taskForPackage($package, $attributes['construction_work_package_task_id'] ?? null);
        $prior = $task ? $task->delays()->latest('id')->value('revised_end_date') : $package->delays()->whereNull('construction_work_package_task_id')->latest('id')->value('revised_end_date');
        $baseline = $prior ?: ($task?->planned_end_date ?? $package->planned_end_date);
        if (!$baseline)
            $this->invalid('planned_end_date', 'Set a planned end date before recording a delay.');
        if (blank($attributes['revised_end_date'] ?? null))
            $this->invalid('revised_end_date', 'A revised end date is required.');
        try {
            $revisedEndDate = Carbon::parse($attributes['revised_end_date']);
        } catch (\Throwable) {
            $this->invalid('revised_end_date', 'Enter a valid revised end date.');
        }
        if ($revisedEndDate->lte(Carbon::parse($baseline)))
            $this->invalid('revised_end_date', 'The revised end date must be later than the current forecast.');
        $delay = ConstructionDelay::create(['company_id' => $package->company_id, 'construction_work_package_id' => $package->id, 'construction_work_package_task_id' => $task?->id, 'baseline_end_date' => $baseline, 'revised_end_date' => $attributes['revised_end_date'], 'reason_code' => $attributes['reason_code'], 'description' => $attributes['description'] ?? null, 'reported_at' => $attributes['reported_at'], 'reported_by' => $actor->id]);
        DB::afterCommit(fn() => $this->notify($actor, $package->construction->project, 'Construction delay recorded', "A delay was recorded for {$package->name}."));
        return $delay;
    }

    private function applyTaskProgress(ConstructionWorkPackageTask $task, float $progress): void
    {
        $status = $task->status;
        if ($task->requires_inspection && $progress === 100.0)
            $status = 'awaiting_inspection';
        if ($progress < 100.0 && $status === 'awaiting_inspection')
            $status = 'in_progress';
        $task->forceFill(['progress_percentage' => $progress, 'status' => $status])->save();
        $this->recalculatePackage($task->workPackage);
    }

    private function recalculatePackage(ConstructionWorkPackage $package): void
    {
        $tasks = $package->tasks()->where('status', '!=', 'cancelled')->get();
        $progress = $tasks->isEmpty() ? 0 : round((float) $tasks->avg(fn($task) => (float) $task->progress_percentage), 2);
        if ($package->status === 'completed')
            $progress = 100;
        $package->forceFill(['progress_percentage' => $progress])->save();
        $this->recalculateConstruction($package->construction);
    }

    private function recalculateConstruction(ProjectConstruction $construction): void
    {
        $packages = $construction->workPackages()->where('status', '!=', 'cancelled')->get();
        $progress = $packages->isEmpty() ? 0 : round((float) $packages->avg(fn($package) => (float) $package->progress_percentage), 2);
        if ($construction->status === 'completed')
            $progress = 100;
        $construction->forceFill(['progress_percentage' => $progress])->save();
    }

    private function effectiveUpdate(ConstructionWorkPackageTask $task): ?ConstructionProgressUpdate
    {
        return ConstructionProgressUpdate::withoutGlobalScopes()->where('construction_work_package_task_id', $task->id)
            ->whereNotIn('id', ConstructionProgressUpdate::withoutGlobalScopes()
                ->whereNotNull('corrects_progress_update_id')
                ->select('corrects_progress_update_id'))
            ->latest('id')->first();
    }

    private function hasCriticalIssues(ProjectConstruction|ConstructionWorkPackage $subject): bool
    {
        $query = $subject instanceof ProjectConstruction ? ConstructionIssue::withoutGlobalScopes()->whereHas('workPackage', fn($query) => $query->where('project_construction_id', $subject->id)->where('status', '!=', 'cancelled')) : $subject->issues();
        return $query->where('severity', 'critical')->whereIn('status', ['open', 'in_progress'])->exists();
    }

    private function assertConstructionMutable(ProjectConstruction $construction): void
    {
        if (in_array($construction->status, ['completed', 'cancelled'], true) || in_array($construction->project->status, ['closed', 'cancelled'], true))
            $this->invalid('construction', 'This construction record is historical and cannot be changed.');
    }
    private function assertPackageMutable(ConstructionWorkPackage $package): void
    {
        $this->assertConstructionMutable($package->construction);
        if (in_array($package->status, ['completed', 'cancelled'], true))
            $this->invalid('work_package', 'Completed or cancelled work packages cannot be changed.');
    }
    private function assertTaskMutable(ConstructionWorkPackageTask $task): void
    {
        $this->assertPackageMutable($task->workPackage);
        if (in_array($task->status, ['completed', 'cancelled'], true))
            $this->invalid('task', 'Completed or cancelled tasks cannot be changed.');
    }
    private function line(Project $project, mixed $id): ?BudgetLine
    {
        if (!$id)
            return null;
        $line = BudgetLine::withoutGlobalScopes()->findOrFail($id);
        if ((int) $line->company_id !== (int) $project->company_id || (int) $line->project_id !== (int) $project->id)
            $this->invalid('budget_line_id', 'The budget line must belong to this project.');
        return $line;
    }
    private function party(Project $project, mixed $id, bool $nullable): ?Party
    {
        if (!$id) {
            if ($nullable)
                return null;
            $this->invalid('party_id', 'A party is required.');
        }
        $party = Party::withoutGlobalScopes()->findOrFail($id);
        if ((int) $party->company_id !== (int) $project->company_id)
            $this->invalid('party_id', 'The selected party must belong to this project company.');
        return $party;
    }
    private function taskForPackage(ConstructionWorkPackage $package, mixed $id): ?ConstructionWorkPackageTask
    {
        if (!$id)
            return null;
        $task = ConstructionWorkPackageTask::withoutGlobalScopes()->findOrFail($id);
        if ((int) $task->construction_work_package_id !== (int) $package->id || (int) $task->company_id !== (int) $package->company_id)
            $this->invalid('construction_work_package_task_id', 'The selected task must belong to this work package.');
        return $task;
    }
    private function projectMember(Project $project, mixed $id): void
    {
        if (!$id)
            return;
        $member = ProjectMember::withoutGlobalScopes()->where('project_id', $project->id)->where('user_id', $id)->whereNull('ended_at')->first();
        if (!$member)
            $this->invalid('assigned_to_user_id', 'The assignee must be an active member of this project.');
    }
    private function dates(array $attributes, string $start, string $end): void
    {
        if (!empty($attributes[$start]) && !empty($attributes[$end]) && Carbon::parse($attributes[$end])->lt(Carbon::parse($attributes[$start])))
            $this->invalid($end, 'The end date cannot be before the start date.');
    }
    private function pastDate(mixed $date, string $field, string $label): void
    {
        if (blank($date))
            $this->invalid($field, "{$label} is required.");
        try {
            $value = Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            $this->invalid($field, "{$label} must be a valid date.");
        }
        if ($value->gt(today()))
            $this->invalid($field, "{$label} cannot be in the future.");
    }
    private function percentage(mixed $value): void
    {
        if (!is_numeric($value) || (float) $value < 0 || (float) $value > 100)
            $this->invalid('progress_percentage', 'Progress must be between 0 and 100.');
    }
    private function transition(string $name, string $from, string $to): never
    {
        $this->invalid('status', "Cannot transition {$name} from {$from} to {$to}.");
    }
    private function authorize(User $actor, string $ability, object|string $record): void
    {
        if (!$actor->can($ability, $record))
            throw new AuthorizationException;
    }
    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
    private function notify(User $actor, Project $project, string $title, string $body): void
    {
        $project->activeMembers()->with('user')->get()->pluck('user')->filter()->push($actor)->unique('id')->each->notify(new ProjectWorkflowNotification($title, $body, $project->id));
    }
}
