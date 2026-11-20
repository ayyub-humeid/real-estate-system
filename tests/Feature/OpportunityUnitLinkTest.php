<?php

namespace Tests\Feature;

use App\Enums\{CrmInquirySource, OfferProposedBySide, OpportunityType, ReservationSource, UnitStatus};
use App\Models\{Company, Location, Opportunity, Party, Project, Property, Unit, User};
use App\Services\{LeadIntakeService, OfferNegotiationService, OpportunityService, ReservationWorkflowService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The unit a customer is interested in is picked once on the opportunity and
 * must then reach the offer and the reservation without being chosen again.
 */
class OpportunityUnitLinkTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): Company
    {
        return Company::create(['name' => $name, 'email' => strtolower($name).'@test.local']);
    }

    private function actor(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach (['create_lead', 'view_any_lead', 'view_lead', 'create_opportunity', 'view_any_opportunity', 'view_opportunity', 'update_opportunity', 'create_crm::offer', 'update_crm::offer', 'view_crm::offer',
            'create_offer_revision', 'finalize_offer_revision', 'confirm_offer_agreement', 'accept_offer', 'create_unit::reservation'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function unit(Company $company, string $number, string $status = 'ready', ?Project $project = null): Unit
    {
        $location = Location::withoutGlobalScopes()->firstOrCreate(['company_id' => $company->id, 'name' => 'City'], ['type' => 'city']);
        $property = Property::withoutGlobalScopes()->firstOrCreate(['company_id' => $company->id, 'name' => 'Tower'], ['location_id' => $location->id, 'address' => 'Address']);

        return Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'project_id' => $project?->id, 'unit_number' => $number, 'type' => 'apartment', 'status' => $status]);
    }

    private function lead(User $actor, string $phone = '0591234567')
    {
        return app(LeadIntakeService::class)->createLead($actor, ['name' => 'Nour', 'phone' => $phone, 'source' => CrmInquirySource::Manual->value]);
    }

    public function test_opportunity_stores_the_unit_and_inherits_its_project(): void
    {
        $company = $this->company('A');
        $actor = $this->actor($company);
        $project = Project::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Tower One', 'project_type' => 'residential', 'status' => 'planning']);
        $unit = $this->unit($company, 'A-101', 'ready', $project);

        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $this->lead($actor)->id, 'type' => OpportunityType::Sale->value, 'unit_id' => $unit->id]);

        $this->assertSame($unit->id, $opportunity->unit_id);
        $this->assertSame($project->id, $opportunity->project_id);
    }

    public function test_unit_must_be_ready_belong_to_the_company_and_match_the_project(): void
    {
        $a = $this->company('A');
        $b = $this->company('B');
        $actor = $this->actor($a);
        $lead = $this->lead($actor);
        $project = Project::withoutGlobalScopes()->create(['company_id' => $a->id, 'name' => 'P1', 'project_type' => 'residential', 'status' => 'planning']);
        $other = Project::withoutGlobalScopes()->create(['company_id' => $a->id, 'name' => 'P2', 'project_type' => 'residential', 'status' => 'planning']);

        $cases = [
            'foreign company unit' => ['unit_id' => $this->unit($b, 'X-1')->id],
            'sold unit' => ['unit_id' => $this->unit($a, 'S-1', UnitStatus::Sold->value)->id],
            'unit of another project' => ['unit_id' => $this->unit($a, 'P-1', 'ready', $other)->id, 'project_id' => $project->id],
        ];
        foreach ($cases as $name => $extra) {
            try {
                app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'type' => OpportunityType::Sale->value, ...$extra]);
                $this->fail("Rejected case accepted: {$name}");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('unit_id', $exception->errors(), $name);
            }
        }
    }

    public function test_editing_an_opportunity_whose_unit_was_since_sold_does_not_fail(): void
    {
        $company = $this->company('A');
        $actor = $this->actor($company);
        $unit = $this->unit($company, 'A-101');
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $this->lead($actor)->id, 'type' => OpportunityType::Sale->value, 'unit_id' => $unit->id]);
        $unit->update(['status' => UnitStatus::Sold->value]);

        $updated = app(OpportunityService::class)->update($actor, $opportunity, ['type' => OpportunityType::Sale->value, 'unit_id' => $unit->id, 'notes' => 'Follow up']);

        $this->assertSame('Follow up', $updated->notes);
        $this->assertSame($unit->id, $updated->unit_id);
    }

    public function test_update_without_listing_or_unit_keys_keeps_both_links(): void
    {
        $company = $this->company('A');
        $actor = $this->actor($company);
        $unit = $this->unit($company, 'A-101');
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $this->lead($actor)->id, 'type' => OpportunityType::Sale->value, 'unit_id' => $unit->id]);

        // Listings are switched off, so the forms no longer send listing_id. It must not wipe the link.
        $updated = app(OpportunityService::class)->update($actor, $opportunity, ['notes' => 'Only notes']);

        $this->assertSame($unit->id, $updated->unit_id);
    }

    public function test_offer_inherits_the_opportunity_unit_and_may_move_to_another_own_unit(): void
    {
        $a = $this->company('A');
        $b = $this->company('B');
        $actor = $this->actor($a);
        $unit = $this->unit($a, 'A-101');
        $second = $this->unit($a, 'A-102');
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $this->lead($actor)->id, 'type' => OpportunityType::Sale->value, 'unit_id' => $unit->id]);

        $inherited = app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $opportunity->id, 'transaction_type' => OpportunityType::Sale->value]);
        $moved = app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $opportunity->id, 'transaction_type' => OpportunityType::Sale->value, 'unit_id' => $second->id]);

        $this->assertSame($unit->id, $inherited->unit_id);
        $this->assertSame($second->id, $moved->unit_id);

        $this->expectException(ValidationException::class);
        app(OfferNegotiationService::class)->createOffer($actor, ['opportunity_id' => $opportunity->id, 'transaction_type' => OpportunityType::Sale->value, 'unit_id' => $this->unit($b, 'B-1')->id]);
    }

    public function test_reservation_from_an_accepted_offer_uses_the_offer_unit_without_picking_it_again(): void
    {
        $company = $this->company('A');
        $actor = $this->actor($company);
        $unit = $this->unit($company, 'A-101');
        $lead = $this->lead($actor);
        $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Nour', 'lead_id' => $lead->id, 'is_active' => true]);
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'type' => OpportunityType::Sale->value, 'unit_id' => $unit->id]);
        $offers = app(OfferNegotiationService::class);
        $offer = $offers->createOffer($actor, ['opportunity_id' => $opportunity->id, 'transaction_type' => OpportunityType::Sale->value]);
        $revision = $offers->submitRevision($actor, $offers->createDraftRevision($actor, $offer, ['proposed_by_side' => OfferProposedBySide::Customer->value, 'amount' => 110000, 'currency' => 'USD']));
        $offers->confirmAgreement($actor, $revision);

        $reservation = app(ReservationWorkflowService::class)->createDraft($actor, [
            'source' => ReservationSource::AcceptedOffer->value,
            'offer_id' => $offer->id,
            'customer_party_id' => $party->id,
            // no unit_id: it must come from the offer
        ]);

        $this->assertSame($unit->id, $reservation->unit_id);
        $this->assertSame('110000.00', (string) $reservation->agreed_amount);
    }

    public function test_opportunity_create_form_offers_only_ready_units_and_saves_the_choice(): void
    {
        $company = $this->company('A');
        $actor = $this->actor($company);
        $ready = $this->unit($company, 'R-1');
        $sold = $this->unit($company, 'S-1', UnitStatus::Sold->value);
        $lead = $this->lead($actor);
        $pipeline = app(\App\Services\CrmPipelineProvisioningService::class)->ensureDefault($company);
        $stage = $pipeline->stages()->where('is_active', true)->orderBy('position')->firstOrFail();
        $this->actingAs($actor);

        // Only Ready units are offered, never a sold one.
        $options = \App\Filament\Support\OpportunityUnitFields::unitOptions($company->id, null, OpportunityType::Sale);
        $this->assertArrayHasKey($ready->id, $options);
        $this->assertArrayNotHasKey($sold->id, $options);

        \Livewire\Livewire::test(\App\Filament\Resources\OpportunityResource\Pages\CreateOpportunity::class)
            ->assertFormFieldExists('unit_id')->assertFormFieldExists('project_id')
            ->fillForm([
                'lead_id' => $lead->id,
                'type' => OpportunityType::Sale->value,
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $stage->id,
                'unit_id' => $ready->id,
                'source' => CrmInquirySource::Manual->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($ready->id, Opportunity::withoutGlobalScopes()->where('lead_id', $lead->id)->firstOrFail()->unit_id);
    }
}
