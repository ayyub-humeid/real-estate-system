<?php
namespace App\Services;
use App\Enums\{CrmActivityOutcome, CrmActivityType, CrmRequirementType, CrmStageType};
use App\Models\{CrmPipelineStage, CrmStageRequirement, Opportunity, OpportunityRequirementCompletion, OpportunityStageHistory, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class OpportunityWorkflowService
{
    public function moveToStage(User $actor, Opportunity $record, CrmPipelineStage $target, array $context = []): Opportunity
    {
        $this->authorize($actor, 'moveStage', $record);
        if ($target->stage_type === CrmStageType::Won && !$actor->can('close_won_opportunity'))
            throw new AuthorizationException;
        if ($target->stage_type === CrmStageType::Lost && !$actor->can('close_lost_opportunity'))
            throw new AuthorizationException;
        return DB::transaction(function () use ($actor, $record, $target, $context) {
            $opportunity = Opportunity::withoutGlobalScopes()->with(['lead', 'stage.requirements', 'requirementCompletions'])->lockForUpdate()->findOrFail($record->id);
            if ((int) $target->company_id !== $opportunity->company_id || (int) $target->pipeline_id !== $opportunity->pipeline_id)
                $this->invalid('stage', __('crm.errors.stage_pipeline'));
            if (!$target->is_active)
                $this->invalid('stage', __('crm.errors.stage_inactive'));
            $current = $opportunity->stage;
            if ($current->stage_type !== CrmStageType::Open)
                $this->invalid('stage', __('crm.errors.terminal'));
            $isBackward = $target->stage_type === CrmStageType::Open && $target->position < $current->position;
            if (!$isBackward)
                $this->assertRequirements($opportunity, $current, $current, $target);
            $this->applyClosure($opportunity, $target, $context);
            if ($target->stage_type !== CrmStageType::Open)
                $this->assertRequirements($opportunity, $target, $current, $target);
            $from = $opportunity->pipeline_stage_id;
            $opportunity->forceFill(['pipeline_stage_id' => $target->id, 'closed_at' => $target->stage_type === CrmStageType::Open ? null : now(), 'updated_by' => $actor->id])->save();
            OpportunityStageHistory::withoutGlobalScopes()->create(['company_id' => $opportunity->company_id, 'opportunity_id' => $opportunity->id, 'from_stage_id' => $from, 'to_stage_id' => $target->id, 'changed_by' => $actor->id, 'changed_at' => now(), 'metadata' => ['event' => $isBackward ? 'moved_back' : 'moved']]);
            return $opportunity->refresh();
        });
    }
    public function completeRequirement(User $actor, Opportunity $record, CrmStageRequirement $requirement, ?string $notes = null): OpportunityRequirementCompletion
    {
        $this->authorize($actor, 'moveStage', $record);
        return DB::transaction(function () use ($actor, $record, $requirement, $notes) {
            $opportunity = Opportunity::withoutGlobalScopes()->lockForUpdate()->findOrFail($record->id);
            $lockedRequirement = CrmStageRequirement::withoutGlobalScopes()->lockForUpdate()->findOrFail($requirement->id);
            if ((int) $lockedRequirement->company_id !== $opportunity->company_id || (int) $lockedRequirement->pipeline_stage_id !== $opportunity->pipeline_stage_id)
                $this->invalid('requirement', __('crm.errors.cross_company'));
            if ($lockedRequirement->requirement_type !== CrmRequirementType::ChecklistCompleted)
                $this->invalid('requirement', __('crm.errors.not_checklist'));
            $completion = OpportunityRequirementCompletion::withoutGlobalScopes()->where('opportunity_id', $opportunity->id)->where('stage_requirement_id', $lockedRequirement->id)->lockForUpdate()->first();
            if ($completion) {
                $completion->update(['completed_by' => $actor->id, 'completed_at' => now(), 'notes' => $notes]);
                return $completion->refresh();
            }return OpportunityRequirementCompletion::withoutGlobalScopes()->create(['company_id' => $opportunity->company_id, 'opportunity_id' => $opportunity->id, 'stage_requirement_id' => $lockedRequirement->id, 'completed_by' => $actor->id, 'completed_at' => now(), 'notes' => $notes]);
        });
    }
    private function assertRequirements(
        Opportunity $opportunity,
        CrmPipelineStage $requirementsStage,
        CrmPipelineStage $fromStage,
        CrmPipelineStage $toStage,
    ): void {
        $missing = $this->missingRequirements($opportunity, $requirementsStage)
            ->map(fn (CrmStageRequirement $requirement): string => '• **' . $requirement->display_label . "**\n\n" . $this->requirementGuidance($requirement))
            ->all();
        if ($missing)
            $this->invalid('stage', __('crm.errors.requirements_missing', [
                'from' => $fromStage->display_label,
                'to' => $toStage->display_label,
                'requirements' => implode("\n\n", $missing),
            ]));
    }

    /** @return \Illuminate\Support\Collection<int, CrmStageRequirement> */
    public function missingRequirements(Opportunity $opportunity, CrmPipelineStage $stage): \Illuminate\Support\Collection
    {
        return $stage->requirements()
            ->where('is_active', true)
            ->where('is_required', true)
            ->get()
            ->filter(fn (CrmStageRequirement $requirement): bool => ! $this->requirementIsSatisfied($opportunity, $requirement))
            ->values();
    }
    /**
     * Used by both the protected transition and the Opportunity View screen.
     * The View is informative only; moveToStage remains the authority.
     */
    public function requirementIsSatisfied(Opportunity $o, CrmStageRequirement $r): bool
    {
        $config = $r->config_json ?? [];
        return match ($r->requirement_type) { CrmRequirementType::FieldRequired => $this->value($o, $config['field'] ?? null) !== null && $this->value($o, $config['field'] ?? null) !== '', CrmRequirementType::FieldEquals => $this->value($o, $config['field'] ?? null) == ($config['value'] ?? null), CrmRequirementType::RelatedRecordExists => $this->related($o, $config), CrmRequirementType::ChecklistCompleted => $o->requirementCompletions()->where('stage_requirement_id', $r->id)->exists(), CrmRequirementType::ActivityExists => $this->activity($o, $config), };
    }
    private function value(Opportunity $o, ?string $field): mixed
    {
        return match ($field) { 'lead.normalized_phone_or_email' => ($o->lead?->normalized_phone ?: $o->lead?->normalized_email), 'lead.normalized_phone' => $o->lead?->normalized_phone, 'lead.normalized_email' => $o->lead?->normalized_email, default => $field && in_array($field, ['budget_min', 'budget_max', 'notes', 'lost_reason', 'closure_notes'], true) ? $o->{$field} : null};
    }
    private function related(Opportunity $o, array $config): bool
    {
        if (($config['relation'] ?? null) !== 'inquiries')
            return false;
        $query = $o->lead->inquiries();
        $field = $config['field'] ?? null;
        if ($field === 'contact_result' && ($config['operator'] ?? null) === 'not_empty')
            return $query->whereNotNull('contact_result')->where('contact_result', '!=', '')->exists();
        return false;
    }
    private function activity(Opportunity $opportunity, array $config): bool
    {
        $type = CrmActivityType::tryFrom((string) ($config['activity_type'] ?? ''));
        $outcomes = collect($config['allowed_outcomes'] ?? [])->filter(fn ($value) => CrmActivityOutcome::tryFrom((string) $value))->values()->all();
        if (! $type || $outcomes === []) return false;
        return $opportunity->activities()->where('company_id', $opportunity->company_id)->where('type', $type->value)->whereIn('outcome', $outcomes)->exists();
    }
    public function requirementGuidance(CrmStageRequirement $requirement): string
    {
        $config = $requirement->config_json ?? [];

        return match ($requirement->requirement_type) {
            CrmRequirementType::ChecklistCompleted => __('crm.requirement_guidance.checklist'),
            CrmRequirementType::RelatedRecordExists => (($config['relation'] ?? null) === 'inquiries' && ($config['field'] ?? null) === 'contact_result')
                ? __('crm.requirement_guidance.contact_result')
                : __('crm.requirement_guidance.checklist'),
            CrmRequirementType::FieldRequired => __('crm.requirement_guidance.field', [
                'field' => $this->fieldLabel($config['field'] ?? null),
            ]),
            CrmRequirementType::FieldEquals => __('crm.requirement_guidance.field_equals', [
                'field' => $this->fieldLabel($config['field'] ?? null),
                'value' => $config['value'] ?? '—',
            ]),
            CrmRequirementType::ActivityExists => __('crm.requirement_guidance.activity', [
                'type' => CrmActivityType::tryFrom((string) ($config['activity_type'] ?? ''))?->getLabel() ?? '—',
            ]),
        };
    }
    private function fieldLabel(?string $field): string
    {
        return CrmPipelineStageService::fieldOptions()[$field] ?? (string) $field;
    }
    private function applyClosure(Opportunity $o, CrmPipelineStage $target, array $context): void
    {
        if ($target->stage_type === CrmStageType::Lost) {
            $reason = $context['lost_reason'] ?? $o->lost_reason;
            if (!$reason)
                $this->invalid('lost_reason', __('crm.errors.lost_reason'));
            $o->lost_reason = $reason;
            $o->closure_notes = $context['closure_notes'] ?? $o->closure_notes;
        }
        if ($target->stage_type === CrmStageType::Won) {
            $o->closure_notes = $context['closure_notes'] ?? $o->closure_notes;
        }
    }
    private function authorize(User $u, string $ability, Opportunity $o): void
    {
        if (!$u->can($ability, $o))
            throw new AuthorizationException;
    }
    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
