<?php

namespace App\Services;

use App\Enums\{CrmRequirementType, CrmStageType};
use App\Models\{Company, CrmPipeline, CrmPipelineStage, CrmStageRequirement};
use Illuminate\Support\Facades\DB;

class CrmPipelineProvisioningService
{
    public function ensureDefault(Company $company): CrmPipeline
    {
        return DB::transaction(function () use ($company): CrmPipeline {
            Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($company->id);

            $default = CrmPipeline::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('is_default', true)
                ->lockForUpdate()
                ->first();

            if ($default) {
                $this->ensureDefaultStages($default);

                return $default;
            }

            // A company may have created its first Pipeline manually. Promote
            // it rather than creating a second translated “Sales Pipeline” and
            // reaching crm_pipelines.company_id/name's unique constraint.
            $existing = CrmPipeline::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->orderByDesc('is_active')
                ->orderBy('id')
                ->first();

            if ($existing) {
                $existing->update(['is_default' => true]);
                $this->ensureDefaultStages($existing);

                return $existing->refresh();
            }

            $pipeline = CrmPipeline::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'name' => __('crm.defaults.pipeline'),
                'is_default' => true,
                'is_active' => true,
            ]);

            $this->ensureDefaultStages($pipeline);

            return $pipeline;
        });
    }

    /**
     * Makes a newly selected default Pipeline useful immediately. This is
     * deliberately idempotent: an administrator's configured Pipeline is
     * never overwritten or supplemented once it already has a Stage.
     */
    public function ensureDefaultStages(CrmPipeline $pipeline): CrmPipeline
    {
        return DB::transaction(function () use ($pipeline): CrmPipeline {
            $lockedPipeline = CrmPipeline::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($pipeline->id);

            if ($lockedPipeline->stages()->exists()) {
                return $lockedPipeline;
            }

            foreach ($this->defaultStages() as $position => [$code, $label, $stageType]) {
                $stage = CrmPipelineStage::withoutGlobalScopes()->create([
                    'company_id' => $lockedPipeline->company_id,
                    'pipeline_id' => $lockedPipeline->id,
                    'code' => $code,
                    'label' => $label,
                    'stage_type' => $stageType->value,
                    'position' => $position,
                    'is_active' => true,
                ]);

                foreach ($this->requirements($code) as $requirementPosition => $requirement) {
                    CrmStageRequirement::withoutGlobalScopes()->create([
                        'company_id' => $lockedPipeline->company_id,
                        'pipeline_stage_id' => $stage->id,
                        'key' => $requirement['key'],
                        'label' => __($requirement['label']),
                        'requirement_type' => $requirement['type']->value,
                        'config_json' => $requirement['config'],
                        'is_required' => true,
                        'position' => $requirementPosition,
                        'is_active' => true,
                    ]);
                }
            }

            return $lockedPipeline->refresh();
        });
    }

    /** @return array<int, array{0: string, 1: string, 2: CrmStageType}> */
    private function defaultStages(): array
    {
        return [
            ['new', __('crm.defaults.stages.new'), CrmStageType::Open],
            ['contacted', __('crm.defaults.stages.contacted'), CrmStageType::Open],
            ['qualified', __('crm.defaults.stages.qualified'), CrmStageType::Open],
            ['viewing', __('crm.defaults.stages.viewing'), CrmStageType::Open],
            ['negotiation', __('crm.defaults.stages.negotiation'), CrmStageType::Open],
            ['won', __('crm.defaults.stages.won'), CrmStageType::Won],
            ['lost', __('crm.defaults.stages.lost'), CrmStageType::Lost],
        ];
    }

    /** @return array<int, array{key: string, label: string, type: CrmRequirementType, config: array<string, string>}> */
    private function requirements(string $code): array
    {
        return match ($code) {
            'new' => [[
                'key' => 'contact_information',
                'label' => 'crm.defaults.requirements.contact_information',
                'type' => CrmRequirementType::FieldRequired,
                'config' => ['field' => 'lead.normalized_phone_or_email'],
            ]],
            'contacted' => [[
                'key' => 'contact_result',
                'label' => 'crm.defaults.requirements.contact_result',
                'type' => CrmRequirementType::RelatedRecordExists,
                'config' => ['relation' => 'inquiries', 'field' => 'contact_result', 'operator' => 'not_empty'],
            ]],
            'qualified' => [[
                'key' => 'budget_confirmed',
                'label' => 'crm.defaults.requirements.budget_confirmed',
                'type' => CrmRequirementType::FieldRequired,
                'config' => ['field' => 'budget_max'],
            ]],
            'won' => [[
                'key' => 'closure_notes',
                'label' => 'crm.defaults.requirements.closure_notes',
                'type' => CrmRequirementType::FieldRequired,
                'config' => ['field' => 'closure_notes'],
            ]],
            'lost' => [[
                'key' => 'lost_reason',
                'label' => 'crm.defaults.requirements.lost_reason',
                'type' => CrmRequirementType::FieldRequired,
                'config' => ['field' => 'lost_reason'],
            ]],
            default => [],
        };
    }
}
