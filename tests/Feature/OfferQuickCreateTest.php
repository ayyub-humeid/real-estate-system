<?php

namespace Tests\Feature;

use App\Enums\{CrmInquirySource, OfferProposedBySide, OfferRevisionStatus, OfferStatus, OpportunityType};
use App\Filament\Resources\OpportunityResource\Pages\ViewOpportunity;
use App\Filament\Resources\OpportunityResource\RelationManagers\OffersRelationManager;
use App\Models\{Company, CrmOffer, CrmOfferRevision, Location, Opportunity, Property, Unit, User};
use App\Services\{LeadIntakeService, OfferNegotiationService, OpportunityService};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** An admin records the first price while creating the offer; no trip to the Price history tab. */
class OfferQuickCreateTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = ['create_lead', 'view_any_lead', 'view_lead', 'create_opportunity', 'view_any_opportunity', 'view_opportunity', 'update_opportunity',
        'create_crm::offer', 'view_any_crm::offer', 'view_crm::offer', 'update_crm::offer', 'create_offer_revision', 'view_offer_revision'];

    private function actor(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function opportunity(User $actor, Company $company, OpportunityType $type = OpportunityType::Sale): Opportunity
    {
        $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'City', 'type' => 'city']);
        $property = Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => 'Tower', 'address' => 'Address']);
        $unit = Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'A-101', 'type' => 'apartment', 'status' => 'ready',
            'sale_price' => 250000, 'sale_currency' => 'USD', 'rent_price' => 900, 'rent_currency' => 'USD']);
        $lead = app(LeadIntakeService::class)->createLead($actor, ['name' => 'Nour', 'phone' => '0591234567', 'source' => CrmInquirySource::Manual->value]);

        return app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'type' => $type->value, 'unit_id' => $unit->id]);
    }

    private function company(): Company
    {
        return Company::create(['name' => 'Madar', 'email' => 'madar@test.local']);
    }

    public function test_price_is_recorded_and_sent_in_one_step(): void
    {
        $company = $this->company();
        $actor = $this->actor($company, [...self::BASE, 'finalize_offer_revision']);
        $opportunity = $this->opportunity($actor, $company);

        $offer = app(OfferNegotiationService::class)->createQuickOffer($actor, [
            'opportunity_id' => $opportunity->id, 'amount' => 240000, 'currency' => 'USD', 'down_payment_amount' => 40000, 'send_now' => true,
        ]);

        $revision = $offer->revisions()->firstOrFail();
        $this->assertSame(OfferStatus::Negotiating, $offer->status);
        $this->assertSame(OfferRevisionStatus::Submitted, $revision->status);
        $this->assertSame(OfferProposedBySide::Company, $revision->proposed_by_side);
        $this->assertSame('240000.00', (string) $revision->amount);
        $this->assertSame($opportunity->unit_id, $offer->unit_id);
    }

    public function test_send_now_off_keeps_the_price_as_a_draft(): void
    {
        $company = $this->company();
        $actor = $this->actor($company, [...self::BASE, 'finalize_offer_revision']);
        $opportunity = $this->opportunity($actor, $company);

        $offer = app(OfferNegotiationService::class)->createQuickOffer($actor, ['opportunity_id' => $opportunity->id, 'amount' => 240000, 'currency' => 'USD', 'send_now' => false]);

        $this->assertSame(OfferStatus::Draft, $offer->status);
        $this->assertSame(OfferRevisionStatus::Draft, $offer->revisions()->firstOrFail()->status);
    }

    public function test_without_an_amount_only_the_offer_is_created(): void
    {
        $company = $this->company();
        $actor = $this->actor($company, self::BASE);
        $opportunity = $this->opportunity($actor, $company);

        $offer = app(OfferNegotiationService::class)->createQuickOffer($actor, ['opportunity_id' => $opportunity->id, 'send_now' => true]);

        $this->assertSame(OfferStatus::Draft, $offer->status);
        $this->assertSame(0, $offer->revisions()->count());
    }

    public function test_a_failure_in_the_price_leaves_no_half_created_offer(): void
    {
        $company = $this->company();
        $actor = $this->actor($company, [...self::BASE, 'finalize_offer_revision']);
        $sale = $this->opportunity($actor, $company);
        $service = app(OfferNegotiationService::class);

        // Down payment above the price is refused, and the offer must not survive.
        try {
            $service->createQuickOffer($actor, ['opportunity_id' => $sale->id, 'amount' => 1000, 'currency' => 'USD', 'down_payment_amount' => 5000, 'send_now' => true]);
            $this->fail('A down payment above the price must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('down_payment_amount', $exception->errors());
        }
        $this->assertSame(0, CrmOffer::withoutGlobalScopes()->count());

        // Sending without the send permission is refused as a whole, not silently left as a draft.
        $limited = $this->actor($company, self::BASE);
        try {
            $service->createQuickOffer($limited, ['opportunity_id' => $sale->id, 'amount' => 1000, 'currency' => 'USD', 'send_now' => true]);
            $this->fail('Sending needs the finalize permission.');
        } catch (AuthorizationException) {
        }
        $this->assertSame(0, CrmOffer::withoutGlobalScopes()->count());
        $this->assertSame(0, CrmOfferRevision::withoutGlobalScopes()->count());
    }

    public function test_a_rent_price_needs_its_rent_terms(): void
    {
        $company = $this->company();
        $actor = $this->actor($company, [...self::BASE, 'finalize_offer_revision']);
        $rent = $this->opportunity($actor, $company, OpportunityType::Rent);

        try {
            app(OfferNegotiationService::class)->createQuickOffer($actor, ['opportunity_id' => $rent->id, 'amount' => 900, 'currency' => 'USD', 'send_now' => true]);
            $this->fail('A rent price without frequency and term must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rent_frequency', $exception->errors());
        }
        $this->assertSame(0, CrmOffer::withoutGlobalScopes()->count());

        $offer = app(OfferNegotiationService::class)->createQuickOffer($actor, ['opportunity_id' => $rent->id, 'amount' => 900, 'currency' => 'USD',
            'rent_frequency' => 'monthly', 'lease_term_value' => 12, 'lease_term_unit' => 'month', 'send_now' => true]);
        $this->assertSame(OfferStatus::Negotiating, $offer->status);
    }

    public function test_offers_tab_form_prefills_the_unit_price_and_creates_the_offer_with_its_price(): void
    {
        $company = $this->company();
        $actor = $this->actor($company, [...self::BASE, 'finalize_offer_revision']);
        $opportunity = $this->opportunity($actor, $company);
        $this->actingAs($actor);

        Livewire::test(OffersRelationManager::class, ['ownerRecord' => $opportunity, 'pageClass' => ViewOpportunity::class])
            ->mountTableAction('create')
            ->assertTableActionDataSet(['amount' => 250000, 'currency' => 'USD', 'send_now' => true])
            ->setTableActionData(['amount' => 245000, 'send_now' => true])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $offer = CrmOffer::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($opportunity->unit_id, $offer->unit_id);
        $this->assertSame(OfferStatus::Negotiating, $offer->status);
        $this->assertSame('245000.00', (string) $offer->revisions()->firstOrFail()->amount);
    }
}
