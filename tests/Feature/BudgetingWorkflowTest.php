<?php

namespace Tests\Feature;

use App\Models\{ActualCost,Company,FinancialCommitment,Party,Project,User};
use App\Services\BudgetingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BudgetingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function permissions(): array
    {
        return ['create_project::budget','update_project::budget','submit_project_budget','approve_project_budget','reject_project_budget','create_budget_revision','create_budget_category','update_budget_category','create_budget_item','update_budget_item','create_financial::commitment','update_financial::commitment','commit_financial_commitment','create_unbudgeted_commitment','approve_over_budget_commitment','release_financial_commitment','cancel_financial_commitment','create_financial_commitment_amendment','approve_financial_commitment_amendment','create_actual::cost','update_actual::cost','submit_actual_cost','approve_actual_cost','create_actual_cost_correction','create_payment','record_payment','allocate_payment'];
    }
    private function actor(Company $company, ?array $permissions=null): User { $user=User::factory()->create(['company_id'=>$company->id]); foreach($permissions ?? $this->permissions() as $p)$user->givePermissionTo(Permission::findOrCreate($p,'web')); return $user; }
    private function project(Company $company): Project { return Project::withoutGlobalScopes()->create(['company_id'=>$company->id,'name'=>'Palm Heights','project_type'=>'residential','currency'=>'USD']); }
    private function party(Company $company,string $name='Atlas'): Party { return Party::withoutGlobalScopes()->create(['company_id'=>$company->id,'type'=>'company','name'=>$name,'is_active'=>true]); }

    private function approvedItem(BudgetingService $service,User $actor,Project $project): array
    {
        $budget=$service->createBudget($actor,$project,['name'=>'Initial']);
        $category=$service->addCategory($actor,$budget,['name'=>'Design']);
        $item=$service->addItem($actor,$category,['name'=>'Architecture','planned_amount'=>1000]);
        $service->submitBudget($actor,$budget); $service->approveBudget($actor,$budget->fresh());
        return [$budget->fresh(),$item->fresh()];
    }

    public function test_full_planned_committed_actual_paid_chain_uses_project_currency_snapshots(): void
    {
        $company=Company::create(['name'=>'A','email'=>'a@test.test']); $actor=$this->actor($company); $project=$this->project($company); $vendor=$this->party($company); $service=app(BudgetingService::class); [, $item]=$this->approvedItem($service,$actor,$project);
        $commitment=$service->createCommitment($actor,$project,['budget_item_id'=>$item->id,'party_id'=>$vendor->id,'description'=>'Architectural services','amount'=>50000,'currency'=>'ILS','budget_amount'=>900]);
        $service->commit($actor,$commitment);
        $cost=$service->createActualCost($actor,$project,['financial_commitment_id'=>$commitment->id,'party_id'=>$vendor->id,'name'=>'Invoice 1','amount'=>50000,'currency'=>'ILS','budget_amount'=>900,'incurred_at'=>'2026-10-06']);
        $service->submitActualCost($actor,$cost); $service->approveActualCost($actor,$cost->fresh());
        $payment=$service->recordPayment($actor,$project,['party_id'=>$vendor->id,'amount'=>50000,'currency'=>'ILS','project_amount'=>920,'payment_date'=>'2026-10-06']);
        $allocation=$service->allocatePayment($actor,$payment,$cost->fresh(),['payment_amount'=>50000,'actual_cost_amount'=>50000,'project_amount'=>920]);
        $this->assertSame('committed',$commitment->fresh()->status); $this->assertSame('approved',$cost->fresh()->status); $this->assertSame('completed',$payment->status); $this->assertSame('ILS',$allocation->payment->currency); $this->assertSame(920.0,(float)$allocation->project_amount);
        $this->assertSame(920.0,(float)$project->payments()->where('status','completed')->join('payment_allocations','payments.id','=','payment_allocations.payment_id')->sum('payment_allocations.project_amount'));
    }

    public function test_revision_preserves_financial_history_by_stable_budget_line(): void
    {
        $company=Company::create(['name'=>'A','email'=>'a@test.test']);$actor=$this->actor($company);$project=$this->project($company);$vendor=$this->party($company);$service=app(BudgetingService::class);[$budget,$item]=$this->approvedItem($service,$actor,$project);
        $commitment=$service->createCommitment($actor,$project,['budget_item_id'=>$item->id,'party_id'=>$vendor->id,'description'=>'Design','amount'=>800,'currency'=>'USD','budget_amount'=>800]);$service->commit($actor,$commitment);
        $revision=$service->createRevision($actor,$budget);$clone=$revision->categories()->with('items')->first()->items->first();$this->assertSame($item->budget_line_id,$clone->budget_line_id);$service->submitBudget($actor,$revision);$service->approveBudget($actor,$revision->fresh());
        $this->assertSame($item->id,$commitment->fresh()->budget_item_id);$this->assertSame($item->budget_line_id,$commitment->fresh()->budget_line_id);$this->assertSame('superseded',$budget->fresh()->status);
    }

    public function test_allocation_rejects_cross_project_vendor_unapproved_and_overallocation(): void
    {
        $company=Company::create(['name'=>'A','email'=>'a@test.test']);$other=Company::create(['name'=>'B','email'=>'b@test.test']);$actor=$this->actor($company);$project=$this->project($company);$vendor=$this->party($company);$otherVendor=$this->party($company,'Other');$service=app(BudgetingService::class);[,$item]=$this->approvedItem($service,$actor,$project);
        $cost=$service->createActualCost($actor,$project,['budget_item_id'=>$item->id,'party_id'=>$vendor->id,'name'=>'Invoice','amount'=>100,'currency'=>'USD','budget_amount'=>100,'incurred_at'=>'2026-10-06']);$payment=$service->recordPayment($actor,$project,['party_id'=>$vendor->id,'amount'=>100,'currency'=>'USD','project_amount'=>100,'payment_date'=>'2026-10-06']);
        try{$service->allocatePayment($actor,$payment,$cost,['payment_amount'=>100,'actual_cost_amount'=>100,'project_amount'=>100]);$this->fail('Unapproved cost was allocated.');}catch(ValidationException){$this->assertDatabaseCount('payment_allocations',0);}
        $service->submitActualCost($actor,$cost);$service->approveActualCost($actor,$cost->fresh());
        $wrong=$service->recordPayment($actor,$project,['party_id'=>$otherVendor->id,'amount'=>100,'currency'=>'USD','project_amount'=>100,'payment_date'=>'2026-10-06']);
        try{$service->allocatePayment($actor,$wrong,$cost->fresh(),['payment_amount'=>100,'actual_cost_amount'=>100,'project_amount'=>100]);$this->fail('Cross-vendor allocation was accepted.');}catch(ValidationException){$this->assertDatabaseCount('payment_allocations',0);}
        $service->allocatePayment($actor,$payment,$cost->fresh(),['payment_amount'=>100,'actual_cost_amount'=>100,'project_amount'=>100]);
        try{$service->allocatePayment($actor,$payment,$cost->fresh(),['payment_amount'=>1,'actual_cost_amount'=>1,'project_amount'=>1]);$this->fail('Over-allocation was accepted.');}catch(ValidationException){$this->assertDatabaseCount('payment_allocations',1);}
        $this->assertNotNull($other);
    }

    public function test_cross_company_and_unauthorized_financial_input_is_rejected(): void
    {
        $company=Company::create(['name'=>'A','email'=>'a@test.test']);$other=Company::create(['name'=>'B','email'=>'b@test.test']);$actor=$this->actor($company);$project=$this->project($company);$service=app(BudgetingService::class);[,$item]=$this->approvedItem($service,$actor,$project);
        try{$service->createCommitment($actor,$project,['budget_item_id'=>$item->id,'party_id'=>$this->party($other)->id,'description'=>'Bad','amount'=>1,'currency'=>'USD','budget_amount'=>1]);$this->fail('Cross-company party accepted.');}catch(ValidationException){$this->assertDatabaseCount('financial_commitments',0);}
        $this->expectException(AuthorizationException::class);$service->createBudget($this->actor($company,[]),$project,[]);
    }

    public function test_financial_commitment_requires_an_item_from_its_current_approved_project_budget(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $actor = $this->actor($company);
        $project = $this->project($company);
        $vendor = $this->party($company);
        $service = app(BudgetingService::class);

        $draftBudget = $service->createBudget($actor, $project, ['name' => 'Draft']);
        $draftCategory = $service->addCategory($actor, $draftBudget, ['name' => 'Construction']);
        $draftItem = $service->addItem($actor, $draftCategory, ['name' => 'Concrete', 'planned_amount' => 1000]);

        try {
            $service->createCommitment($actor, $project, ['budget_item_id' => $draftItem->id, 'party_id' => $vendor->id, 'description' => 'Draft budget commitment', 'amount' => 100, 'currency' => 'USD', 'budget_amount' => 100]);
            $this->fail('A draft-budget item was accepted for a commitment.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('financial_commitments', 0);
        }

        $otherProject = $this->project($company);
        [, $otherItem] = $this->approvedItem($service, $actor, $otherProject);

        try {
            $service->createCommitment($actor, $project, ['budget_item_id' => $otherItem->id, 'party_id' => $vendor->id, 'description' => 'Other project commitment', 'amount' => 100, 'currency' => 'USD', 'budget_amount' => 100]);
            $this->fail('Another project’s budget item was accepted for a commitment.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('financial_commitments', 0);
        }
    }

    public function test_only_a_draft_commitment_can_be_edited(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $actor = $this->actor($company);
        $project = $this->project($company);
        $vendor = $this->party($company);
        $service = app(BudgetingService::class);
        [, $item] = $this->approvedItem($service, $actor, $project);

        $commitment = $service->createCommitment($actor, $project, ['budget_item_id' => $item->id, 'party_id' => $vendor->id, 'description' => 'Original', 'reference_number' => 'PO-001', 'amount' => 100, 'currency' => 'USD', 'budget_amount' => 100]);
        $updated = $service->updateCommitment($actor, $commitment, ['budget_item_id' => $item->id, 'party_id' => $vendor->id, 'description' => 'Updated', 'reference_number' => 'PO-002', 'amount' => 120, 'currency' => 'USD', 'budget_amount' => 120]);

        $this->assertSame('Updated', $updated->description);
        $this->assertSame('PO-002', $updated->reference_number);
        $this->assertSame(120.0, (float) $updated->amount);

        $service->commit($actor, $updated);
        $this->expectException(AuthorizationException::class);
        $service->updateCommitment($actor, $updated->fresh(), ['budget_item_id' => $item->id, 'party_id' => $vendor->id, 'description' => 'Blocked', 'amount' => 120, 'currency' => 'USD', 'budget_amount' => 120]);
    }

    public function test_actual_cost_rejects_a_draft_or_released_financial_commitment(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $actor = $this->actor($company);
        $project = $this->project($company);
        $vendor = $this->party($company);
        $service = app(BudgetingService::class);
        [, $item] = $this->approvedItem($service, $actor, $project);
        $commitment = $service->createCommitment($actor, $project, ['budget_item_id' => $item->id, 'party_id' => $vendor->id, 'description' => 'Concrete supply', 'amount' => 100, 'currency' => 'USD', 'budget_amount' => 100]);

        foreach (['draft', 'released'] as $status) {
            if ($status === 'released') {
                $service->commit($actor, $commitment);
                $service->releaseCommitment($actor, $commitment->fresh(), 'Supplier released the scope');
            }

            try {
                $service->createActualCost($actor, $project, ['financial_commitment_id' => $commitment->id, 'party_id' => $vendor->id, 'name' => 'Invoice', 'amount' => 100, 'currency' => 'USD', 'budget_amount' => 100, 'incurred_at' => '2026-10-06']);
                $this->fail("A {$status} commitment accepted an actual cost.");
            } catch (ValidationException) {
                $this->assertDatabaseCount('actual_costs', 0);
            }
        }
    }

    public function test_only_a_draft_actual_cost_can_be_edited(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $actor = $this->actor($company);
        $project = $this->project($company);
        $vendor = $this->party($company);
        $service = app(BudgetingService::class);
        [, $item] = $this->approvedItem($service, $actor, $project);
        $cost = $service->createActualCost($actor, $project, ['budget_item_id' => $item->id, 'party_id' => $vendor->id, 'name' => 'Original invoice', 'amount' => 100, 'currency' => 'USD', 'budget_amount' => 100, 'incurred_at' => '2026-10-06']);

        $updated = $service->updateActualCost($actor, $cost, ['budget_item_id' => $item->id, 'party_id' => $vendor->id, 'name' => 'Corrected invoice', 'amount' => 120, 'currency' => 'USD', 'budget_amount' => 120, 'incurred_at' => '2026-10-06']);
        $this->assertSame('Corrected invoice', $updated->name);
        $this->assertSame(120.0, (float) $updated->amount);

        $service->submitActualCost($actor, $updated);
        $this->expectException(AuthorizationException::class);
        $service->updateActualCost($actor, $updated->fresh(), ['budget_item_id' => $item->id, 'party_id' => $vendor->id, 'name' => 'Blocked', 'amount' => 120, 'currency' => 'USD', 'budget_amount' => 120, 'incurred_at' => '2026-10-06']);
    }

    public function test_correction_is_new_draft_and_does_not_mutate_approved_cost(): void
    {
        $company=Company::create(['name'=>'A','email'=>'a@test.test']);$actor=$this->actor($company);$project=$this->project($company);$vendor=$this->party($company);$service=app(BudgetingService::class);[,$item]=$this->approvedItem($service,$actor,$project);
        $cost=$service->createActualCost($actor,$project,['budget_item_id'=>$item->id,'party_id'=>$vendor->id,'name'=>'Invoice','amount'=>100,'currency'=>'USD','budget_amount'=>100,'incurred_at'=>'2026-10-06']);$service->submitActualCost($actor,$cost);$service->approveActualCost($actor,$cost->fresh());
        $correction=$service->correctActualCost($actor,$cost->fresh(),['amount'=>-10,'budget_amount'=>-10,'correction_reason'=>'Invoice discount']);
        $this->assertSame('draft',$correction->status);$this->assertSame($cost->id,$correction->correction_of_actual_cost_id);$this->assertSame(100.0,(float)$cost->fresh()->amount);
    }

    public function test_only_one_draft_budget_and_same_budget_category_parent_are_allowed(): void
    {
        $company=Company::create(['name'=>'A','email'=>'a@test.test']);$actor=$this->actor($company);$project=$this->project($company);$service=app(BudgetingService::class);
        $first=$service->createBudget($actor,$project,['name'=>'V1']);
        try{$service->createBudget($actor,$project,['name'=>'V2']);$this->fail('A second draft budget was created.');}catch(ValidationException){$this->assertDatabaseCount('project_budgets',1);}
        $otherProject=$this->project($company);$otherBudget=$service->createBudget($actor,$otherProject,['name'=>'Other']);$foreignCategory=$service->addCategory($actor,$otherBudget,['name'=>'Foreign']);
        try{$service->addCategory($actor,$first,['name'=>'Invalid','parent_id'=>$foreignCategory->id]);$this->fail('A cross-budget parent was accepted.');}catch(ValidationException){$this->assertDatabaseCount('budget_categories',1);}
    }

    public function test_unbudgeted_and_over_budget_commitments_require_explicit_permissions_and_reason(): void
    {
        $company=Company::create(['name'=>'A','email'=>'a@test.test']);$all=$this->permissions();$withoutUnbudgeted=array_values(array_diff($all,['create_unbudgeted_commitment']));$actor=$this->actor($company,$withoutUnbudgeted);$project=$this->project($company);$vendor=$this->party($company);$service=app(BudgetingService::class);
        $this->expectException(AuthorizationException::class);$service->createCommitment($actor,$project,['party_id'=>$vendor->id,'description'=>'Unbudgeted','amount'=>1,'currency'=>'USD','budget_amount'=>1]);
    }

    public function test_over_budget_commitment_needs_a_reason_and_committed_obligation_uses_amendment_or_release(): void
    {
        $company=Company::create(['name'=>'A','email'=>'a@test.test']);$actor=$this->actor($company);$project=$this->project($company);$vendor=$this->party($company);$service=app(BudgetingService::class);[,$item]=$this->approvedItem($service,$actor,$project);
        $commitment=$service->createCommitment($actor,$project,['budget_item_id'=>$item->id,'party_id'=>$vendor->id,'description'=>'Over','amount'=>1100,'currency'=>'USD','budget_amount'=>1100]);
        try{$service->commit($actor,$commitment);$this->fail('Over-budget commitment was committed without reason.');}catch(ValidationException){$this->assertSame('draft',$commitment->fresh()->status);}
        $service->commit($actor,$commitment,'Approved contingency use');$amendment=$service->requestCommitmentAmendment($actor,$commitment->fresh(),['amount_change'=>50,'budget_amount_change'=>50,'reason'=>'Scope increase']);$this->assertSame('pending_approval',$amendment->status);$service->approveCommitmentAmendment($actor,$amendment->fresh());$this->assertSame('approved',$amendment->fresh()->status);$service->releaseCommitment($actor,$commitment->fresh(),'Supplier released scope');$this->assertSame('released',$commitment->fresh()->status);
    }

    public function test_project_currency_locks_after_financial_history_exists(): void
    {
        $company=Company::create(['name'=>'A','email'=>'a@test.test']);$actor=$this->actor($company);$project=$this->project($company);$service=app(BudgetingService::class);$service->createBudget($actor,$project,['name'=>'Initial']);
        $project->currency='ILS';$this->expectException(ValidationException::class);$project->save();
    }

    public function test_actual_cost_payment_and_correction_dates_cannot_be_future_dated(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.test']);
        $actor = $this->actor($company);
        $project = $this->project($company);
        $vendor = $this->party($company);
        $service = app(BudgetingService::class);
        [, $item] = $this->approvedItem($service, $actor, $project);
        $futureDate = today()->addDay()->toDateString();

        try {
            $service->createActualCost($actor, $project, ['budget_item_id' => $item->id, 'party_id' => $vendor->id, 'name' => 'Future invoice', 'amount' => 100, 'currency' => 'USD', 'budget_amount' => 100, 'incurred_at' => $futureDate]);
            $this->fail('A future actual-cost date was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('actual_costs', 0);
        }

        try {
            $service->recordPayment($actor, $project, ['party_id' => $vendor->id, 'amount' => 100, 'currency' => 'USD', 'project_amount' => 100, 'payment_date' => $futureDate]);
            $this->fail('A future payment date was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('payments', 0);
        }

        $cost = $service->createActualCost($actor, $project, ['budget_item_id' => $item->id, 'party_id' => $vendor->id, 'name' => 'Invoice', 'amount' => 100, 'currency' => 'USD', 'budget_amount' => 100, 'incurred_at' => today()->toDateString()]);
        $service->submitActualCost($actor, $cost);
        $service->approveActualCost($actor, $cost->fresh());

        try {
            $service->correctActualCost($actor, $cost->fresh(), ['amount' => -10, 'budget_amount' => -10, 'correction_reason' => 'Correction', 'incurred_at' => $futureDate]);
            $this->fail('A future correction date was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('actual_costs', 1);
        }
    }

    public function test_super_admin_budget_children_inherit_the_parent_company_without_a_user_company_context(): void
    {
        $company = Company::create(['name' => 'Target Company', 'email' => 'target@test.test']);
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->syncRoles([Role::findOrCreate('super_admin', 'web')]);

        foreach ($this->permissions() as $permission) {
            $superAdmin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->actingAs($superAdmin);

        $project = $this->project($company);
        $service = app(BudgetingService::class);
        $budget = $service->createBudget($superAdmin, $project, ['name' => 'Super Admin budget']);
        $category = $service->addCategory($superAdmin, $budget, ['name' => 'Infrastructure']);
        $item = $service->addItem($superAdmin, $category, ['name' => 'Roadworks', 'planned_amount' => 5000]);

        $this->assertSame($company->id, $budget->company_id);
        $this->assertSame($company->id, $category->company_id);
        $this->assertSame($company->id, $item->company_id);
        $this->assertSame($company->id, $item->line->company_id);
    }
}
