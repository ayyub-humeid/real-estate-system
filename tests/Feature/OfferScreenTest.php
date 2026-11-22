<?php

namespace Tests\Feature;

use App\Enums\{CrmInquirySource, OfferProposedBySide, OfferStatus, OpportunityType};
use App\Filament\Resources\CrmOfferResource\Pages\{ListCrmOffers, ViewCrmOffer};
use App\Filament\Resources\CrmOfferResource\RelationManagers\OfferRevisionsRelationManager;
use App\Models\{Company, CrmOffer, CrmOfferApproval, Opportunity, User};
use App\Services\{LeadIntakeService, OfferNegotiationService, OpportunityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** The offer page shows sales-language buttons, only when they apply, and always explains a refusal. */
class OfferScreenTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\MakesReadyUnits;

    private const PERMISSIONS = ['create_lead', 'view_any_lead', 'view_lead', 'create_opportunity', 'view_any_opportunity', 'view_opportunity', 'update_opportunity',
        'create_crm::offer', 'view_any_crm::offer', 'view_crm::offer', 'update_crm::offer', 'create_offer_revision', 'update_offer_revision', 'view_any_offer_revision', 'view_offer_revision',
        'finalize_offer_revision', 'request_offer_approval', 'approve_offer_revision', 'reject_offer_revision', 'view_any_offer_approval', 'view_offer_approval',
        'confirm_offer_agreement', 'accept_offer', 'close_offer', 'withdraw_offer', 'convert_offer_to_reservation'];

    private function company(): Company
    {
        return Company::create(['name' => 'Madar', 'email' => 'madar@test.local']);
    }

    private function actor(Company $company, ?array $permissions = null): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions ?? self::PERMISSIONS as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function offer(User $actor, array $price = []): CrmOffer
    {
        $lead = app(LeadIntakeService::class)->createLead($actor, ['name' => 'Nour', 'phone' => '0591'.random_int(100000, 999999), 'source' => CrmInquirySource::Manual->value]);
        $opportunity = app(OpportunityService::class)->create($actor, ['lead_id' => $lead->id, 'type' => OpportunityType::Sale->value]);

        return app(OfferNegotiationService::class)->createQuickOffer($actor, ['opportunity_id' => $opportunity->id, 'unit_id' => $this->readyUnitId((int) $actor->company_id), ...$price]);
    }

    private function page(CrmOffer $offer)
    {
        return Livewire::test(ViewCrmOffer::class, ['record' => $offer->getRouteKey()]);
    }

    public function test_buttons_follow_the_stage_of_the_negotiation(): void
    {
        $actor = $this->actor($this->company());
        $this->actingAs($actor);
        $offer = $this->offer($actor);

        // No price yet: the only way forward is to add our price.
        $this->page($offer)
            ->assertSee(__('sales.offer_next.no_price'))
            ->assertActionVisible('send_new_price')
            ->assertActionHidden('accept_offer')->assertActionHidden('customer_counter')->assertActionHidden('ask_approval')->assertActionHidden('send_price');

        // Add our price (sent): now the customer can answer, a manager can be asked, the offer can be accepted.
        $this->page($offer)
            ->callAction('send_new_price', ['amount' => 240000, 'currency' => 'USD'])
            ->assertHasNoActionErrors()
            ->assertActionVisible('accept_offer')->assertActionVisible('customer_counter')->assertActionVisible('ask_approval')->assertActionVisible('send_new_price')
            ->assertSee(__('sales.offer_next.waiting_customer', ['amount' => '240,000 USD']));

        $this->assertSame(OfferStatus::Negotiating, $offer->fresh()->status);
    }

    public function test_the_offer_page_leads_with_the_price_and_shows_the_agreement_only_once_there_is_one(): void
    {
        $actor = $this->actor($this->company());
        $this->actingAs($actor);
        $offer = $this->offer($actor, ['amount' => 240000, 'currency' => 'USD', 'send_now' => true]);

        // Open negotiation: the headline price, the deal and the team are shown; the empty agreement card is not.
        $this->page($offer)
            ->assertSee(__('crm.offers.fields.latest_amount'))->assertSee('240,000 USD')
            ->assertSee(__('crm.offers.sections.deal'))->assertSee('Nour')
            ->assertSee(__('crm.offers.sections.team'))
            ->assertDontSee(__('crm.offers.sections.agreement'))
            ->assertDontSee(__('crm.offers.fields.agreed_amount'));

        $this->page($offer)->callAction('accept_offer')->assertHasNoActionErrors();

        $this->page($offer->fresh())
            ->assertSee(__('crm.offers.sections.agreement'))
            ->assertSee(__('crm.offers.fields.agreed_amount'));
    }
    public function test_customer_counter_then_manager_approval_then_acceptance(): void
    {
        $actor = $this->actor($this->company());
        $this->actingAs($actor);
        $offer = $this->offer($actor, ['amount' => 240000, 'currency' => 'USD', 'send_now' => true]);

        $this->page($offer)
            ->callAction('customer_counter', ['amount' => 225000, 'currency' => 'USD'])
            ->assertHasNoActionErrors()
            ->assertSee(__('sales.offer_next.customer_proposed', ['amount' => '225,000 USD']));
        $this->assertSame(OfferProposedBySide::Customer, $offer->revisions()->orderByDesc('revision_number')->first()->proposed_by_side);

        // A discount needs a manager: while the approval is pending, accepting is blocked and says why.
        $this->page($offer)->callAction('ask_approval')->assertHasNoActionErrors()
            ->assertActionDisabled('accept_offer')
            ->assertActionHidden('ask_approval')
            ->assertSee(__('sales.offer_next.waiting_approval', ['amount' => '225,000 USD']));

        $approval = CrmOfferApproval::withoutGlobalScopes()->firstOrFail();
        app(OfferNegotiationService::class)->decideApproval($actor, $approval, true, 'OK');

        $this->page($offer)->assertActionEnabled('accept_offer')->callAction('accept_offer')->assertHasNoActionErrors()
            ->assertActionHidden('accept_offer')->assertActionHidden('customer_counter')->assertActionVisible('convert_to_reservation')
            ->assertSee(__('sales.offer_next.accepted', ['amount' => '225,000 USD']));
        $this->assertSame(OfferStatus::Accepted, $offer->fresh()->status);
    }

    public function test_a_draft_price_is_sent_with_its_own_button(): void
    {
        $actor = $this->actor($this->company());
        $this->actingAs($actor);
        $offer = $this->offer($actor, ['amount' => 240000, 'currency' => 'USD', 'send_now' => false]);

        $this->page($offer)->assertActionVisible('send_price')->assertActionHidden('accept_offer')
            ->callAction('send_price')->assertHasNoActionErrors()
            ->assertActionHidden('send_price')->assertActionVisible('accept_offer');
        $this->assertSame(OfferStatus::Negotiating, $offer->fresh()->status);
    }

    public function test_closing_the_offer_has_a_button_for_each_side(): void
    {
        $actor = $this->actor($this->company());
        $this->actingAs($actor);
        $declined = $this->offer($actor, ['amount' => 1000, 'currency' => 'USD', 'send_now' => true]);
        $withdrawn = $this->offer($actor, ['amount' => 1000, 'currency' => 'USD', 'send_now' => true]);

        $this->page($declined)->callAction('customer_declined')->assertHasNoActionErrors()->assertActionHidden('customer_declined')->assertSee(__('sales.offer_next.rejected'));
        $this->page($withdrawn)->callAction('withdraw_offer')->assertHasNoActionErrors()->assertSee(__('sales.offer_next.withdrawn'));

        $this->assertSame(OfferStatus::Rejected, $declined->fresh()->status);
        $this->assertSame(OfferStatus::Withdrawn, $withdrawn->fresh()->status);
    }

    public function test_a_blocked_step_shows_the_rule_instead_of_failing_silently(): void
    {
        $actor = $this->actor($this->company());
        $this->actingAs($actor);
        $offer = $this->offer($actor, ['amount' => 1000, 'currency' => 'USD', 'send_now' => true]);

        // Down payment above the price is refused with a persistent message naming the problem.
        $this->page($offer)
            ->callAction('send_new_price', ['amount' => 1000, 'currency' => 'USD', 'down_payment_amount' => 5000])
            ->assertNotified(__('crm.offers.errors.action_failed'));
        $this->assertSame(1, $offer->revisions()->count(), 'The refused price must not be saved.');
    }

    public function test_people_without_the_permission_do_not_see_the_button(): void
    {
        $company = $this->company();
        $admin = $this->actor($company);
        $offer = $this->offer($admin, ['amount' => 1000, 'currency' => 'USD', 'send_now' => true]);

        $viewer = $this->actor($company, array_diff(self::PERMISSIONS, ['accept_offer', 'confirm_offer_agreement', 'withdraw_offer', 'close_offer', 'request_offer_approval']));
        $this->actingAs($viewer);

        $this->page($offer)->assertActionHidden('accept_offer')->assertActionHidden('withdraw_offer')->assertActionHidden('customer_declined')->assertActionHidden('ask_approval');
    }

    public function test_price_history_is_read_only_apart_from_editing_a_draft(): void
    {
        $actor = $this->actor($this->company());
        $this->actingAs($actor);
        $offer = $this->offer($actor, ['amount' => 1000, 'currency' => 'USD', 'send_now' => true]);

        Livewire::test(OfferRevisionsRelationManager::class, ['ownerRecord' => $offer, 'pageClass' => ViewCrmOffer::class])
            ->assertTableActionDoesNotExist('submit')
            ->assertTableActionDoesNotExist('request_approval')
            ->assertTableActionDoesNotExist('confirm_agreement')
            ->assertCountTableRecords(1);
    }

    public function test_managers_can_filter_the_offers_waiting_for_their_approval(): void
    {
        $company = $this->company();
        $seller = $this->actor($company);
        $waiting = $this->offer($seller, ['amount' => 1000, 'currency' => 'USD', 'send_now' => true]);
        $other = $this->offer($seller, ['amount' => 2000, 'currency' => 'USD', 'send_now' => true]);
        app(OfferNegotiationService::class)->requestApproval($seller, $waiting->revisions()->firstOrFail());

        $this->actingAs($this->actor($company));
        Livewire::test(ListCrmOffers::class)
            ->assertCanSeeTableRecords([$waiting, $other])
            ->filterTable('awaiting_my_approval')
            ->assertCanSeeTableRecords([$waiting])
            ->assertCanNotSeeTableRecords([$other]);
    }
}
