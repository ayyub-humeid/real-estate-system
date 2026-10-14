<?php

namespace App\Services;

use App\Enums\{CrmActivityOutcome, CrmActivityType, CrmTaskStatus};
use App\Models\{Company, CrmActivity, CrmFollowUpTask, Lead, Opportunity, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmTaskService
{
    public function create(User $actor, array $data): CrmFollowUpTask
    {
        $company = $this->company($actor, $data['company_id'] ?? null);
        if (!$actor->can('create', CrmFollowUpTask::class))
            throw new AuthorizationException;
        return DB::transaction(function () use ($actor, $company, $data): CrmFollowUpTask {
            [$lead, $opportunity] = $this->subjects($company, $data);
            $assignee = $this->assignee($company, $data['assigned_to'] ?? null);
            $type = $this->type($data['type'] ?? null);
            $title = trim((string) ($data['title'] ?? ''));
            if ($title === '')
                $this->invalid('title', __('crm.tasks.errors.title_required'));
            return CrmFollowUpTask::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'lead_id' => $lead?->id,
                'opportunity_id' => $opportunity?->id,
                'created_by' => $actor->id,
                'assigned_to' => $assignee->id,
                'type' => $type->value,
                'status' => CrmTaskStatus::Pending->value,
                'title' => $title,
                'description' => $data['description'] ?? null,
                'due_at' => $this->dueAt($data['due_at'] ?? null),
            ]);
        });
    }

    public function update(User $actor, CrmFollowUpTask $task, array $data): CrmFollowUpTask
    {
        if (!$actor->can('update', $task)) throw new AuthorizationException;
        return DB::transaction(function () use ($actor, $task, $data): CrmFollowUpTask {
            $locked = CrmFollowUpTask::withoutGlobalScopes()->lockForUpdate()->findOrFail($task->id);
            if (!$actor->isSuperAdmin() && (int) $actor->company_id !== (int) $locked->company_id) throw new AuthorizationException;
            if (!in_array($locked->status, [CrmTaskStatus::Pending, CrmTaskStatus::InProgress], true)) $this->invalid('status', __('crm.tasks.errors.not_editable'));
            $company = Company::withoutGlobalScopes()->findOrFail($locked->company_id);
            [$lead, $opportunity] = $this->subjects($company, [...$data, 'lead_id' => $data['lead_id'] ?? $locked->lead_id, 'opportunity_id' => $data['opportunity_id'] ?? $locked->opportunity_id]);
            $assignee = $this->assignee($company, $data['assigned_to'] ?? $locked->assigned_to);
            $type = $this->type($data['type'] ?? $locked->type);
            $title = trim((string) ($data['title'] ?? $locked->title));
            if ($title === '') $this->invalid('title', __('crm.tasks.errors.title_required'));
            $locked->update(['lead_id'=>$lead?->id, 'opportunity_id'=>$opportunity?->id, 'assigned_to'=>$assignee->id, 'type'=>$type->value, 'title'=>$title, 'description'=>$data['description'] ?? $locked->description, 'due_at'=>$this->dueAt($data['due_at'] ?? $locked->due_at)]);
            return $locked->refresh();
        });
    }

    public function complete(User $actor, CrmFollowUpTask $task, array $data): CrmActivity
    {
        if (!$actor->can('complete', $task))
            throw new AuthorizationException;
        return DB::transaction(function () use ($actor, $task, $data): CrmActivity {
            $locked = CrmFollowUpTask::withoutGlobalScopes()->lockForUpdate()->findOrFail($task->id);
            if (!$actor->isSuperAdmin() && (int) $actor->company_id !== (int) $locked->company_id)
                throw new AuthorizationException;
            if (!in_array($locked->status, [CrmTaskStatus::Pending, CrmTaskStatus::InProgress], true))
                $this->invalid('status', __('crm.tasks.errors.not_completable'));
            $outcome = $this->outcome($locked->type, $data['outcome'] ?? null);
            $activity = CrmActivity::withoutGlobalScopes()->create([
                'company_id' => $locked->company_id,
                'lead_id' => $locked->lead_id,
                'opportunity_id' => $locked->opportunity_id,
                'task_id' => $locked->id,
                'type' => $locked->type->value,
                'outcome' => $outcome?->value,
                'performed_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
                'occurred_at' => now(),
            ]);
            $locked->update(['status' => CrmTaskStatus::Completed, 'completed_by' => $actor->id, 'completed_at' => now()]);
            if (($data['create_next_follow_up'] ?? false) === true) {
                $this->create($actor, [
                    'company_id' => $locked->company_id,
                    'lead_id' => $locked->lead_id,
                    'opportunity_id' => $locked->opportunity_id,
                    'assigned_to' => $data['next_assigned_to'] ?? $locked->assigned_to,
                    'type' => $data['next_type'] ?? $locked->type->value,
                    'title' => $data['next_title'] ?? $locked->title,
                    'description' => $data['next_description'] ?? null,
                    'due_at' => $data['next_due_at'] ?? null,
                ]);
            }
            return $activity;
        });
    }

    public function logActivity(User $actor, array $data): CrmActivity
    {
        $company = $this->company($actor, $data['company_id'] ?? null);
        if (!$actor->can('create', CrmActivity::class))
            throw new AuthorizationException;
        return DB::transaction(function () use ($actor, $company, $data): CrmActivity {
            [$lead, $opportunity] = $this->subjects($company, $data);
            $type = $this->type($data['type'] ?? null);
            $outcome = $this->outcome($type, $data['outcome'] ?? null);
            return CrmActivity::withoutGlobalScopes()->create(['company_id' => $company->id, 'lead_id' => $lead?->id, 'opportunity_id' => $opportunity?->id, 'type' => $type->value, 'outcome' => $outcome?->value, 'performed_by' => $actor->id, 'notes' => $data['notes'] ?? null, 'occurred_at' => $data['occurred_at'] ?? now()]);
        });
    }

    private function company(User $actor, ?int $id): Company
    {
        if ($actor->isSuperAdmin()) {
            if (!$id)
                $this->invalid('company_id', __('crm.errors.company_required'));
            return Company::withoutGlobalScopes()->findOrFail($id);
        }
        if (!$actor->company_id || ($id && (int) $id !== (int) $actor->company_id))
            $this->invalid('company_id', $id ? __('crm.errors.cross_company') : __('crm.errors.company_required'));
        return Company::withoutGlobalScopes()->findOrFail($actor->company_id);
    }
    private function subjects(Company $company, array $data): array
    {
        $lead = !empty($data['lead_id']) ? Lead::withoutGlobalScopes()->findOrFail($data['lead_id']) : null;
        $opportunity = !empty($data['opportunity_id']) ? Opportunity::withoutGlobalScopes()->findOrFail($data['opportunity_id']) : null;
        foreach ([$lead, $opportunity] as $subject)
            if ($subject && (int) $subject->company_id !== (int) $company->id)
                $this->invalid('company_id', __('crm.errors.cross_company'));
        if ($opportunity && $lead && (int) $opportunity->lead_id !== (int) $lead->id)
            $this->invalid('lead_id', __('crm.tasks.errors.subject_mismatch'));
        return [$lead ?: $opportunity?->lead, $opportunity];
    }
    private function assignee(Company $company, mixed $id): User
    {
        if (!$id)
            $this->invalid('assigned_to', __('crm.tasks.errors.assignee_required'));
        $user = User::withoutGlobalScopes()->findOrFail($id);
        if ((int) $user->company_id !== (int) $company->id)
            $this->invalid('assigned_to', __('crm.errors.cross_company'));
        return $user;
    }
    private function type(mixed $value): CrmActivityType
    {
        $type = $value instanceof CrmActivityType ? $value : CrmActivityType::tryFrom((string) $value);
        if (!$type)
            $this->invalid('type', __('crm.tasks.errors.type_invalid'));
        return $type;
    }
    private function dueAt(mixed $value): mixed
    {
        if ($value === null || $value === '') return null;
        $dueAt = $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);
        if ($dueAt->lt(now())) $this->invalid('due_at', __('crm.tasks.errors.due_date_past'));
        return $dueAt;
    }
    private function outcome(CrmActivityType $type, mixed $value): ?CrmActivityOutcome
    {
        if ($value === null || $value === '') {
            if ($type === CrmActivityType::Note)
                return null;
            $this->invalid('outcome', __('crm.tasks.errors.outcome_required'));
        }
        $outcome = $value instanceof CrmActivityOutcome ? $value : CrmActivityOutcome::tryFrom((string) $value);
        if (!$outcome || !array_key_exists($outcome->value, CrmActivityOutcome::optionsFor($type)))
            $this->invalid('outcome', __('crm.tasks.errors.outcome_invalid'));
        return $outcome;
    }
    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
