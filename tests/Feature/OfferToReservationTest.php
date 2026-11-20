<?php

namespace Tests\Feature;

use App\Enums\{CrmInquirySource, OfferProposedBySide, OfferRevisionStatus, OfferStatus, OpportunityType, ReservationSource, ReservationStatus, UnitStatus};
use App\Models\{Company, CrmOffer, Lead, Listing, Party, Unit, User};
use App\Services\{LeadIntakeService, OfferNegotiationService, OpportunityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OfferToReservationTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): Company
    {
        return Company::create(['name' => $name, 'email' => strtolower($name) . '@test.local']);
    }
    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        return $user;
    }
    private function permissions(): array
    {
        return ['create_lead', 'create_opportunity', 'view_opportunity', 'create_crm::offer', 'view_any_crm::offer', 'view_crm::offer', 'update_crm::offer', 'create_offer_revision', 'update_offer_revision', 'view_offer_revision', 'finalize_offer_revision', 'confirm_offer_agreement', 'accept_offer', 'convert_offer_to_reservation', 'create_unit::reservation', 'view_any_unit::reservation', 'view_unit::reservation'];
    }

    private function acceptedOffer(User $actor, Company $company, array $overrides = []): array
    {
        $lead = app(LeadIntakeService::class)->createLead($actor, array_merge(['name' => 'Nour', 'phone' => '0591234567', 'source' => CrmInquirySource::Manual->value], $overrides['lead'] ?? []));
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'type' => OpportunityType::Sale->value, 'source' => CrmInquirySource::Manual->value]);
        $offer = app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $opportunity->id, 'transaction_type' => OpportunityType::Sale->value]);
        $revision = app(OfferNegotiationService::class)->createDraftRevision($actor, $offer, ['proposed_by_side' => OfferProposedBySide::Customer->value, 'amount' => 110000, 'currency' => 'USD']);
        $submitted = app(OfferNegotiationService::class)->submitRevision($actor, $revision);
        app(OfferNegotiationService::class)->confirmAgreement($actor, $submitted);

        return ['company' => $company, 'lead' => $lead, 'opportunity' => $opportunity, 'offer' => $offer->fresh(), 'revision' => $submitted];
    }

    public function test_accept_offer_transitions_status_to_accepted(): void
    {
        $company = $this->company('A');
        $actor = $this->user($company, $this->permissions());
        $data = $this->acceptedOffer($actor, $company);

        $this->assertSame(OfferStatus::Accepted, $data['offer']->status);
        $this->assertSame(OfferRevisionStatus::Agreed, $data['revision']->fresh()->status);
    }

    public function test_user_without_accept_permission_cannot_accept(): void
    {
        $company = $this->company('A');
        $actor = $this->user($company, array_diff($this->permissions(), ['accept_offer', 'confirm_offer_agreement']));
        $lead = app(LeadIntakeService::class)->createLead($actor, ['name' => 'Nour', 'phone' => '0591234567']);
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'type' => OpportunityType::Sale->value]);
        $offer = app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $opportunity->id, 'transaction_type' => OpportunityType::Sale->value]);
        $revision = app(OfferNegotiationService::class)->createDraftRevision($actor, $offer, ['proposed_by_side' => OfferProposedBySide::Customer->value, 'amount' => 110000, 'currency' => 'USD']);
        $submitted = app(OfferNegotiationService::class)->submitRevision($actor, $revision);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(OfferNegotiationService::class)->confirmAgreement($actor, $submitted);
    }

    public function test_cross_company_offer_acceptance_is_rejected(): void
    {
        $a = $this->company('A');
        $b = $this->company('B');
        $actor = $this->user($a, $this->permissions());
        $foreignActor = $this->user($b, $this->permissions());
        $data = $this->acceptedOffer($actor, $a);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(OfferNegotiationService::class)->confirmAgreement($foreignActor, $data['revision']);
    }

    // The old URL-prefilled reservation form was replaced by the offer's one-step
    // conversion; see OfferToReservationOneStepTest.

    public function test_convert_offer_to_reservation_requires_permission(): void
    {
        $company = $this->company('A');
        $actor = $this->user($company, array_diff($this->permissions(), ['convert_offer_to_reservation']));
        $lead = app(LeadIntakeService::class)->createLead($actor, ['name' => 'Nour', 'phone' => '0591234567']);
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'type' => OpportunityType::Sale->value]);
        $offer = app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $opportunity->id, 'transaction_type' => OpportunityType::Sale->value]);

        $this->assertFalse($actor->can('convert_offer_to_reservation', $offer));
    }
}
