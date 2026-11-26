<?php

namespace Tests\Feature;

use App\Enums\{ContractStatus, ContractType, ContractVersionStatus};
use App\Filament\Resources\ContractResource\Pages\CreateContract;
use App\Models\{Company, Contract, ContractParty, ContractTemplate, ContractTemplateVersion, ContractTerm, ContractVersion, Location, Party, Property, Unit, User};
use App\Services\{ContractDraftService, ContractPdfService, ContractTemplateService, ContractWorkflowService};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A contract PDF is printed from its template version's legal text, so no
 * contract may be reviewed, signed or printed without one, and a template a
 * contract relies on must never be erasable.
 */
class ContractTemplateGuaranteeTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_contract_pdf_can_be_produced_without_a_legal_template(): void
    {
        [, $user, $contract] = $this->contractWithoutTemplate();
        $this->grant($user, ['view_contract', 'generate_contract_pdf']);
        $version = $contract->versions()->firstOrFail();
        $pdf = app(ContractPdfService::class);

        try {
            $pdf->preview($user, $version);
            $this->fail('A draft with no legal template was previewed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template_version_id', $exception->errors());
            $this->assertSame(__('contract_domain.pdf.template_required'), $exception->errors()['template_version_id'][0]);
        }

        // The approval route cannot reach this state, but a forged or legacy
        // record must still never produce a document with no legal text.
        $version->forceFill(['status' => ContractVersionStatus::Approved])->save();

        try {
            $pdf->generateOfficial($user, $version->fresh());
            $this->fail('An official PDF was issued with no legal template.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template_version_id', $exception->errors());
        }

        $this->assertSame(0, $version->documents()->count());
    }

    public function test_a_contract_cannot_be_sent_for_review_without_a_legal_template(): void
    {
        [, $user, $contract] = $this->contractWithoutTemplate();
        $this->grant($user, ['submit_contract_version']);

        try {
            app(ContractWorkflowService::class)->submit($user, $contract->versions()->firstOrFail());
            $this->fail('A contract with no legal template was sent for review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template_version_id', $exception->errors());
        }

        $this->assertSame(ContractVersionStatus::Draft, $contract->versions()->firstOrFail()->status);
    }

    public function test_the_contract_form_says_what_to_do_when_the_company_has_no_template(): void
    {
        [, $user, $unit] = $this->companyUserAndUnit('No template');
        $this->grant($user, ['create_contract', 'view_any_contract']);
        $this->actingAs($user);

        Livewire::test(CreateContract::class)
            ->assertSuccessful()
            ->assertSee(__('contract_domain.template.choose_type_first'))
            ->fillForm(['contract_type' => ContractType::Sale->value, 'property_id' => $unit->property_id])
            ->assertSee(__('contract_domain.template.none_for_type'));
    }

    public function test_the_form_stops_naming_the_gap_once_a_template_exists(): void
    {
        [, $user] = $this->companyUserAndUnit('Has template');
        $this->grant($user, ['create_contract', 'view_any_contract']);
        $this->templateVersion($user, ContractType::Sale);
        $this->actingAs($user);

        Livewire::test(CreateContract::class)
            ->fillForm(['contract_type' => ContractType::Sale->value])
            ->assertDontSee(__('contract_domain.template.none_for_type'));
    }

    public function test_a_template_version_a_contract_relies_on_cannot_be_deleted(): void
    {
        $template = $this->templateInUse('Protected template');

        try {
            $template->delete();
            $this->fail('A template version used by a contract was deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template_version', $exception->errors());
        }

        $this->assertDatabaseHas('contract_template_versions', ['id' => $template->id]);
    }

    public function test_the_database_itself_refuses_to_erase_a_template_a_contract_relies_on(): void
    {
        $template = $this->templateInUse('Database guard');

        // The model guard covers the application; this covers a raw delete from
        // a seeder, a console command or a future code path that bypasses it.
        $this->expectException(QueryException::class);
        DB::table('contract_template_versions')->where('id', $template->id)->delete();
    }

    public function test_a_template_of_the_wrong_type_or_another_company_is_refused(): void
    {
        [, $user, $unit] = $this->companyUserAndUnit('Wrong template');
        [, $otherUser] = $this->companyUserAndUnit('Other company');
        $this->grant($user, ['create_contract']);
        $leaseTemplate = $this->templateVersion($user, ContractType::Lease);
        $foreignTemplate = $this->templateVersion($otherUser, ContractType::Sale);

        $data = [
            'contract_type' => ContractType::Sale->value,
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
            'total_amount' => 1000,
            'currency' => 'USD',
            'parties' => [],
        ];

        foreach (['wrong type' => $leaseTemplate, 'another company' => $foreignTemplate] as $case => $template) {
            try {
                app(ContractDraftService::class)->create($user, [...$data, 'template_version_id' => $template->id]);
                $this->fail("A template from $case was accepted.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('template_version_id', $exception->errors());
            }
        }
    }

    public function test_deactivating_a_template_blocks_new_contracts_but_keeps_signed_ones_printable(): void
    {
        [, $user, $unit] = $this->companyUserAndUnit('Deactivated template');
        $this->grant($user, ['create_contract', 'submit_contract_version', 'approve_contract_version', 'view_contract', 'generate_contract_pdf']);
        $template = $this->templateVersion($user, ContractType::Sale);
        $party = Party::create(['company_id' => $user->company_id, 'type' => 'person', 'name' => 'Signed Buyer', 'is_active' => true]);

        $contract = app(ContractDraftService::class)->create($user, [
            'contract_type' => ContractType::Sale->value,
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
            'template_version_id' => $template->id,
            'total_amount' => 1000,
            'currency' => 'USD',
            'start_date' => today()->toDateString(),
            'parties' => [['party_id' => $party->id, 'role' => 'buyer']],
        ]);
        $workflow = app(ContractWorkflowService::class);
        $version = $contract->versions()->firstOrFail();
        $workflow->submit($user, $version);
        $workflow->approve($user, $version->fresh());

        $template->template->forceFill(['is_active' => false])->save();

        // The company retired this template, so nothing new may use it.
        try {
            app(ContractDraftService::class)->create($user, [
                'contract_type' => ContractType::Sale->value,
                'property_id' => $unit->property_id,
                'unit_id' => $unit->id,
                'template_version_id' => $template->id,
                'total_amount' => 500,
                'currency' => 'USD',
                'parties' => [],
            ]);
            $this->fail('A retired template was used for a new contract.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template_version_id', $exception->errors());
        }

        // The contract already approved under it stays printable.
        $official = app(ContractPdfService::class)->generateOfficial($user, $version->fresh());
        $this->assertSame('official_approved_pdf', $official->document->document_type->value);
    }

    public function test_a_template_version_needs_real_legal_text(): void
    {
        [, $user] = $this->companyUserAndUnit('Empty template');
        $this->grant($user, ['update_contract::template']);
        $template = ContractTemplate::create([
            'company_id' => $user->company_id, 'name' => 'Blank '.str()->uuid(),
            'contract_type' => ContractType::Sale, 'is_active' => true,
        ]);

        try {
            app(ContractTemplateService::class)->createVersion($user, $template, ['body' => '<p>   </p>']);
            $this->fail('A template version with no legal text was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('body', $exception->errors());
        }

        $this->assertSame(0, $template->versions()->count());
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * A draft that is complete in every other way — party, term, amount — so the
     * missing legal template is the only thing that can stop it.
     *
     * @return array{0: Company, 1: User, 2: Contract}
     */
    private function contractWithoutTemplate(): array
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Template gap');
        $contract = Contract::create([
            'company_id' => $company->id, 'contract_number' => 'CON-'.str()->random(8),
            'contract_type' => ContractType::Sale, 'status' => ContractStatus::Draft,
            'unit_id' => $unit->id, 'property_id' => $unit->property_id, 'created_by' => $user->id,
        ]);
        $version = ContractVersion::create([
            'company_id' => $company->id, 'contract_id' => $contract->id, 'version_number' => 1,
            'status' => ContractVersionStatus::Draft, 'total_amount' => 1000, 'currency' => 'USD',
            'start_date' => today()->toDateString(), 'created_by' => $user->id,
        ]);
        $party = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Gap Buyer', 'is_active' => true]);
        ContractParty::create([
            'company_id' => $company->id, 'contract_version_id' => $version->id,
            'party_id' => $party->id, 'role' => 'buyer', 'snapshot' => ['name' => $party->name],
        ]);
        ContractTerm::create([
            'company_id' => $company->id, 'contract_version_id' => $version->id,
            'term_type' => 'legal', 'position' => 1, 'title' => 'Price', 'text' => 'Agreed price.',
        ]);

        return [$company, $user, $contract->fresh()];
    }

    /** A template version a saved contract already depends on. */
    private function templateInUse(string $prefix): ContractTemplateVersion
    {
        [, $user, $unit] = $this->companyUserAndUnit($prefix);
        $this->grant($user, ['create_contract']);
        $template = $this->templateVersion($user, ContractType::Sale);
        $party = Party::create(['company_id' => $user->company_id, 'type' => 'person', 'name' => 'Keeper', 'is_active' => true]);

        app(ContractDraftService::class)->create($user, [
            'contract_type' => ContractType::Sale->value,
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
            'template_version_id' => $template->id,
            'total_amount' => 1000,
            'currency' => 'USD',
            'parties' => [['party_id' => $party->id, 'role' => 'buyer']],
        ]);

        return $template;
    }

    /** @return array{0: Company, 1: User, 2: Unit} */
    private function companyUserAndUnit(string $prefix): array
    {
        $company = Company::create(['name' => $prefix.' Co '.str()->random(4), 'email' => str()->uuid().'@example.test']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::create(['company_id' => $company->id, 'name' => $prefix.' City', 'type' => 'city']);
        $property = Property::create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => $prefix.' Property', 'address' => 'Test address']);
        $unit = Unit::create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'A-101', 'status' => 'ready', 'type' => 'apartment']);

        return [$company, $user, $unit];
    }

    private function templateVersion(User $user, ContractType $type): ContractTemplateVersion
    {
        $template = ContractTemplate::create([
            'company_id' => $user->company_id, 'name' => 'Tpl '.str()->uuid(),
            'contract_type' => $type, 'is_active' => true,
        ]);

        return ContractTemplateVersion::create([
            'company_id' => $user->company_id, 'contract_template_id' => $template->id, 'version_number' => 1,
            'body' => '<p>{{contract_number}}</p>', 'clauses' => [['type' => 'legal', 'title' => 'Term', 'text' => 'Legal term.']],
            'created_by' => $user->id,
        ]);
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);
        $user->forgetCachedPermissions();
    }
}
