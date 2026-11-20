<?php

namespace Tests\Feature;

use App\Enums\{CrmInquirySource, OpportunityType};
use App\Filament\Resources\LeadResource\Pages\EditLead;
use App\Filament\Resources\OpportunityResource\Pages\{CreateOpportunity, EditOpportunity};
use App\Models\{Company, CrmPipeline, CrmPipelineStage, Lead, Opportunity, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Every Create/Edit page in the sales pipeline must turn a service failure
 * into a notification, never an uncaught exception. Several pages were
 * missing this (zero-silent-failures, AGENTS.md); these tests lock the fix in.
 */
class WorkflowActionFailureHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    public function test_creating_an_opportunity_with_a_cross_company_lead_shows_a_validation_error_not_a_crash(): void
    {
        $company = Company::create(['name' => 'Opportunity Co', 'email' => 'opp@example.test']);
        $otherCompany = Company::create(['name' => 'Other Co', 'email' => 'otheropp@example.test']);
        $actor = $this->actor($company, ['create_opportunity', 'view_any_opportunity', 'view_opportunity']);
        $foreignLead = Lead::withoutGlobalScopes()->create(['company_id' => $otherCompany->id, 'name' => 'Foreign Lead', 'phone' => '0590000001', 'status' => 'active', 'first_source' => 'manual']);
        $pipeline = CrmPipeline::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Default', 'is_active' => true, 'is_default' => true]);
        $stage = CrmPipelineStage::withoutGlobalScopes()->create(['company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'code' => 'new', 'label' => 'New', 'position' => 0, 'is_active' => true, 'stage_type' => 'open']);
        $this->actingAs($actor);

        Livewire::test(CreateOpportunity::class)
            ->fillForm([
                'lead_id' => $foreignLead->id,
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $stage->id,
                'type' => OpportunityType::Sale->value,
                'source' => CrmInquirySource::Manual->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['lead_id']);

        $this->assertSame(0, Opportunity::withoutGlobalScopes()->count());
    }

    public function test_updating_an_opportunity_to_a_cross_company_listing_shows_a_validation_error_not_a_crash(): void
    {
        $company = Company::create(['name' => 'Edit Opportunity Co', 'email' => 'editopp@example.test']);
        $otherCompany = Company::create(['name' => 'Other Opp Co', 'email' => 'otheropp2@example.test']);
        $actor = $this->actor($company, ['create_opportunity', 'update_opportunity', 'view_any_opportunity', 'view_opportunity']);
        $lead = Lead::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Local Lead', 'phone' => '0590000002', 'status' => 'active', 'first_source' => 'manual']);
        $pipeline = CrmPipeline::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Default', 'is_active' => true, 'is_default' => true]);
        $stage = CrmPipelineStage::withoutGlobalScopes()->create(['company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'code' => 'new', 'label' => 'New', 'position' => 0, 'is_active' => true, 'stage_type' => 'open']);
        $opportunity = Opportunity::withoutGlobalScopes()->create(['company_id' => $company->id, 'lead_id' => $lead->id, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'type' => OpportunityType::Sale->value, 'source' => CrmInquirySource::Manual->value]);
        $foreignUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $this->actingAs($actor);

        // assigned_to is validated cross-company inside OpportunityService::update();
        // the field itself is not restricted to same-company options client-side,
        // so a stale/forged id is exactly what should surface as a form error.
        Livewire::test(EditOpportunity::class, ['record' => $opportunity->getRouteKey()])
            ->fillForm(['assigned_to' => $foreignUser->id])
            ->call('save')
            ->assertHasFormErrors(['assigned_to']);
    }

    public function test_lead_intake_service_refuses_creation_without_permission(): void
    {
        // CreateLead::handleRecordCreation() now catches this AuthorizationException
        // and shows a notification instead of letting it surface as a raw crash;
        // this locks in the service-level guarantee that makes that catch reachable.
        $company = Company::create(['name' => 'No Create Lead Co', 'email' => 'nolead@example.test']);
        $actor = $this->actor($company, ['view_any_lead']);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(\App\Services\LeadIntakeService::class)->createLead($actor, ['name' => 'Nope', 'phone' => '0590000099']);
    }

    public function test_editing_a_lead_with_a_duplicate_phone_shows_a_validation_error_not_a_crash(): void
    {
        $company = Company::create(['name' => 'Edit Lead Co', 'email' => 'editlead@example.test']);
        $actor = $this->actor($company, ['create_lead', 'update_lead', 'view_any_lead', 'view_lead']);
        $existing = Lead::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Existing', 'phone' => '0590000003', 'normalized_phone' => \App\Support\ContactNormalizer::phone('0590000003'), 'status' => 'active', 'first_source' => 'manual']);
        $editable = Lead::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Editable', 'phone' => '0590000004', 'normalized_phone' => \App\Support\ContactNormalizer::phone('0590000004'), 'status' => 'active', 'first_source' => 'manual']);
        $this->actingAs($actor);

        // The "source" field is a known pre-existing gap: it is never pre-filled
        // from the record (it maps to a plain `source` attribute the Lead model
        // does not have, only `first_source`), so it must be supplied here or
        // Filament's own required-field validation blocks the save first.
        Livewire::test(EditLead::class, ['record' => $editable->getRouteKey()])
            ->fillForm(['phone' => '0590000003', 'source' => CrmInquirySource::Manual->value])
            ->call('save')
            ->assertHasFormErrors(['phone']);

        $this->assertSame('0590000004', $editable->fresh()->phone);
    }
}
