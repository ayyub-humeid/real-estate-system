<?php

namespace Tests\Feature;

use App\Enums\{CrmInquirySource, OpportunityType};
use App\Filament\Pages\CrmKanban;
use App\Filament\Resources\LeadResource\Pages\CreateLead;
use App\Filament\Resources\LeadResource\Pages\ListLeads;
use App\Filament\Resources\OpportunityResource\Pages\ListOpportunities;
use App\Filament\Resources\LeadResource\Pages\ViewLead;
use App\Filament\Resources\CrmPipelineResource\Pages\ViewCrmPipeline;
use App\Filament\Resources\OpportunityResource\Pages\ViewOpportunity;
use App\Filament\Resources\OpportunityResource\Pages\CreateOpportunity;
use App\Models\{Company, CrmPipeline, Lead, Opportunity, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CrmFilamentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach (['view_any_lead', 'view_lead', 'create_lead', 'view_any_opportunity', 'view_opportunity', 'create_opportunity', 'move_opportunity_stage', 'close_won_opportunity', 'close_lost_opportunity', 'view_any_crm::pipeline', 'view_crm::pipeline', 'view_any_lead_inquiry', 'view_lead_inquiry', 'view_any_crm_pipeline_stage', 'view_crm_pipeline_stage', 'view_any_opportunity_requirement_completion', 'view_opportunity_requirement_completion', 'view_any_opportunity_stage_history', 'view_opportunity_stage_history'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        return $user;
    }

    public function test_crm_resource_forms_and_localized_kanban_render_the_real_user_workflow(): void
    {
        $company = Company::create(['name' => 'Madar', 'email' => 'madar@example.test']);
        $actor = $this->actor($company);
        $this->actingAs($actor);

        Livewire::test(CreateLead::class)
            ->assertFormFieldExists('name')->assertFormFieldExists('phone')->assertFormFieldExists('email')
            ->fillForm(['name' => 'Mariam', 'phone' => '0591111111', 'source' => CrmInquirySource::Manual->value])
            ->call('create')->assertHasNoFormErrors();

        $lead = Lead::withoutGlobalScopes()->where('name', 'Mariam')->firstOrFail();

        // A deliberate duplicate entered through the real Create page must
        // render a field-level error instead of silently deduplicating it.
        Livewire::test(CreateLead::class)
            ->fillForm([
                'name' => 'Mariam Again',
                'phone' => '+970 591 111 111',
                'source' => CrmInquirySource::Manual->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['phone']);

        // The Leads index must render enum-backed status badges. Filament
        // provides table values as `$state`, rather than container-resolving
        // arbitrary callback argument names.
        Livewire::test(ListLeads::class)
            ->assertSee('Mariam');

        $pipeline = CrmPipeline::withoutGlobalScopes()->where('company_id', $company->id)->firstOrFail();
        $stage = $pipeline->stages()->orderBy('position')->firstOrFail();

        Livewire::test(CreateOpportunity::class)
            ->assertFormFieldExists('lead_id')->assertFormFieldExists('pipeline_id')->assertFormFieldExists('pipeline_stage_id')
            ->fillForm(['lead_id' => $lead->id, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'type' => OpportunityType::Sale->value, 'source' => CrmInquirySource::Manual->value])
            ->call('create')->assertHasNoFormErrors();

        $opportunity = Opportunity::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($stage->id, $opportunity->pipeline_stage_id);

        // Browser-shaped regression: relation tabs must be usable from View,
        // not only from Edit pages where a user happens to discover them.
        Livewire::test(ViewCrmPipeline::class, ['record' => $pipeline->getRouteKey()])
            ->assertSee(__('crm.navigation.stages'));
        Livewire::test(ViewLead::class, ['record' => $lead->getRouteKey()])
            ->assertSee(__('crm.fields.inquiries'))
            ->assertSee(__('crm.navigation.opportunities'));
        Livewire::test(ViewOpportunity::class, ['record' => $opportunity->getRouteKey()])
            // A staff member sees the current Stage's conditions before
            // attempting Kanban movement, rather than learning them only
            // from a blocked-transition notification.
            ->assertSee(__('crm.navigation.requirements'))
            ->assertSee(__('crm.defaults.requirements.contact_information'))
            ->assertSee(__('crm.workflow_overview.title'))
            // The workflow overview keeps requirements from the other
            // Pipeline Stages visible; adding a later Stage condition must
            // never make earlier conditions appear to have disappeared.
            ->assertSee(__('crm.defaults.requirements.contact_result'))
            ->assertSee(__('crm.requirement_status.complete'))
            ->assertSee(__('crm.navigation.requirement_completions'))
            ->assertSee(__('crm.navigation.stage_history'));

        app()->setLocale('ar');
        Livewire::test(ListLeads::class)
            ->assertSee('يدوي')
            ->assertSee('نشط');
        Livewire::test(ListOpportunities::class)
            ->assertSee('بيع')
            ->assertSee('يدوي')
            ->assertSee('جديد');
        // Relation-manager tables are lazy-loaded by Filament, so their
        // header-action markup is not part of the View page's first Livewire
        // response. Assert the translated labels here; the View-page relation
        // tabs themselves are asserted above.
        $this->assertSame('إنشاء استفسار', __('crm.actions.create_inquiry'));
        $this->assertSame('إنشاء فرصة', __('crm.actions.create_opportunity'));
        Livewire::test(CrmKanban::class)
            ->assertSee('لوحة الفرص')
            ->assertSee('اسحب البطاقة إلى مرحلة أخرى')
            ->assertSee('dir="rtl"', false);
        app()->setLocale('en');

        // Closing an Opportunity is not a cryptic blocked movement: the
        // Kanban opens a guided terminal form in the same flow and submits
        // the closing information to the protected workflow.
        $won = $pipeline->stages()->where('code', 'won')->firstOrFail();
        Livewire::test(CrmKanban::class)
            ->call('moveOpportunity', $opportunity->id, (string) $won->id)
            ->assertActionMounted('terminalMove')
            ->set('mountedActionsData.0.closure_notes', 'Price and handover date agreed.')
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $this->assertSame($won->id, $opportunity->fresh()->pipeline_stage_id);
    }
}
