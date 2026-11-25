<?php

namespace Tests\Feature;

use App\Enums\{ContractStatus, ContractType};
use App\Filament\Exports\ProjectExporter;
use App\Filament\Widgets\FinancialVarianceWidget;
use App\Models\{BudgetCategory, BudgetItem, BudgetLine, Company, Contract, CrmPipeline, CrmPipelineStage, Lead, Location, Opportunity, Party, Project, ProjectBudget, ProjectBuilding, ProjectDesignPackage, ProjectMember, ProjectProperty, Property, Unit, User};
use App\Services\BudgetingService;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A company's project is the top-level lens: one row should say how sales stand, who works on
 * it, how much demand it has and how the money compares to the approved budget. Those money
 * figures are checked against the dashboard widget so the file and the screen cannot disagree.
 */
class ProjectAnalysisExportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');

        $this->company = Company::create(['name' => 'Analysis Co', 'email' => 'analysis@example.test', 'is_active' => true]);
        $this->actor = User::factory()->create(['company_id' => $this->company->id, 'role' => 'company_admin']);
        foreach (['create_financial::commitment', 'commit_financial_commitment', 'create_actual::cost', 'submit_actual_cost', 'approve_actual_cost'] as $permission) {
            $this->actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($this->actor);
    }

    public function test_a_project_row_carries_its_sales_team_demand_and_money(): void
    {
        $project = $this->project('Marina Phase 1');
        $this->fillProject($project);

        $row = $this->row($project);

        $this->assertSame('Marina Phase 1', $row['name']);
        $this->assertSame('4', $row['units_count']);
        $this->assertSame('1', $row['sold_units_count']);
        $this->assertSame('1', $row['reserved_units_count']);
        $this->assertSame('1', $row['available_units_count']);
        $this->assertSame('2', $row['contracts_count']);
        $this->assertSame('1', $row['active_contracts_count']);
        $this->assertSame('1', $row['opportunities_count']);
        $this->assertSame('1', $row['active_project_properties_count']);
        $this->assertSame('1', $row['buildings_count']);
        $this->assertSame('1', $row['members_count']);
        $this->assertSame('1', $row['design_packages_count']);
        $this->assertSame('Marina Tower', $row['property_names']);

        $this->assertSame('100000', $row['approved_budget']);
        $this->assertSame('30000', $row['committed_amount']);
        $this->assertSame('20000', $row['approved_actual_costs']);
        $this->assertSame('50000', $row['budget_variance']);
    }

    public function test_the_exported_money_matches_the_dashboard_widget_for_the_same_project(): void
    {
        $project = $this->project('Marina Phase 1');
        $this->fillProject($project);

        $widget = new FinancialVarianceWidget;
        $widget->record = $project;
        $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);
        $row = $this->row($project);

        foreach (['approved_budget' => 0, 'committed_amount' => 1, 'approved_actual_costs' => 2, 'budget_variance' => 3] as $column => $index) {
            $this->assertSame(
                number_format((float) $row[$column], 2).' USD',
                (string) $stats[$index]->getValue(),
                "$column must read the same in the file and on the dashboard",
            );
        }
    }

    public function test_a_project_with_nothing_yet_reports_zeroes_not_blanks(): void
    {
        $row = $this->row($this->project('Empty Project'));

        foreach (['units_count', 'contracts_count', 'members_count', 'approved_budget', 'committed_amount', 'approved_actual_costs', 'budget_variance'] as $column) {
            $this->assertSame('0', $row[$column], "$column should be 0 for an empty project");
        }
        $this->assertNull($row['property_names']);
    }

    public function test_a_budget_that_is_not_approved_is_left_out_of_the_total(): void
    {
        $project = $this->project('Draft Budget Project');
        $this->budgetFor($project, status: 'draft', amount: 5000);

        $this->assertSame('0', $this->row($project)['approved_budget']);
    }

    public function test_each_project_is_totalled_on_its_own(): void
    {
        $a = $this->project('Project A');
        $b = $this->project('Project B');
        $this->budgetFor($a, status: 'approved', amount: 1000);
        $this->budgetFor($b, status: 'approved', amount: 7000);

        $this->assertSame('1000', $this->row($a)['approved_budget']);
        $this->assertSame('7000', $this->row($b)['approved_budget']);
    }

    public function test_every_numeric_column_is_a_real_column_so_excel_can_total_it(): void
    {
        $names = collect(ProjectExporter::getColumns())->map->getName()->all();

        foreach (ProjectExporter::numericColumns() as $numeric) {
            $this->assertContains($numeric, $names, "$numeric is declared numeric but is not an export column");
        }
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function project(string $name): Project
    {
        return Project::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'name' => $name, 'project_type' => 'residential', 'currency' => 'USD', 'status' => 'approved',
        ]);
    }

    /** @return array{0: ProjectBudget, 1: BudgetItem} */
    private function budgetFor(Project $project, string $status, int $amount): array
    {
        $budget = ProjectBudget::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'project_id' => $project->id, 'version_number' => 1, 'name' => 'V1',
        ]);
        $budget->forceFill(['status' => $status])->save();
        $category = BudgetCategory::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'project_budget_id' => $budget->id, 'name' => 'Construction', 'sort_order' => 1,
        ]);
        $line = BudgetLine::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'project_id' => $project->id]);
        $item = BudgetItem::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'budget_category_id' => $category->id, 'budget_line_id' => $line->id,
            'name' => 'Concrete', 'planned_amount' => $amount, 'quantity' => 1,
        ]);

        return [$budget, $item];
    }

    private function fillProject(Project $project): void
    {
        $companyId = $this->company->id;
        $location = Location::withoutGlobalScopes()->create(['company_id' => $companyId, 'name' => 'Amman', 'type' => 'city']);
        $property = Property::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'location_id' => $location->id, 'name' => 'Marina Tower', 'address' => 'Sea Road',
        ]);
        ProjectProperty::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'project_id' => $project->id, 'property_id' => $property->id, 'role' => 'primary', 'attached_at' => now()->subDay(),
        ]);

        foreach (['sold', 'reserved', 'ready', 'draft'] as $i => $status) {
            Unit::withoutGlobalScopes()->create([
                'company_id' => $companyId, 'property_id' => $property->id, 'project_id' => $project->id,
                'unit_number' => 'U-'.($i + 1), 'status' => $status, 'type' => 'apartment',
            ]);
        }

        foreach ([ContractStatus::Active, ContractStatus::Draft] as $status) {
            Contract::withoutGlobalScopes()->create([
                'company_id' => $companyId, 'contract_number' => 'CNT-'.str()->uuid(), 'contract_type' => ContractType::Sale,
                'status' => $status, 'created_by' => $this->actor->id, 'project_id' => $project->id,
            ]);
        }

        ProjectBuilding::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'project_id' => $project->id, 'name' => 'Block A', 'code' => 'A', 'building_type' => 'residential', 'status' => 'planned',
        ]);
        ProjectMember::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'project_id' => $project->id, 'user_id' => $this->actor->id, 'role' => 'manager', 'started_at' => now()->subDay(),
        ]);
        ProjectDesignPackage::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'project_id' => $project->id, 'name' => 'Architecture', 'discipline' => 'architecture', 'created_by' => $this->actor->id,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'name' => 'Sara Haddad', 'phone' => '+962790000000', 'status' => 'active', 'first_source' => 'whatsapp', 'created_by' => $this->actor->id,
        ]);
        $pipeline = CrmPipeline::withoutGlobalScopes()->create(['company_id' => $companyId, 'name' => 'Default', 'is_active' => true, 'is_default' => true]);
        $stage = CrmPipelineStage::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'pipeline_id' => $pipeline->id, 'code' => 'new', 'label' => 'New', 'stage_type' => 'open', 'position' => 1, 'is_active' => true,
        ]);
        Opportunity::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'lead_id' => $lead->id, 'project_id' => $project->id,
            'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'type' => 'sale', 'created_by' => $this->actor->id,
        ]);

        [, $item] = $this->budgetFor($project, status: 'approved', amount: 100000);
        $service = app(BudgetingService::class);
        $vendor = Party::withoutGlobalScopes()->create(['company_id' => $companyId, 'type' => 'individual', 'name' => 'Vendor']);

        $commitment = $service->createCommitment($this->actor, $project, [
            'party_id' => $vendor->id, 'budget_item_id' => $item->id, 'description' => 'Concrete supply',
            'amount' => 30000, 'currency' => 'USD', 'budget_amount' => 30000,
        ]);
        $service->commit($this->actor, $commitment);

        $cost = $service->createActualCost($this->actor, $project, [
            'party_id' => $vendor->id, 'financial_commitment_id' => $commitment->id, 'budget_item_id' => $item->id,
            'name' => 'Concrete delivery', 'amount' => 20000, 'currency' => 'USD', 'budget_amount' => 20000, 'incurred_at' => today(),
        ]);
        $service->submitActualCost($this->actor, $cost);
        $service->approveActualCost($this->actor, $cost);
    }

    /** @return array<string, string|null> keyed by column name, every column switched on */
    private function row(Project $project): array
    {
        $columns = collect(ProjectExporter::getColumns())->map->getName()->all();
        $loaded = ProjectExporter::modifyQuery(Project::query()->withoutGlobalScopes())->whereKey($project->getKey())->firstOrFail();
        $export = Export::create([
            'file_disk' => 'local', 'file_name' => 'analysis', 'exporter' => ProjectExporter::class, 'total_rows' => 1, 'user_id' => $this->actor->id,
        ]);

        return array_combine($columns, (new ProjectExporter($export, array_fill_keys($columns, true), []))($loaded));
    }
}
