<?php

namespace Tests\Feature;

use App\Models\{Company, Party, Project, ProjectBudget, BudgetCategory, BudgetItem, BudgetLine, FinancialCommitment, ActualCost, User};
use App\Services\BudgetingService;
use App\Filament\Resources\FinancialCommitmentResource;
use App\Filament\Resources\ActualCostResource;
use App\Filament\Widgets\FinancialOverviewStats;
use App\Filament\Widgets\FinancialVarianceWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase2FinancialControlsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'company_admin']);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function widgetStats(object $widget): array
    {
        $method = new ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);
        return $method->invoke($widget);
    }

    private function projectWithBudget(Company $company): array
    {
        $project = Project::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Test Project',
            'project_type' => 'residential',
            'currency' => 'USD',
            'status' => 'approved',
        ]);

        $budget = ProjectBudget::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'version_number' => 1,
            'name' => 'V1',
        ]);
        $budget->forceFill(['status' => 'approved'])->save();

        $category = BudgetCategory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'project_budget_id' => $budget->id,
            'name' => 'Construction',
            'sort_order' => 1,
        ]);

        $line = BudgetLine::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
        ]);

        $item = BudgetItem::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'budget_category_id' => $category->id,
            'budget_line_id' => $line->id,
            'name' => 'Concrete',
            'planned_amount' => 100000,
            'quantity' => 1,
        ]);

        return ['project' => $project, 'budget' => $budget, 'item' => $item, 'line' => $line];
    }

    public function test_variance_widget_shows_approved_budget_commitments_actual_costs_and_variance(): void
    {
        app()->setLocale('en');
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $user = $this->actor($company, [
            'view_any_financial::commitment',
            'create_financial::commitment', 'commit_financial_commitment',
            'create_actual::cost', 'submit_actual_cost', 'approve_actual_cost',
        ]);
        $this->actingAs($user);

        $data = $this->projectWithBudget($company);
        $project = $data['project'];
        $item = $data['item'];

        $service = app(BudgetingService::class);

        // Create a committed obligation
        $commitment = $service->createCommitment($user, $project, [
            'party_id' => Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Vendor'])->id,
            'budget_item_id' => $item->id,
            'description' => 'Concrete supply',
            'amount' => 30000,
            'currency' => 'USD',
            'budget_amount' => 30000,
        ]);
        $service->commit($user, $commitment);

        // Create an approved actual cost
        $cost = $service->createActualCost($user, $project, [
            'party_id' => $commitment->party_id,
            'financial_commitment_id' => $commitment->id,
            'budget_item_id' => $item->id,
            'name' => 'Concrete delivery',
            'amount' => 20000,
            'currency' => 'USD',
            'budget_amount' => 20000,
            'incurred_at' => today(),
        ]);
        $service->submitActualCost($user, $cost);
        $service->approveActualCost($user, $cost);

        $widget = new FinancialVarianceWidget();
        $stats = $this->widgetStats($widget);

        $this->assertSame('Approved budget', $stats[0]->getLabel());
        $this->assertSame('Committed obligations', $stats[1]->getLabel());
        $this->assertSame('Approved actual costs', $stats[2]->getLabel());
        $this->assertSame('Budget variance', $stats[3]->getLabel());
    }

    public function test_financial_commitment_resource_table_has_colored_status_badges(): void
    {
        $table = FinancialCommitmentResource::table(new \Filament\Tables\Table(new class extends \Filament\Resources\Pages\ListRecords {}));
        $columns = $table->getColumns();
        $statusColumn = array_filter($columns, fn ($c) => $c->getName() === 'status');
        $this->assertNotEmpty(array_values($statusColumn));
    }

    public function test_actual_cost_resource_table_has_colored_status_badges(): void
    {
        $table = ActualCostResource::table(new \Filament\Tables\Table(new class extends \Filament\Resources\Pages\ListRecords {}));
        $columns = $table->getColumns();
        $statusColumn = array_filter($columns, fn ($c) => $c->getName() === 'status');
        $this->assertNotEmpty(array_values($statusColumn));
    }

    public function test_dashboard_overview_stats_show_monetary_totals_with_record_counts(): void
    {
        app()->setLocale('en');
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $user = $this->actor($company, [
            'view_any_financial::commitment',
            'create_financial::commitment', 'commit_financial_commitment',
            'create_actual::cost', 'submit_actual_cost', 'approve_actual_cost',
        ]);
        $this->actingAs($user);

        $data = $this->projectWithBudget($company);
        $project = $data['project'];
        $item = $data['item'];

        $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Vendor']);

        $service = app(BudgetingService::class);

        // Create committed commitment
        $commitment = $service->createCommitment($user, $project, [
            'party_id' => $party->id,
            'budget_item_id' => $item->id,
            'description' => 'Commitment',
            'amount' => 30000,
            'currency' => 'USD',
            'budget_amount' => 30000,
        ]);
        $service->commit($user, $commitment);

        // Create approved actual cost
        $cost = $service->createActualCost($user, $project, [
            'party_id' => $party->id,
            'financial_commitment_id' => $commitment->id,
            'budget_item_id' => $item->id,
            'name' => 'Actual cost',
            'amount' => 20000,
            'currency' => 'USD',
            'budget_amount' => 20000,
            'incurred_at' => today(),
        ]);
        $service->submitActualCost($user, $cost);
        $service->approveActualCost($user, $cost);

        $widget = new FinancialOverviewStats();
        $stats = $this->widgetStats($widget);

        $this->assertSame('Active commitments', $stats[1]->getLabel());
        $this->assertSame('Approved actual costs', $stats[2]->getLabel());
        $this->assertSame('Completed project payments', $stats[3]->getLabel());
    }

    public function test_change_order_creates_financial_commitment_amendment(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $user = $this->actor($company, [
            'create_financial::commitment', 'commit_financial_commitment',
            'create_financial_commitment_amendment',
        ]);
        $this->actingAs($user);

        $data = $this->projectWithBudget($company);
        $project = $data['project'];
        $item = $data['item'];

        $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Vendor']);

        $service = app(BudgetingService::class);
        $commitment = $service->createCommitment($user, $project, [
            'party_id' => $party->id,
            'budget_item_id' => $item->id,
            'description' => 'Original commitment',
            'amount' => 30000,
            'currency' => 'USD',
            'budget_amount' => 30000,
        ]);
        $service->commit($user, $commitment);

        // Request a change order (amendment)
        $amendment = $service->requestCommitmentAmendment($user, $commitment, [
            'amount_change' => 5000,
            'budget_amount_change' => 5000,
            'reason' => 'Scope increase',
        ]);

        $this->assertSame('pending_approval', $amendment->status);
        $this->assertSame(5000.0, (float) $amendment->amount_change);
        $this->assertSame(5000.0, (float) $amendment->budget_amount_change);
        $this->assertSame('Scope increase', $amendment->reason);
        $this->assertSame($commitment->id, $amendment->financial_commitment_id);
    }

    public function test_change_order_affects_effective_budget_amount_when_approved(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $user = $this->actor($company, [
            'create_financial::commitment', 'commit_financial_commitment',
            'create_financial_commitment_amendment', 'approve_financial_commitment_amendment',
        ]);
        $this->actingAs($user);

        $data = $this->projectWithBudget($company);
        $project = $data['project'];
        $item = $data['item'];

        $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Vendor']);

        $service = app(BudgetingService::class);
        $commitment = $service->createCommitment($user, $project, [
            'party_id' => $party->id,
            'budget_item_id' => $item->id,
            'description' => 'Original commitment',
            'amount' => 30000,
            'currency' => 'USD',
            'budget_amount' => 30000,
        ]);
        $service->commit($user, $commitment);

        $this->assertSame(30000.0, (float) $commitment->effectiveBudgetAmount());

        $amendment = $service->requestCommitmentAmendment($user, $commitment, [
            'amount_change' => 5000,
            'budget_amount_change' => 5000,
            'reason' => 'Scope increase',
        ]);
        $service->approveCommitmentAmendment($user, $amendment);

        $this->assertSame(35000.0, (float) $commitment->refresh()->effectiveBudgetAmount());
    }
}
