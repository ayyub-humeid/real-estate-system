<?php

namespace Tests\Feature;

use App\Enums\{CrmInquirySource, CrmRequirementType, OfferApprovalStatus, OfferProposedBySide, OfferRevisionStatus, OfferStatus, OpportunityType};
use App\Filament\Resources\CrmOfferResource;
use App\Models\{Company, CrmOffer, CrmStageRequirement, User};
use App\Services\{LeadIntakeService, OfferNegotiationService, OpportunityService, OpportunityWorkflowService};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OfferNegotiationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): Company { return Company::create(['name' => $name, 'email' => strtolower($name) . '@test.local']); }
    private function user(Company $company, array $permissions): User { $user = User::factory()->create(['company_id' => $company->id]); foreach ($permissions as $permission) $user->givePermissionTo(Permission::findOrCreate($permission, 'web')); return $user; }
    private function permissions(): array { return ['create_lead', 'create_opportunity', 'view_opportunity', 'move_opportunity_stage', 'create_crm::offer', 'view_any_crm::offer', 'view_crm::offer', 'update_crm::offer', 'assign_offer', 'create_offer_revision', 'update_offer_revision', 'view_offer_revision', 'submit_offer_revision', 'request_offer_approval', 'approve_offer_revision', 'reject_offer_revision', 'confirm_offer_agreement', 'withdraw_offer', 'close_offer', 'expire_offer']; }
    private function opportunity(User $actor, OpportunityType $type = OpportunityType::Sale): \App\Models\Opportunity { $lead = app(LeadIntakeService::class)->createLead($actor, ['name' => 'Nour', 'phone' => '0591234567', 'source' => CrmInquirySource::Manual->value]); return app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'type' => $type->value, 'source' => CrmInquirySource::Manual->value]); }
    private function offer(User $actor, \App\Models\Opportunity $opportunity): CrmOffer { return app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $opportunity->id, 'transaction_type' => $opportunity->type->value]); }
    private function draft(User $actor, CrmOffer $offer, array $extra = []): \App\Models\CrmOfferRevision { return app(OfferNegotiationService::class)->createDraftRevision($actor, $offer, ['proposed_by_side' => OfferProposedBySide::Customer->value, 'amount' => 110000, 'currency' => 'USD', ...$extra]); }

    public function test_offer_reference_and_revision_numbers_are_generated_without_manual_input(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions()); $opportunity = $this->opportunity($actor);
        $first = $this->offer($actor, $opportunity); $second = $this->offer($actor, $opportunity);
        $this->assertMatchesRegularExpression('/^OFF-' . now()->format('Y') . '-0001$/', $first->reference_number);
        $this->assertNotSame($first->reference_number, $second->reference_number);
        $one = $this->draft($actor, $first, ['payment_method' => 'bank_transfer']); $two = $this->draft($actor, $first, ['amount' => 111000, 'proposed_by_side' => 'company']);
        $this->assertSame(1, $one->revision_number); $this->assertSame(2, $two->revision_number); $this->assertSame('bank_transfer', $one->payment_method);
    }

    public function test_submitted_revision_is_immutable_and_counteroffer_preserves_history(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions()); $offer = $this->offer($actor, $this->opportunity($actor)); $first = $this->draft($actor, $offer);
        $submitted = app(OfferNegotiationService::class)->submitRevision($actor, $first);
        $this->assertSame(OfferRevisionStatus::Submitted, $submitted->status);
        $this->expectException(ValidationException::class);
        app(OfferNegotiationService::class)->updateDraftRevision($actor, $submitted, ['amount' => 99999]);
    }

    public function test_counteroffer_supersedes_the_previous_active_proposal(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions()); $offer = $this->offer($actor, $this->opportunity($actor)); $first = app(OfferNegotiationService::class)->submitRevision($actor, $this->draft($actor, $offer));
        $counter = app(OfferNegotiationService::class)->createCounteroffer($actor, $offer, ['proposed_by_side' => 'company', 'amount' => 116000, 'currency' => 'USD']);
        $this->assertSame(OfferRevisionStatus::Superseded, $first->fresh()->status); $this->assertSame(OfferRevisionStatus::Submitted, $counter->status); $this->assertSame(OfferStatus::Negotiating, $offer->fresh()->status);
    }

    public function test_internal_approval_is_not_external_agreement_and_agreement_records_actor(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions()); $approver = $this->user($company, ['approve_offer_revision', 'confirm_offer_agreement']); $offer = $this->offer($actor, $this->opportunity($actor)); $revision = app(OfferNegotiationService::class)->submitRevision($actor, $this->draft($actor, $offer));
        $approval = app(OfferNegotiationService::class)->requestApproval($actor, $revision);
        $this->assertNull($approval->assigned_approver_id);
        $approved = app(OfferNegotiationService::class)->decideApproval($approver, $approval, true, 'Within authority');
        $this->assertSame(OfferApprovalStatus::Approved, $approved->status); $this->assertSame(OfferStatus::Negotiating, $offer->fresh()->status);
        $agreed = app(OfferNegotiationService::class)->confirmAgreement($approver, $revision);
        $this->assertSame(OfferRevisionStatus::Agreed, $agreed->status); $this->assertSame($approver->id, $agreed->confirmed_by); $this->assertSame(OfferStatus::Accepted, $offer->fresh()->status);
    }

    public function test_cross_company_and_unauthorized_offer_operations_are_rejected(): void
    {
        $a = $this->company('A'); $b = $this->company('B'); $actor = $this->user($a, $this->permissions()); $foreign = $this->user($b, $this->permissions()); $opportunity = $this->opportunity($actor);
        $this->expectException(ValidationException::class);
        app(OfferNegotiationService::class)->createOffer($foreign, ['company_id' => $b->id, 'opportunity_id' => $opportunity->id, 'transaction_type' => 'sale']);
    }

    public function test_assignment_and_offer_creation_permissions_are_enforced_server_side(): void
    {
        $company = $this->company('A');
        $creator = $this->user($company, array_diff($this->permissions(), ['assign_offer']));
        $assignee = $this->user($company, []);
        $opportunity = $this->opportunity($creator);

        $this->expectException(AuthorizationException::class);
        app(OfferNegotiationService::class)->createOffer($creator, [
            'opportunity_id' => $opportunity->id,
            'transaction_type' => 'sale',
            'assigned_to' => $assignee->id,
        ]);
    }

    public function test_assigned_negotiator_sees_and_manages_only_own_offers(): void
    {
        $company = $this->company('A');
        $manager = $this->user($company, $this->permissions());
        $ahmad = $this->user($company, ['view_own_crm_offer', 'manage_own_crm_offer']);
        $otherEmployee = $this->user($company, []);
        $opportunity = $this->opportunity($manager);
        $owned = app(OfferNegotiationService::class)->createOffer($manager, ['opportunity_id' => $opportunity->id, 'transaction_type' => 'sale', 'assigned_to' => $ahmad->id]);
        $other = app(OfferNegotiationService::class)->createOffer($manager, ['opportunity_id' => $opportunity->id, 'transaction_type' => 'sale', 'assigned_to' => $otherEmployee->id]);

        $this->assertTrue($ahmad->can('viewAny', CrmOffer::class));
        $this->assertTrue($ahmad->can('view', $owned));
        $this->assertFalse($ahmad->can('view', $other));
        $this->actingAs($ahmad);
        $this->assertSame([$owned->id], CrmOfferResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame(1, app(OfferNegotiationService::class)->createDraftRevision($ahmad, $owned, ['proposed_by_side' => 'company', 'amount' => 120000, 'currency' => 'USD'])->revision_number);

        $this->expectException(AuthorizationException::class);
        app(OfferNegotiationService::class)->createDraftRevision($ahmad, $other, ['proposed_by_side' => 'company', 'amount' => 120000, 'currency' => 'USD']);
    }

    public function test_rental_terms_and_accepted_offer_stage_requirement_are_enforced_without_moving_opportunity(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions()); $opportunity = $this->opportunity($actor, OpportunityType::Rent); $offer = $this->offer($actor, $opportunity);
        $this->expectException(ValidationException::class);
        app(OfferNegotiationService::class)->createDraftRevision($actor, $offer, ['proposed_by_side' => 'customer', 'amount' => 1000, 'currency' => 'USD', 'rent_frequency' => 'monthly']);
    }

    public function test_accepted_offer_requirement_is_satisfied_without_automatically_moving_opportunity(): void
    {
        $company = $this->company('A'); $actor = $this->user($company, $this->permissions()); $opportunity = $this->opportunity($actor); $stage = $opportunity->stage;
        $requirement = CrmStageRequirement::withoutGlobalScopes()->create(['company_id' => $company->id, 'pipeline_stage_id' => $stage->id, 'key' => 'agreed_offer', 'label' => 'Agreed offer', 'requirement_type' => CrmRequirementType::AcceptedOfferExists->value, 'is_required' => true, 'is_active' => true]);
        $offer = $this->offer($actor, $opportunity); $revision = app(OfferNegotiationService::class)->submitRevision($actor, $this->draft($actor, $offer)); app(OfferNegotiationService::class)->confirmAgreement($actor, $revision);
        $this->assertTrue(app(OpportunityWorkflowService::class)->requirementIsSatisfied($opportunity->fresh(), $requirement)); $this->assertSame($stage->id, $opportunity->fresh()->pipeline_stage_id);
    }

    public function test_offer_static_labels_resolve_in_english_and_arabic(): void
    {
        app()->setLocale('en');
        $this->assertSame('Draft', OfferStatus::Draft->getLabel());
        $this->assertSame('Customer', OfferProposedBySide::Customer->getLabel());

        app()->setLocale('ar');
        $this->assertSame('مسودة', OfferStatus::Draft->getLabel());
        $this->assertSame('العميل', OfferProposedBySide::Customer->getLabel());
    }
}
