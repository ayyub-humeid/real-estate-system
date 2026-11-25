<?php

namespace Tests\Feature;

use App\Enums\{ContractStatus, ContractType};
use App\Filament\Exports\{ContractExporter, ExpenseExporter, LeadExporter, PlanExporter, ProjectExporter, UnitExporter};
use App\Filament\Resources\ContractResource\Pages\ListContracts;
use App\Filament\Resources\ExpenseResource\Pages\ListExpenses;
use App\Filament\Resources\LeadResource\Pages\ListLeads;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\PlanResource\Pages\ListPlans;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Resources\UnitResource\Pages\ListUnits;
use App\Jobs\CreateNumericAwareXlsxFile;
use App\Models\{Company, Contract, Expense, Lead, Location, Plan, Project, Property, Subscription, Unit, User};
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class CompanyDataExportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $actor;
    private Project $project;
    private Property $property;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');

        $this->company = Company::create(['name' => 'Export Co', 'email' => 'export@example.test', 'is_active' => true]);
        // Starter-equivalent plan: exports must work on the lowest tier.
        Subscription::create([
            'company_id' => $this->company->id,
            'plan_id' => Plan::create([
                'name' => 'Starter', 'slug' => 'starter-fixture', 'price' => 99, 'billing_cycle' => 'monthly', 'is_active' => true,
                'features' => ['maintenance_tracking' => true, 'rental_requests' => true, 'expense_tracking' => true, 'max_units' => 50, 'max_projects' => 2],
            ])->id,
            'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);
        $this->actor = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Finance Lead']);

        $location = Location::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'name' => 'Amman', 'type' => 'city']);
        $this->property = Property::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'location_id' => $location->id, 'name' => 'Marina Tower', 'address' => 'Sea Road',
        ]);
        $this->project = Project::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'name' => 'Marina Phase 1', 'project_type' => 'residential',
            'currency' => 'USD', 'status' => 'planning', 'start_date' => '2026-01-15',
        ]);
        $this->unit = Unit::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'property_id' => $this->property->id, 'project_id' => $this->project->id,
            'unit_number' => 'A-304', 'status' => 'draft', 'type' => 'apartment', 'bedrooms' => 3, 'bathrooms' => 2,
            'actual_area' => 145.5, 'area_unit' => 'sqm', 'sale_price' => 250000, 'sale_currency' => 'USD',
        ]);
    }

    // ── Data: every exporter, every column, against real records ──────────────

    public function test_contract_export_resolves_every_column(): void
    {
        $contract = Contract::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'contract_number' => 'CNT-77', 'contract_type' => ContractType::Sale,
            'status' => ContractStatus::Active, 'created_by' => $this->actor->id,
            'property_id' => $this->property->id, 'project_id' => $this->project->id, 'unit_id' => $this->unit->id,
        ]);

        $row = $this->fullRow(ContractExporter::class, $contract);

        $this->assertSame('CNT-77', $row['contract_number']);
        $this->assertSame('A-304 · Marina Tower', $row['subject']);
        $this->assertSame(ContractType::Sale->getLabel(), $row['contract_type']);
        $this->assertSame(ContractStatus::Active->getLabel(), $row['status']);
        $this->assertSame('Marina Phase 1', $row['project.name']);
        $this->assertSame('Finance Lead', $row['createdBy.name']);
    }

    public function test_unit_export_resolves_every_column_and_keeps_prices_numeric(): void
    {
        $row = $this->fullRow(UnitExporter::class, $this->unit);

        $this->assertSame('A-304', $row['unit_number']);
        $this->assertSame('Marina Tower', $row['property.name']);
        $this->assertSame('Marina Phase 1', $row['project.name']);
        $this->assertSame('250000', $row['sale_price']);
        $this->assertSame('145.5', $row['actual_area']);
        $this->assertSame('USD', $row['sale_currency']);
        $this->assertSame(['actual_area', 'sale_price', 'rent_price'], UnitExporter::numericColumns());
    }

    public function test_lead_export_resolves_every_column(): void
    {
        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'name' => 'Sara Haddad', 'phone' => '+962790000000',
            'email' => 'sara@example.test', 'status' => 'active', 'first_source' => 'whatsapp',
            'assigned_to' => $this->actor->id, 'created_by' => $this->actor->id,
        ]);

        $row = $this->fullRow(LeadExporter::class, $lead);

        $this->assertSame('Sara Haddad', $row['name']);
        $this->assertSame('sara@example.test', $row['email']);
        $this->assertSame(__('crm.sources.whatsapp'), $row['first_source']);
        $this->assertSame(__('crm.lead_status.active'), $row['status']);
        $this->assertSame('Finance Lead', $row['assignedTo.name']);
    }

    public function test_project_export_resolves_every_column_including_counts(): void
    {
        $row = $this->fullRow(ProjectExporter::class, $this->project);

        $this->assertSame('Marina Phase 1', $row['name']);
        $this->assertSame(__('project.statuses.planning'), $row['status']);
        $this->assertSame('2026-01-15', $row['start_date']);
        $this->assertSame('1', $row['units_count']);
        $this->assertSame('0', $row['buildings_count']);
    }

    public function test_expense_export_resolves_every_column_and_keeps_amount_numeric(): void
    {
        $expense = Expense::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'property_id' => $this->property->id, 'unit_id' => $this->unit->id,
            'created_by' => $this->actor->id, 'title' => 'Lobby repair', 'category' => 'maintenance',
            'amount' => 1200.5, 'currency' => 'USD', 'status' => 'paid', 'expense_date' => '2026-11-02',
            'reference_number' => '000912',
        ]);

        $row = $this->fullRow(ExpenseExporter::class, $expense);

        $this->assertSame('2026-11-02', $row['expense_date']);
        $this->assertSame('1200.5', $row['amount']);
        $this->assertSame('Paid', $row['status']);
        $this->assertSame('Maintenance', $row['category']);
        $this->assertSame('A-304', $row['unit.unit_number']);
        $this->assertSame('Finance Lead', $row['creator.name']);
        // Identifiers stay text so a leading zero survives into Excel.
        $this->assertSame('000912', $row['reference_number']);
        $this->assertSame(['amount'], ExpenseExporter::numericColumns());
    }

    public function test_plan_export_resolves_every_column(): void
    {
        $plan = Plan::create([
            'name' => 'Starter Export', 'slug' => 'starter-test', 'price' => 99, 'annual_price' => 990, 'trial_days' => 30,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['max_projects' => 2, 'max_units' => null, 'construction_tracking' => false],
        ]);

        $row = $this->fullRow(PlanExporter::class, $plan);

        $this->assertSame('99', $row['price']);
        $this->assertSame('2', $row['features.max_projects']);
        $this->assertSame(__('plan_export.limits.unlimited'), $row['features.max_units']);
    }

    public function test_the_xlsx_writer_treats_unit_prices_as_numbers(): void
    {
        $columns = ['unit_number', 'sale_price', 'sale_currency'];
        $job = new CreateNumericAwareXlsxFile(new Export(['exporter' => UnitExporter::class]), array_fill_keys($columns, true), []);

        $positions = (new \ReflectionMethod($job, 'numericColumnPositions'))->invoke($job);

        $this->assertSame([1], $positions);
    }

    public function test_every_exporter_completes_with_a_readable_notification_and_file_name(): void
    {
        $export = Export::create([
            'file_disk' => 'local', 'file_name' => 'x', 'exporter' => UnitExporter::class,
            'total_rows' => 3, 'successful_rows' => 3, 'user_id' => $this->actor->id,
        ]);

        foreach ([ContractExporter::class, UnitExporter::class, LeadExporter::class, ProjectExporter::class, ExpenseExporter::class, PlanExporter::class] as $exporter) {
            $this->assertStringContainsString('3 rows exported', $exporter::getCompletedNotificationBody($export));
            $this->assertStringEndsWith('-'.$export->id, (new $exporter($export, [], []))->getFileName($export));
        }
    }

    // ── UI mirror: the button a company admin actually clicks ─────────────────

    /** @return array<string, array{0: class-string, 1: string, 2: class-string<Exporter>}> */
    public static function companyLists(): array
    {
        return [
            'contracts' => [ListContracts::class, 'view_any_contract', ContractExporter::class],
            'units' => [ListUnits::class, 'view_any_unit', UnitExporter::class],
            'leads' => [ListLeads::class, 'view_any_lead', LeadExporter::class],
            'projects' => [ListProjects::class, 'view_any_project', ProjectExporter::class],
            'expenses' => [ListExpenses::class, 'view_any_expense', ExpenseExporter::class],
            'payments' => [ListPayments::class, 'view_any_payment', \App\Filament\Exports\PaymentExporter::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('companyLists')]
    public function test_company_list_shows_the_export_button_and_starts_an_export(string $page, string $permission, string $exporter): void
    {
        Bus::fake();
        $this->actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $this->actingAs($this->actor);

        Livewire::test($page)
            ->assertSuccessful()
            ->assertTableActionExists('export')
            ->assertSee(__('export.action'))
            ->callTableAction('export')
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('exports', ['exporter' => $exporter, 'user_id' => $this->actor->id]);
    }

    public function test_super_admin_can_export_the_plan_list(): void
    {
        Bus::fake();
        $admin = User::factory()->create(['company_id' => null]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin);

        Livewire::test(ListPlans::class)
            ->assertSuccessful()
            ->assertTableActionExists('export')
            ->callTableAction('export')
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('exports', ['exporter' => PlanExporter::class, 'user_id' => $admin->id]);
    }

    /**
     * Runs the exporter exactly as the queued job does: through its own modifyQuery()
     * (so a wrong relationship fails here) and with every column switched on.
     *
     * @param class-string<Exporter> $exporterClass
     * @return array<string, string|null> keyed by column name
     */
    private function fullRow(string $exporterClass, Model $record): array
    {
        $columns = collect($exporterClass::getColumns())->map->getName()->all();
        $loaded = $exporterClass::modifyQuery($record::query()->withoutGlobalScopes())->whereKey($record->getKey())->firstOrFail();

        $export = Export::create([
            'file_disk' => 'local', 'file_name' => 'test', 'exporter' => $exporterClass,
            'total_rows' => 1, 'user_id' => $this->actor->id,
        ]);

        $values = (new $exporterClass($export, array_fill_keys($columns, true), []))($loaded);

        $this->assertCount(count($columns), $values);

        return array_combine($columns, $values);
    }
}
