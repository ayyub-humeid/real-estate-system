<?php

namespace Tests\Feature;

use App\Enums\{ContractStatus, ContractType};
use App\Filament\Exports\{ContractExporter, ExpenseExporter, LeadExporter, PaymentExporter, UnitExporter};
use App\Models\{Company, Contract, CrmPipeline, CrmPipelineStage, Expense, Lead, Location, Opportunity, Party, Payment, Project, ProjectBuilding, ProjectBuildingFloor, ProjectPlannedUnit, ProjectProperty, Property, Unit, User};
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A company reads its exports project by project, so a record must name its project
 * however it was entered: directly, through its unit, its planned unit or its property.
 * A blank project column on a payment was what prompted this.
 */
class ExportProjectLinkTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $actor;
    private Property $property;
    private Project $marina;
    private Project $harbor;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');

        $this->company = Company::create(['name' => 'Link Co', 'email' => 'link@example.test', 'is_active' => true]);
        $this->actor = User::factory()->create(['company_id' => $this->company->id]);
        $location = Location::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'name' => 'Amman', 'type' => 'city']);
        $this->property = Property::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'location_id' => $location->id, 'name' => 'Marina Tower', 'address' => 'Sea Road',
        ]);
        $this->marina = $this->project('Marina Phase 1');
        $this->harbor = $this->project('Harbor Phase 2');
    }

    // ── Contract ─────────────────────────────────────────────────────────────

    public function test_a_contract_names_its_own_project_first(): void
    {
        $unit = $this->unit('U-1', $this->harbor);
        $contract = $this->contract(['project_id' => $this->marina->id, 'unit_id' => $unit->id]);

        $this->assertSame('Marina Phase 1', $this->row(ContractExporter::class, $contract)['project.name']);
    }

    public function test_a_contract_written_against_a_unit_reports_the_units_project(): void
    {
        $contract = $this->contract(['unit_id' => $this->unit('U-2', $this->marina)->id]);

        $this->assertSame('Marina Phase 1', $this->row(ContractExporter::class, $contract)['project.name']);
    }

    public function test_a_contract_written_against_a_planned_unit_reports_the_project_of_its_building(): void
    {
        $building = ProjectBuilding::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'project_id' => $this->harbor->id, 'name' => 'Block A', 'code' => 'A', 'building_type' => 'residential', 'status' => 'planned',
        ]);
        $floor = ProjectBuildingFloor::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'project_building_id' => $building->id, 'floor_number' => 1, 'label' => 'Floor 1',
        ]);
        $planned = ProjectPlannedUnit::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'project_building_floor_id' => $floor->id, 'code' => 'P-101', 'unit_type' => 'apartment',
        ]);

        $contract = $this->contract(['planned_unit_id' => $planned->id]);

        $this->assertSame('Harbor Phase 2', $this->row(ContractExporter::class, $contract)['project.name']);
    }

    public function test_a_contract_on_a_property_reports_the_project_the_property_is_attached_to(): void
    {
        $this->attach($this->property, $this->marina);

        $contract = $this->contract(['property_id' => $this->property->id]);

        $this->assertSame('Marina Phase 1', $this->row(ContractExporter::class, $contract)['project.name']);
    }

    public function test_a_property_that_left_a_project_no_longer_counts_toward_it(): void
    {
        $this->attach($this->property, $this->marina, detached: true);
        $this->attach($this->property, $this->harbor);

        $contract = $this->contract(['property_id' => $this->property->id]);

        $this->assertSame('Harbor Phase 2', $this->row(ContractExporter::class, $contract)['project.name']);
    }

    public function test_a_contract_with_no_project_link_leaves_the_column_empty(): void
    {
        $contract = $this->contract([]);

        $this->assertNull($this->row(ContractExporter::class, $contract)['project.name']);
    }

    // ── Payment ──────────────────────────────────────────────────────────────

    public function test_a_contract_receipt_reports_the_project_of_its_contract(): void
    {
        $contract = $this->contract(['unit_id' => $this->unit('U-3', $this->marina)->id]);
        $payment = $this->payment($contract);

        $this->assertNull($payment->project_id, 'A receipt carries no project of its own.');
        $this->assertSame('Marina Phase 1', $this->row(PaymentExporter::class, $payment)['project.name']);
    }

    public function test_a_project_cost_payment_keeps_its_own_project(): void
    {
        $contract = $this->contract(['unit_id' => $this->unit('U-4', $this->marina)->id]);
        $payment = $this->payment($contract, ['project_id' => $this->harbor->id]);

        $this->assertSame('Harbor Phase 2', $this->row(PaymentExporter::class, $payment)['project.name']);
    }

    // ── Unit, expense and lead ───────────────────────────────────────────────

    public function test_a_unit_without_its_own_project_reports_the_project_of_its_property(): void
    {
        $this->attach($this->property, $this->marina);

        $this->assertSame('Marina Phase 1', $this->row(UnitExporter::class, $this->unit('U-5', null))['project.name']);
    }

    public function test_an_expense_reports_the_project_of_its_unit_then_of_its_property(): void
    {
        $viaUnit = $this->expense(['unit_id' => $this->unit('U-6', $this->harbor)->id]);
        $this->assertSame('Harbor Phase 2', $this->row(ExpenseExporter::class, $viaUnit)['project']);

        $this->attach($this->property, $this->marina);
        $viaProperty = $this->expense(['property_id' => $this->property->id]);
        $this->assertSame('Marina Phase 1', $this->row(ExpenseExporter::class, $viaProperty)['project']);

        $this->assertNull($this->row(ExpenseExporter::class, $this->expense([]))['project']);
    }

    public function test_a_lead_lists_each_project_it_asked_about_once(): void
    {
        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'name' => 'Sara Haddad', 'phone' => '+962790000000',
            'status' => 'active', 'first_source' => 'whatsapp', 'created_by' => $this->actor->id,
        ]);
        $pipeline = CrmPipeline::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'name' => 'Default', 'is_active' => true, 'is_default' => true]);
        $stage = CrmPipelineStage::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'pipeline_id' => $pipeline->id, 'code' => 'new', 'label' => 'New', 'stage_type' => 'open', 'position' => 1, 'is_active' => true,
        ]);
        foreach ([$this->marina, $this->harbor, $this->marina] as $project) {
            Opportunity::withoutGlobalScopes()->create([
                'company_id' => $this->company->id, 'lead_id' => $lead->id, 'project_id' => $project->id,
                'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'type' => 'sale', 'created_by' => $this->actor->id,
            ]);
        }

        $this->assertSame('Marina Phase 1, Harbor Phase 2', $this->row(LeadExporter::class, $lead)['projects']);

        $quiet = Lead::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'name' => 'No Interest', 'phone' => '+962790000001',
            'status' => 'active', 'first_source' => 'whatsapp', 'created_by' => $this->actor->id,
        ]);
        $this->assertNull($this->row(LeadExporter::class, $quiet)['projects']);
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function project(string $name): Project
    {
        return Project::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'name' => $name, 'project_type' => 'residential', 'currency' => 'USD', 'status' => 'planning',
        ]);
    }

    private function unit(string $number, ?Project $project): Unit
    {
        return Unit::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'property_id' => $this->property->id, 'project_id' => $project?->id,
            'unit_number' => $number, 'status' => 'draft', 'type' => 'apartment',
        ]);
    }

    private function attach(Property $property, Project $project, bool $detached = false): void
    {
        ProjectProperty::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'project_id' => $project->id, 'property_id' => $property->id, 'role' => 'primary',
            'attached_at' => $detached ? now()->subMonths(2) : now()->subDay(), 'detached_at' => $detached ? now()->subMonth() : null,
        ]);
    }

    private function contract(array $links): Contract
    {
        return Contract::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'contract_number' => 'CNT-'.str()->uuid(), 'contract_type' => ContractType::Sale,
            'status' => ContractStatus::Active, 'created_by' => $this->actor->id,
        ] + $links);
    }

    private function payment(Contract $contract, array $extra = []): Payment
    {
        $party = Party::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'type' => 'person', 'name' => 'Buyer', 'is_active' => true]);

        return Payment::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'contract_id' => $contract->id, 'party_id' => $party->id,
            'direction' => 'incoming', 'payment_scope' => 'contract_receipt', 'idempotency_key' => (string) str()->uuid(),
            'amount' => 100, 'currency' => 'USD', 'project_amount' => 100, 'payment_date' => '2026-11-01',
            'status' => 'completed', 'recorded_by' => $this->actor->id,
        ] + $extra);
    }

    private function expense(array $links): Expense
    {
        return Expense::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'created_by' => $this->actor->id, 'title' => 'Repair', 'category' => 'maintenance',
            'amount' => 10, 'currency' => 'USD', 'status' => 'paid', 'expense_date' => '2026-11-02',
        ] + $links);
    }

    /**
     * Runs the exporter as the queued job does: through modifyQuery() (so an eager-load path
     * that does not exist fails here) with every column switched on.
     *
     * @param class-string<Exporter> $exporterClass
     * @return array<string, string|null>
     */
    private function row(string $exporterClass, Model $record): array
    {
        $columns = collect($exporterClass::getColumns())->map->getName()->all();
        $loaded = $exporterClass::modifyQuery($record::query()->withoutGlobalScopes())->whereKey($record->getKey())->firstOrFail();
        $export = Export::create([
            'file_disk' => 'local', 'file_name' => 'link', 'exporter' => $exporterClass, 'total_rows' => 1, 'user_id' => $this->actor->id,
        ]);

        return array_combine($columns, (new $exporterClass($export, array_fill_keys($columns, true), []))($loaded));
    }
}
