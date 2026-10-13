<?php

namespace App\Services;

use App\Enums\{CrmRequirementType, CrmStageType};
use App\Models\{CrmPipeline, CrmPipelineStage, CrmStageRequirement, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owns the mutable CRM-pipeline configuration.
 *
 * The database unique indexes remain the final integrity guard, but this
 * service locks the parent aggregate and returns field-level validation before
 * a staff member ever sees a database constraint error.
 */
class CrmPipelineStageService
{
    public function create(User $actor, CrmPipeline $pipeline, array $data): CrmPipelineStage
    {
        $this->assertPipelineAccess($actor, $pipeline);

        if (! $actor->can('create', new CrmPipelineStage(['company_id' => $pipeline->company_id]))) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($pipeline, $data): CrmPipelineStage {
            $lockedPipeline = CrmPipeline::withoutGlobalScopes()->lockForUpdate()->findOrFail($pipeline->id);

            $attributes = $this->validatedStageAttributes($lockedPipeline, $data);

            return CrmPipelineStage::withoutGlobalScopes()->create([
                'company_id' => $lockedPipeline->company_id,
                'pipeline_id' => $lockedPipeline->id,
                ...$attributes,
            ]);
        });
    }

    public function update(User $actor, CrmPipelineStage $stage, array $data): CrmPipelineStage
    {
        $pipeline = CrmPipeline::withoutGlobalScopes()->findOrFail($stage->pipeline_id);
        $this->assertPipelineAccess($actor, $pipeline);

        if (! $actor->can('update', $stage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($pipeline, $stage, $data): CrmPipelineStage {
            CrmPipeline::withoutGlobalScopes()->lockForUpdate()->findOrFail($pipeline->id);
            $lockedStage = CrmPipelineStage::withoutGlobalScopes()->lockForUpdate()->findOrFail($stage->id);

            $attributes = $this->validatedStageAttributes($pipeline, $data, $lockedStage);

            if ($lockedStage->opportunities()->exists()
                && ($attributes['code'] !== $lockedStage->code || $attributes['stage_type'] !== $lockedStage->stage_type->value)) {
                $this->invalid('code', __('crm.errors.stage_semantics_locked'));
            }

            $lockedStage->update($attributes);

            return $lockedStage->refresh();
        });
    }

    /**
     * Syncs requirements as one aggregate update. A removed requirement is
     * deactivated, never deleted, so historical completion records remain
     * interpretable.
     */
    public function syncRequirements(User $actor, CrmPipelineStage $stage, array $rows): void
    {
        $pipeline = CrmPipeline::withoutGlobalScopes()->findOrFail($stage->pipeline_id);
        $this->assertPipelineAccess($actor, $pipeline);

        if (! $actor->can('update', $stage)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor, $stage, $rows): void {
            $lockedStage = CrmPipelineStage::withoutGlobalScopes()->lockForUpdate()->findOrFail($stage->id);
            $existing = $lockedStage->requirements()->lockForUpdate()->get()->keyBy('id');
            $reservedKeys = $existing->pluck('key')->flip()->all();
            $retainedIds = [];

            foreach (array_values($rows) as $index => $row) {
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') {
                    $this->invalid("requirements.{$index}.label", __('crm.errors.requirement_details_required'));
                }

                // A Repeater filled from an Eloquent cast can return the enum
                // instance itself, while a newly entered row returns its string
                // value. Support both shapes before validating the request.
                $rawRequirementType = $row['requirement_type'] ?? null;
                $requirementType = $rawRequirementType instanceof CrmRequirementType
                    ? $rawRequirementType
                    : CrmRequirementType::tryFrom((string) $rawRequirementType);
                if (! $requirementType) {
                    $this->invalid("requirements.{$index}.requirement_type", __('crm.errors.requirement_type_invalid'));
                }

                $id = $row['id'] ?? null;
                $requirement = $id ? $existing->get((int) $id) : null;
                if ($id && ! $requirement) {
                    $this->invalid("requirements.{$index}.id", __('crm.errors.cross_company'));
                }

                if ($requirement) {
                    if (! $actor->can('update', $requirement)) {
                        throw new AuthorizationException;
                    }
                } elseif (! $actor->can('create', new CrmStageRequirement(['company_id' => $lockedStage->company_id]))) {
                    throw new AuthorizationException;
                }

                if ($requirementType === CrmRequirementType::ChecklistCompleted
                    && $lockedStage->stage_type !== CrmStageType::Open) {
                    $this->invalid("requirements.{$index}.requirement_type", __('crm.errors.checklist_open_stage_only'));
                }

                // The UI deliberately sends friendly selections such as
                // `budget_max` or `inquiry_contact_result`; the database
                // retains the normalized evaluator configuration here.
                $config = $this->configuration($requirementType, $row, $index);
                $key = $requirement?->key
                    ?? $this->requirementKey($row['key'] ?? null, $label, $reservedKeys);
                $reservedKeys[$key] = true;

                $attributes = [
                    'company_id' => $lockedStage->company_id,
                    'key' => $key,
                    'label' => $label,
                    'requirement_type' => $requirementType->value,
                    'config_json' => $config,
                    'is_required' => (bool) ($row['is_required'] ?? false),
                    // Requirement order is a UX concern, not a value staff
                    // should have to type. Preserve current rows' ordering by
                    // their submitted sequence.
                    'position' => $index,
                    'is_active' => (bool) ($row['is_active'] ?? false),
                ];

                if ($requirement) {
                    $requirement->update($attributes);
                } else {
                    $requirement = $lockedStage->requirements()->create($attributes);
                }

                $retainedIds[] = $requirement->id;
            }

            $removed = $lockedStage->requirements();
            if ($retainedIds !== []) {
                $removed->whereNotIn('id', $retainedIds);
            }
            $removed->update(['is_active' => false]);
        });
    }

    private function validatedStageAttributes(CrmPipeline $pipeline, array $data, ?CrmPipelineStage $except = null): array
    {
        $code = Str::lower(trim((string) ($data['code'] ?? '')));
        $label = trim((string) ($data['label'] ?? ''));
        $position = filter_var($data['position'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $stageType = CrmStageType::tryFrom((string) ($data['stage_type'] ?? ''));

        if ($code === '' || $label === '') {
            $this->invalid('label', __('crm.errors.stage_details_required'));
        }
        if ($position === false) {
            $this->invalid('position', __('crm.errors.stage_position_invalid'));
        }
        if (! $stageType) {
            $this->invalid('stage_type', __('crm.errors.stage_type_invalid'));
        }

        $stages = CrmPipelineStage::withoutGlobalScopes()
            ->where('pipeline_id', $pipeline->id)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id));

        if ((clone $stages)->whereRaw('LOWER(code) = ?', [$code])->exists()) {
            $this->invalid('code', __('crm.errors.stage_code_taken'));
        }
        if ((clone $stages)->where('position', $position)->exists()) {
            $this->invalid('position', __('crm.errors.stage_position_taken'));
        }

        return [
            'code' => $code,
            'label' => $label,
            'stage_type' => $stageType->value,
            'position' => $position,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function assertPipelineAccess(User $actor, CrmPipeline $pipeline): void
    {
        if (! $actor->isSuperAdmin() && (int) $actor->company_id !== (int) $pipeline->company_id) {
            throw new AuthorizationException;
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /** @return array<string, string>|null */
    private function configuration(CrmRequirementType $type, array $row, int $index): ?array
    {
        $legacy = is_array($row['config_json'] ?? null) ? $row['config_json'] : [];

        return match ($type) {
            CrmRequirementType::ChecklistCompleted => null,
            CrmRequirementType::FieldRequired => [
                'field' => $this->field($row['field'] ?? $legacy['field'] ?? null, $index),
            ],
            CrmRequirementType::FieldEquals => [
                'field' => $this->field($row['field'] ?? $legacy['field'] ?? null, $index),
                'value' => $this->expectedValue($row['expected_value'] ?? $legacy['value'] ?? null, $index),
            ],
            CrmRequirementType::RelatedRecordExists => $this->relatedConfiguration(
                $row['related_record'] ?? $this->relatedRecordKey($legacy),
                $index,
            ),
        };
    }

    private function field(mixed $field, int $index): string
    {
        $field = (string) $field;

        if (! array_key_exists($field, self::fieldOptions())) {
            $this->invalid("requirements.{$index}.field", __('crm.errors.requirement_field_invalid'));
        }

        return $field;
    }

    private function expectedValue(mixed $value, int $index): string
    {
        if ($value === null || $value === '') {
            $this->invalid("requirements.{$index}.expected_value", __('crm.errors.requirement_value_required'));
        }

        return (string) $value;
    }

    /** @return array<string, string> */
    private function relatedConfiguration(mixed $relatedRecord, int $index): array
    {
        if ($relatedRecord !== 'inquiry_contact_result') {
            $this->invalid("requirements.{$index}.related_record", __('crm.errors.related_record_invalid'));
        }

        return ['relation' => 'inquiries', 'field' => 'contact_result', 'operator' => 'not_empty'];
    }

    private function relatedRecordKey(array $config): ?string
    {
        return ($config['relation'] ?? null) === 'inquiries'
            && ($config['field'] ?? null) === 'contact_result'
            && ($config['operator'] ?? null) === 'not_empty'
            ? 'inquiry_contact_result'
            : null;
    }

    /** @param array<string, bool> $reservedKeys */
    private function requirementKey(mixed $submittedKey, string $label, array $reservedKeys): string
    {
        // A supplied key is kept only for existing internal callers / legacy
        // data. The Filament form never exposes it to staff.
        $base = Str::lower(trim((string) $submittedKey));
        if ($base === '') {
            $base = Str::slug($label, '_');
        }
        $base = Str::limit($base !== '' ? $base : 'requirement', 72, '');

        $candidate = $base;
        $suffix = 2;
        while (isset($reservedKeys[$candidate])) {
            $candidate = Str::limit($base, 72 - strlen((string) $suffix), '') . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    /** @return array<string, string> */
    public static function fieldOptions(): array
    {
        return [
            'lead.normalized_phone_or_email' => __('crm.requirement_fields.contact_details'),
            'budget_min' => __('crm.fields.budget_min'),
            'budget_max' => __('crm.fields.budget_max'),
            'notes' => __('crm.fields.notes'),
            'closure_notes' => __('crm.fields.closure_notes'),
            'lost_reason' => __('crm.fields.lost_reason'),
        ];
    }

    /** @return array<string, string> */
    public static function relatedRecordOptions(): array
    {
        return [
            'inquiry_contact_result' => __('crm.related_records.inquiry_contact_result'),
        ];
    }
}
