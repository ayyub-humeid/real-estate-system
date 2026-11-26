<?php

namespace Tests\Feature;

use App\Enums\{ContractType, ContractVersionStatus};
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\ContractResource\Pages\ListContracts;
use App\Filament\Resources\ContractTemplateResource\Pages\{CreateContractTemplate, ViewContractTemplate};
use App\Filament\Resources\ContractTemplateResource\RelationManagers\VersionsRelationManager;
use App\Filament\Widgets\ContractTemplateSetupWidget;
use App\Models\{Company, ContractTemplate, ContractTemplateVersion, ContractVersion, Location, Party, Property, Unit, User};
use App\Services\{ContractDraftService, ContractPdfService, ContractPlaceholders, ContractTemplateReadiness};
use App\Support\AmountInWords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContractPlaceholdersAndReadinessTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('arabicAmounts')]
    public function test_arabic_amounts_are_written_grammatically(string $amount, string $expected): void
    {
        $this->assertSame("فقط {$expected} لا غير", AmountInWords::format($amount, 'USD', 'ar'));
    }

    public static function arabicAmounts(): array
    {
        return [
            'one' => ['1', 'دولار أمريكي واحد'],
            'two' => ['2', 'دولاران أمريكيان'],
            'plural' => ['3', 'ثلاثة دولارات أمريكية'],
            'ten' => ['10', 'عشرة دولارات أمريكية'],
            'eleven' => ['11', 'أحد عشر دولاراً أمريكياً'],
            'compound' => ['25', 'خمسة وعشرون دولاراً أمريكياً'],
            'hundred' => ['100', 'مائة دولار أمريكي'],
            'two hundred' => ['200', 'مائتا دولار أمريكي'],
            'thousand' => ['1000', 'ألف دولار أمريكي'],
            'two thousand' => ['2000', 'ألفا دولار أمريكي'],
            'two and a half thousand' => ['2500', 'ألفان وخمسمائة دولار أمريكي'],
            'six thousand' => ['6000', 'ستة آلاف دولار أمريكي'],
            'eleven thousand' => ['11000', 'أحد عشر ألف دولار أمريكي'],
            'eleven thousand and more' => ['11500', 'أحد عشر ألفاً وخمسمائة دولار أمريكي'],
            'quarter million' => ['250000', 'مائتان وخمسون ألف دولار أمريكي'],
            'two hundred thousand' => ['200000', 'مائتا ألف دولار أمريكي'],
            'two million' => ['2000000', 'مليونا دولار أمريكي'],
            'with fraction' => ['1250.50', 'ألف ومائتان وخمسون دولاراً أمريكياً و50/100'],
        ];
    }

    public function test_english_amounts_and_unlisted_currencies(): void
    {
        $this->assertSame('One thousand two hundred fifty US dollars and 05/100 only', AmountInWords::format('1250.05', 'USD', 'en'));
        $this->assertSame('One Israeli shekel only', AmountInWords::format('1', 'ILS', 'en'));
        $this->assertSame('Five CHF only', AmountInWords::format('5', 'CHF', 'en'));
    }

    public function test_an_arabic_lease_template_prints_ids_rent_words_and_the_schedule_once(): void
    {
        app()->setLocale('en');
        [$user, $version] = $this->leaseDraft(
            '<div>المستأجر {{tenant_name}} هوية {{tenant_id_number}}</div>'
            .'<div>{{rent_amount_in_words}} {{rent_frequency}} × {{payments_count}}</div>'
            .'<div>{{total_amount_in_words}}</div><div>{{payment_schedule}}</div>'
        );
        $version->load(['contract.company', 'parties.party']);
        $body = $version->templateVersion->body;
        $values = app(ContractPlaceholders::class)->values($version, $body, null);
        $text = strtr(ContractPlaceholders::unwrapBlocks($body), $values);

        $this->assertStringContainsString('المستأجر Sami Tenant هوية 401234567', $text);
        $this->assertStringContainsString('فقط خمسمائة دولار أمريكي لا غير', $text);
        $this->assertStringContainsString('فقط ستة آلاف دولار أمريكي لا غير', $text);
        $this->assertStringContainsString('× 12', $text);
        // The printer's screen is English, the legal text is Arabic.
        $this->assertStringContainsString(__('contract_domain.pdf.schedule_missing', [], 'ar'), $text);
        $this->assertStringNotContainsString('{{', $text);
        $this->assertSame('en', app()->getLocale());

        $this->assertStringStartsWith('%PDF', app(ContractPdfService::class)->preview($user, $version));
    }

    public function test_the_pdf_page_omits_its_own_schedule_section_when_the_template_places_it(): void
    {
        [, $version] = $this->leaseDraft('<div>{{payment_schedule}}</div>');
        $version->load(['contract.company', 'parties.party', 'terms']);
        $heading = __('contract_domain.pdf.payment_distribution');
        $data = [
            'contractVersion' => $version, 'contract' => $version->contract, 'settings' => null, 'body' => '',
            'contractSummary' => ['unit' => '—', 'property' => '—', 'project' => '—'], 'draft' => true,
            'paymentPlan' => null, 'rentTerms' => null, 'signatures' => [], 'images' => [],
        ];

        $this->assertStringNotContainsString($heading, view('pdf.contract-version', [...$data, 'scheduleInBody' => true])->render());
        $this->assertStringContainsString($heading, view('pdf.contract-version', [...$data, 'scheduleInBody' => false])->render());
    }

    public function test_every_offered_placeholder_is_filled_and_translated(): void
    {
        [, $version] = $this->leaseDraft('<div>text</div>');
        $version->load(['contract.company', 'parties.party']);
        $placeholders = app(ContractPlaceholders::class);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            foreach (ContractType::cases() as $type) {
                foreach ($placeholders->catalogue($type) as $variables) {
                    foreach ($variables as $token => $label) {
                        $this->assertStringNotContainsString('contract_domain.', $label);
                        $this->assertArrayHasKey($token, $placeholders->values($version, 'text', null));
                        $this->assertArrayHasKey($token, $placeholders->samples('text'));
                    }
                }
            }
        }
    }

    public function test_the_template_editor_offers_the_placeholders_that_fit_the_contract_type(): void
    {
        [$user] = $this->companyUser('Editor', ['view_contract::template', 'view_any_contract::template', 'update_contract::template']);
        $template = $this->template($user, ContractType::Lease);
        $this->actingAs($user);

        Livewire::test(VersionsRelationManager::class, ['ownerRecord' => $template, 'pageClass' => ViewContractTemplate::class])
            ->mountTableAction('create')
            ->assertSee(__('contract_domain.placeholders.fields.rent_amount_in_words'))
            ->assertSee(__('contract_domain.placeholders.fields.payment_schedule'))
            ->assertSee(__('contract_domain.placeholders.party_fields.id_number', ['role' => __('Tenant')]))
            ->assertDontSee(__('contract_domain.placeholders.party_fields.id_number', ['role' => __('Buyer')]));
    }

    public function test_the_contract_list_and_dashboard_warn_until_every_type_has_legal_text(): void
    {
        [$user] = $this->companyUser('Setup', ['view_any_contract', 'create_contract::template', 'view_any_contract::template', 'view_contract::template', 'update_contract::template']);
        $this->actingAs($user);

        Livewire::test(ListContracts::class)
            ->assertSee(__('contract_domain.readiness.none_heading'))
            ->assertSee(__('contract_domain.readiness.create_template'));
        Livewire::test(Dashboard::class)->assertSee(__('contract_domain.readiness.none_heading'));

        // A template without text still cannot print a contract.
        $lease = $this->template($user, ContractType::Lease);
        $sale = $this->template($user, ContractType::Sale);
        $this->textFor($sale);

        Livewire::test(ContractTemplateSetupWidget::class)
            ->assertSee(__('contract_domain.readiness.some_heading'))
            ->assertSee(__('contract_domain.readiness.no_text', ['template' => $lease->name]))
            ->assertSee(__('contract_domain.readiness.add_text'));

        $this->textFor($lease);

        $this->assertFalse(ContractTemplateSetupWidget::canView());
        Livewire::test(ListContracts::class)->assertDontSee(__('contract_domain.readiness.some_heading'));
    }

    public function test_the_warning_is_company_isolated_and_respects_permissions(): void
    {
        [$viewer] = $this->companyUser('Viewer', ['view_any_contract']);
        [$other] = $this->companyUser('Ready elsewhere', ['view_any_contract']);
        $this->textFor($this->template($other, ContractType::Sale));
        $this->textFor($this->template($other, ContractType::Lease));

        $readiness = app(ContractTemplateReadiness::class);
        $this->assertSame([], $readiness->missingTypes($other->company_id));
        $this->assertSame([ContractType::Sale, ContractType::Lease], $readiness->missingTypes($viewer->company_id));

        $this->actingAs($viewer);
        Livewire::test(ContractTemplateSetupWidget::class)
            ->assertSee(__('contract_domain.readiness.ask_admin'))
            ->assertDontSee(__('contract_domain.readiness.create_template'));

        [$outsider] = $this->companyUser('No contract access', []);
        $this->actingAs($outsider);
        $this->assertFalse(ContractTemplateSetupWidget::canView());
    }

    public function test_the_create_template_link_preselects_the_missing_type(): void
    {
        [$user] = $this->companyUser('Prefill', ['create_contract::template', 'view_any_contract::template']);
        $this->actingAs($user);

        Livewire::withQueryParams(['contract_type' => 'lease'])
            ->test(CreateContractTemplate::class)
            ->assertFormSet(['contract_type' => 'lease']);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /** @return array{0: User, 1: Company} */
    private function companyUser(string $prefix, array $permissions): array
    {
        $company = Company::create(['name' => $prefix.' Co '.str()->random(4), 'email' => str()->uuid().'@example.test']);
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);

        return [$user, $company];
    }

    private function template(User $user, ContractType $type): ContractTemplate
    {
        return ContractTemplate::create([
            'company_id' => $user->company_id, 'name' => 'Tpl '.str()->uuid(),
            'contract_type' => $type, 'is_active' => true,
        ]);
    }

    private function textFor(ContractTemplate $template, string $body = '<div>Legal text</div>'): ContractTemplateVersion
    {
        return ContractTemplateVersion::create([
            'company_id' => $template->company_id, 'contract_template_id' => $template->id,
            'version_number' => 1, 'body' => $body,
        ]);
    }

    /** @return array{0: User, 1: ContractVersion} */
    private function leaseDraft(string $body): array
    {
        [$user, $company] = $this->companyUser('Lease', ['create_contract', 'view_contract']);
        $location = Location::create(['company_id' => $company->id, 'name' => 'City', 'type' => 'city']);
        $property = Property::create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => 'Property', 'address' => 'Address']);
        $unit = Unit::create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'A-1', 'status' => 'ready', 'type' => 'apartment']);
        $tenant = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Sami Tenant', 'national_id' => '401234567', 'is_active' => true]);

        $contract = app(ContractDraftService::class)->create($user, [
            'contract_type' => ContractType::Lease->value,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'template_version_id' => $this->textFor($this->template($user, ContractType::Lease), $body)->id,
            'rent_amount' => 500,
            'rent_frequency' => 'monthly',
            'currency' => 'USD',
            'start_date' => today()->toDateString(),
            'end_date' => today()->addYear()->subDay()->toDateString(),
            'parties' => [['party_id' => $tenant->id, 'role' => 'tenant']],
        ]);

        $version = $contract->versions()->firstOrFail();
        $this->assertSame(ContractVersionStatus::Draft, $version->status);

        return [$user, $version];
    }
}
