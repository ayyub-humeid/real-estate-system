<?php

namespace Tests\Feature;

use App\Enums\{CrmRequirementType, CrmStageType};
use App\Models\{Company, CrmPipeline, User};
use App\Services\{CrmPipelineService, CrmPipelineStageService, LeadIntakeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CrmConfigurationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company, array $permissions): User
    {
        $actor = User::factory()->create(['company_id' => $company->id]);

        foreach ($permissions as $permission) {
            $actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $actor;
    }

    public function test_existing_manual_pipeline_becomes_default_when_a_lead_is_created_without_creating_a_duplicate(): void
    {
        $company = Company::create(['name' => 'Madar', 'email' => 'madar@example.test']);
        $actor = $this->actor($company, ['create_lead']);
        $pipeline = CrmPipeline::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => __('crm.defaults.pipeline'),
            'is_default' => false,
            'is_active' => true,
        ]);

        app(LeadIntakeService::class)->createLead($actor, [
            'name' => 'Mariam',
            'phone' => '0591111111',
        ]);

        $this->assertSame(1, CrmPipeline::withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertTrue($pipeline->refresh()->is_default);
    }

    public function test_a_pipeline_marked_default_is_immediately_ready_for_kanban_without_overwriting_existing_stages(): void
    {
        $company = Company::create(['name' => 'Madar', 'email' => 'madar@example.test']);
        $actor = $this->actor($company, ['create_crm::pipeline']);

        $pipeline = app(CrmPipelineService::class)->create($actor, [
            'name' => 'Sales Pipeline',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertTrue($pipeline->is_default);
        $this->assertCount(7, $pipeline->stages);
        $this->assertSame(['new', 'contacted', 'qualified', 'viewing', 'negotiation', 'won', 'lost'],
            $pipeline->stages->pluck('code')->all());

        // Provisioning is safe to call again: it neither duplicates nor
        // overwrites the company's configured Stage records.
        app(\App\Services\CrmPipelineProvisioningService::class)->ensureDefaultStages($pipeline);
        $this->assertSame(7, $pipeline->fresh()->stages()->count());
    }

    public function test_pipeline_name_and_stage_code_or_position_duplicates_return_validation_errors_before_database_errors(): void
    {
        $company = Company::create(['name' => 'Madar', 'email' => 'madar@example.test']);
        $actor = $this->actor($company, [
            'create_crm::pipeline',
            'create_crm_pipeline_stage',
            'update_crm_pipeline_stage',
            'create_crm_stage_requirement',
        ]);

        $pipeline = app(CrmPipelineService::class)->create($actor, [
            'name' => 'Sales Pipeline',
            'is_default' => false,
            'is_active' => true,
        ]);

        try {
            app(CrmPipelineService::class)->create($actor, ['name' => 'sales pipeline']);
            $this->fail('A duplicate pipeline name reached the database layer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $service = app(CrmPipelineStageService::class);
        $service->create($actor, $pipeline, [
            'code' => 'qualified',
            'label' => 'Qualified',
            'stage_type' => CrmStageType::Open->value,
            'position' => 2,
            'is_active' => true,
        ]);

        try {
            $service->create($actor, $pipeline, [
                'code' => 'viewing',
                'label' => 'Viewing',
                'stage_type' => CrmStageType::Open->value,
                'position' => 2,
                'is_active' => true,
            ]);
            $this->fail('A duplicate stage position reached the database layer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('position', $exception->errors());
        }

        try {
            $service->create($actor, $pipeline, [
                'code' => 'QUALIFIED',
                'label' => 'Qualified duplicate',
                'stage_type' => CrmStageType::Open->value,
                'position' => 3,
                'is_active' => true,
            ]);
            $this->fail('A duplicate stage code reached the database layer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('code', $exception->errors());
        }
    }

    public function test_stage_requirement_sync_accepts_both_enum_and_string_form_states(): void
    {
        $company = Company::create(['name' => 'Madar', 'email' => 'madar@example.test']);
        $actor = $this->actor($company, [
            'create_crm::pipeline',
            'create_crm_pipeline_stage',
            'update_crm_pipeline_stage',
            'create_crm_stage_requirement',
        ]);
        $pipeline = app(CrmPipelineService::class)->create($actor, [
            'name' => 'Sales Pipeline',
            'is_default' => false,
            'is_active' => true,
        ]);
        $stage = app(CrmPipelineStageService::class)->create($actor, $pipeline, [
            'code' => 'qualified',
            'label' => 'Qualified',
            'stage_type' => CrmStageType::Open->value,
            'position' => 1,
            'is_active' => true,
        ]);

        $service = app(CrmPipelineStageService::class);
        $service->syncRequirements($actor, $stage, [[
            'key' => 'budget_confirmed',
            'label' => 'Budget confirmed',
            'requirement_type' => CrmRequirementType::ChecklistCompleted,
            'is_required' => true,
            'position' => 1,
            'is_active' => true,
        ]]);

        $this->assertDatabaseHas('crm_stage_requirements', [
            'pipeline_stage_id' => $stage->id,
            'key' => 'budget_confirmed',
            'requirement_type' => CrmRequirementType::ChecklistCompleted->value,
        ]);
    }

    public function test_requirement_configuration_uses_business_fields_and_generates_the_internal_key_and_position(): void
    {
        $company = Company::create(['name' => 'Madar', 'email' => 'madar@example.test']);
        $actor = $this->actor($company, [
            'create_crm::pipeline',
            'update_crm_pipeline_stage',
            'create_crm_stage_requirement',
            'update_crm_stage_requirement',
        ]);
        $pipeline = app(CrmPipelineService::class)->create($actor, [
            'name' => 'Sales Pipeline',
            'is_default' => true,
            'is_active' => true,
        ]);
        $stage = $pipeline->stages()->where('code', 'qualified')->firstOrFail();

        app(CrmPipelineStageService::class)->syncRequirements($actor, $stage, [[
            'label' => 'Budget must be confirmed',
            'requirement_type' => CrmRequirementType::FieldRequired->value,
            'field' => 'budget_max',
            'is_required' => true,
            'is_active' => true,
        ], [
            'label' => 'Manager approval recorded',
            'requirement_type' => CrmRequirementType::ChecklistCompleted->value,
            'is_required' => true,
            'is_active' => true,
        ]]);

        $requirements = $stage->requirements()->where('is_active', true)->orderBy('position')->get();
        $this->assertSame('budget_must_be_confirmed', $requirements[0]->key);
        $this->assertSame(['field' => 'budget_max'], $requirements[0]->config_json);
        $this->assertSame(0, $requirements[0]->position);
        $this->assertSame(1, $requirements[1]->position);
        $this->assertNull($requirements[1]->config_json);
    }
}
