<?php

namespace App\Services;

use App\Models\{ActualCost, BudgetCategory, BudgetItem, BudgetLine, DesignPackageAssignment, FinancialCommitment, FinancialCommitmentAmendment, Party, Payment, PaymentAllocation, Project, ProjectBudget, ProjectDesignPackage, PropertyAcquisition, User};
use App\Notifications\ProjectWorkflowNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BudgetingService
{
    public function createBudget(User $actor, Project $project, array $attributes): ProjectBudget
    {
        $this->authorize($actor, 'create', new ProjectBudget(['company_id' => $project->company_id]));
        $this->assertProjectOpen($project);
        return DB::transaction(function () use ($actor, $project, $attributes) {
            $versions = ProjectBudget::withoutGlobalScopes()->where('project_id', $project->id)->lockForUpdate()->get();
            if ($versions->contains('status', 'draft'))
                $this->invalid('budget', 'This project already has a draft budget version.');
            $next = ((int) $versions->max('version_number')) + 1;
            return ProjectBudget::create(['company_id' => $project->company_id, 'project_id' => $project->id, 'version_number' => $next, 'name' => $attributes['name'] ?? null, 'notes' => $attributes['notes'] ?? null]);
        });
    }

    public function addCategory(User $actor, ProjectBudget $budget, array $attributes): BudgetCategory
    {
        $this->authorize($actor, 'update', $budget);
        $this->authorize($actor, 'create', new BudgetCategory(['company_id' => $budget->company_id]));
        $this->assertDraft($budget);
        if (!empty($attributes['parent_id'])) {
            $parent = BudgetCategory::withoutGlobalScopes()->findOrFail($attributes['parent_id']);
            if ((int) $parent->project_budget_id !== (int) $budget->id)
                $this->invalid('parent_id', 'The parent category must belong to this budget version.');
        }
        return BudgetCategory::create(array_merge(collect($attributes)->only(['parent_id', 'name', 'code', 'description', 'sort_order'])->all(), ['company_id' => $budget->company_id, 'project_budget_id' => $budget->id]));
    }

    public function addItem(User $actor, BudgetCategory $category, array $attributes): BudgetItem
    {
        $budget = $category->budget;
        $this->authorize($actor, 'update', $budget);
        $this->authorize($actor, 'create', new BudgetItem(['company_id' => $budget->company_id]));
        $this->assertDraft($budget);
        $this->positive($attributes['planned_amount'] ?? null, 'planned_amount');
        return DB::transaction(function () use ($category, $budget, $attributes) {
            $line = BudgetLine::create(['company_id' => $budget->company_id, 'project_id' => $budget->project_id]);
            $item = BudgetItem::create(array_merge(collect($attributes)->only(['code', 'name', 'description', 'planned_amount', 'quantity', 'unit', 'unit_cost', 'notes'])->all(), ['company_id' => $budget->company_id, 'budget_category_id' => $category->id, 'budget_line_id' => $line->id]));
            $line->update(['created_from_budget_item_id' => $item->id]);
            return $item;
        });
    }

    public function updateCategory(User $actor, ProjectBudget $budget, BudgetCategory $category, array $attributes): BudgetCategory
    {
        $this->assertCategoryBelongsToBudget($budget, $category);
        $this->authorize($actor, 'update', $budget);
        $this->authorize($actor, 'update', $category);
        $this->assertDraft($budget);

        if (! empty($attributes['parent_id'])) {
            $parent = BudgetCategory::withoutGlobalScopes()->findOrFail($attributes['parent_id']);
            $this->assertCategoryBelongsToBudget($budget, $parent);

            if ($parent->is($category) || $this->isCategoryDescendantOf($parent, $category)) {
                $this->invalid('parent_id', 'A category cannot be its own parent or a child of itself.');
            }
        }

        $category->update(collect($attributes)->only(['parent_id', 'name', 'code', 'description', 'sort_order'])->all());

        return $category->refresh();
    }

    public function deleteCategory(User $actor, ProjectBudget $budget, BudgetCategory $category): void
    {
        $this->assertCategoryBelongsToBudget($budget, $category);
        $this->authorize($actor, 'update', $budget);
        $this->authorize($actor, 'delete', $category);
        $this->assertDraft($budget);

        if ($category->children()->exists() || $category->items()->exists()) {
            $this->invalid('category', 'A category can be deleted only after its child categories and budget items are removed.');
        }

        $category->delete();
    }

    public function updateItem(User $actor, ProjectBudget $budget, BudgetItem $item, array $attributes): BudgetItem
    {
        $this->assertItemBelongsToBudget($budget, $item);
        $this->authorize($actor, 'update', $budget);
        $this->authorize($actor, 'update', $item);
        $this->assertDraft($budget);
        $this->positive($attributes['planned_amount'] ?? null, 'planned_amount');

        $item->update(collect($attributes)->only([
            'code', 'name', 'description', 'planned_amount', 'quantity', 'unit', 'unit_cost', 'notes',
        ])->all());

        return $item->refresh();
    }

    public function deleteItem(User $actor, ProjectBudget $budget, BudgetItem $item): void
    {
        $this->assertItemBelongsToBudget($budget, $item);
        $this->authorize($actor, 'update', $budget);
        $this->authorize($actor, 'delete', $item);
        $this->assertDraft($budget);

        $line = $item->line;
        $item->delete();

        if ($line
            && ! $line->items()->exists()
            && ! $line->commitments()->exists()
            && ! $line->actualCosts()->exists()) {
            $line->delete();
        }
    }

    public function submitBudget(User $actor, ProjectBudget $budget): ProjectBudget
    {
        $this->authorize($actor, 'submit', $budget);
        $this->assertDraft($budget);
        if (!$budget->categories()->whereHas('items')->exists())
            $this->invalid('budget', 'A budget needs at least one item before submission.');
        return DB::transaction(function () use ($actor, $budget) {
            $budget->forceFill(['status' => 'pending_approval', 'submitted_by' => $actor->id, 'submitted_at' => now()])->save();
            DB::afterCommit(fn() => $this->notify($actor, $budget->project, 'Budget submitted for approval', "Budget V{$budget->version_number} is awaiting approval."));
            return $budget->refresh();
        });
    }

    public function approveBudget(User $actor, ProjectBudget $budget): ProjectBudget
    {
        $this->authorize($actor, 'approve', $budget);
        if ($budget->status !== 'pending_approval')
            $this->transition('budget', $budget->status, 'approved');
        return DB::transaction(function () use ($actor, $budget) {
            $locked = ProjectBudget::withoutGlobalScopes()->where('project_id', $budget->project_id)->lockForUpdate()->get();
            $target = $locked->firstWhere('id', $budget->id);
            if (!$target || $target->status !== 'pending_approval')
                $this->invalid('status', 'Budget approval is no longer valid.');
            foreach ($locked->where('status', 'approved') as $prior)
                $prior->forceFill(['status' => 'superseded'])->save();
            $target->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            DB::afterCommit(fn() => $this->notify($actor, $target->project, 'Budget approved', "Budget V{$target->version_number} is now the current baseline."));
            return $target->refresh();
        });
    }

    public function rejectBudget(User $actor, ProjectBudget $budget, string $reason): ProjectBudget
    {
        $this->authorize($actor, 'reject', $budget);
        if ($budget->status !== 'pending_approval')
            $this->transition('budget', $budget->status, 'draft');
        $budget->forceFill(['status' => 'draft', 'notes' => trim(($budget->notes ? $budget->notes . "\n" : '') . "Returned: {$reason}")])->save();
        DB::afterCommit(fn() => $this->notify($actor, $budget->project, 'Budget returned to draft', "Budget V{$budget->version_number} requires changes."));
        return $budget->refresh();
    }

    public function cancelBudget(User $actor, ProjectBudget $budget, string $reason): ProjectBudget
    {
        $this->authorize($actor, 'cancel', $budget);
        if (!in_array($budget->status, ['draft', 'pending_approval'], true))
            $this->transition('budget', $budget->status, 'cancelled');
        if (blank($reason))
            $this->invalid('reason', 'A cancellation reason is required.');

        $budget->forceFill([
            'status' => 'cancelled',
            'notes' => trim(($budget->notes ? $budget->notes . "\n" : '') . "Cancelled: {$reason}"),
        ])->save();
        DB::afterCommit(fn() => $this->notify($actor, $budget->project, 'Budget cancelled', "Budget V{$budget->version_number} was cancelled."));
        return $budget->refresh();
    }

    public function createRevision(User $actor, ProjectBudget $budget, array $attributes = []): ProjectBudget
    {
        $this->authorize($actor, 'revision', $budget);
        if (!in_array($budget->status, ['approved', 'superseded'], true))
            $this->invalid('budget', 'Only an approved budget can be revised.');
        return DB::transaction(function () use ($actor, $budget, $attributes) {
            $project = Project::withoutGlobalScopes()->lockForUpdate()->findOrFail($budget->project_id);
            if (ProjectBudget::withoutGlobalScopes()->where('project_id', $project->id)->where('status', 'draft')->exists())
                $this->invalid('budget', 'A draft budget revision already exists for this project.');
            $revision = ProjectBudget::create(['company_id' => $project->company_id, 'project_id' => $project->id, 'version_number' => ((int) ProjectBudget::withoutGlobalScopes()->where('project_id', $project->id)->max('version_number')) + 1, 'name' => $attributes['name'] ?? "Revision of V{$budget->version_number}", 'notes' => $attributes['notes'] ?? null]);
            $map = [];
            foreach ($budget->categories()->orderBy('id')->get() as $category)
                $map[$category->id] = BudgetCategory::create(['company_id' => $revision->company_id, 'project_budget_id' => $revision->id, 'parent_id' => $category->parent_id ? ($map[$category->parent_id]->id ?? null) : null, 'cloned_from_id' => $category->id, 'name' => $category->name, 'code' => $category->code, 'description' => $category->description, 'sort_order' => $category->sort_order]);
            foreach ($budget->categories()->with('items')->get() as $category)
                foreach ($category->items as $item)
                    BudgetItem::create(['company_id' => $revision->company_id, 'budget_category_id' => $map[$category->id]->id, 'budget_line_id' => $item->budget_line_id, 'cloned_from_id' => $item->id, 'code' => $item->code, 'name' => $item->name, 'description' => $item->description, 'planned_amount' => $item->planned_amount, 'quantity' => $item->quantity, 'unit' => $item->unit, 'unit_cost' => $item->unit_cost, 'notes' => $item->notes]);
            return $revision;
        });
    }

    public function createCommitment(User $actor, Project $project, array $attributes): FinancialCommitment
    {
        $this->authorize($actor, 'create', new FinancialCommitment(['company_id' => $project->company_id]));
        $this->assertProjectOpen($project);
        $this->money($attributes, 'amount');
        $this->money($attributes, 'budget_amount');
        $this->currency($attributes['currency'] ?? null);
        [$item, $line] = $this->budgetContext($project, $attributes['budget_item_id'] ?? null, true);
        if (!$item && !$actor->can('create_unbudgeted_commitment'))
            throw new AuthorizationException;
        $party = $this->party($project, $attributes['party_id'] ?? null, true);
        $this->source($project, $attributes['source_type'] ?? null, $attributes['source_id'] ?? null);
        $commitment = FinancialCommitment::create(array_merge(collect($attributes)->only(['source_type', 'source_id', 'reference_number', 'description', 'amount', 'currency', 'budget_amount', 'exchange_rate_to_budget', 'notes'])->all(), ['company_id' => $project->company_id, 'project_id' => $project->id, 'budget_item_id' => $item?->id, 'budget_line_id' => $line?->id, 'party_id' => $party?->id, 'created_by' => $actor->id]));
        return $commitment;
    }

    public function updateCommitment(User $actor, FinancialCommitment $commitment, array $attributes): FinancialCommitment
    {
        $this->authorize($actor, 'update', $commitment);
        $this->assertProjectOpen($commitment->project);
        if ($commitment->status !== 'draft') {
            $this->transition('financial commitment', $commitment->status, 'draft');
        }

        $this->money($attributes, 'amount');
        $this->money($attributes, 'budget_amount');
        $this->currency($attributes['currency'] ?? null);
        [$item, $line] = $this->budgetContext($commitment->project, $attributes['budget_item_id'] ?? null, true);

        if (! $item && ! $actor->can('create_unbudgeted_commitment')) {
            throw new AuthorizationException;
        }

        $party = $this->party($commitment->project, $attributes['party_id'] ?? null, true);
        $this->source($commitment->project, $attributes['source_type'] ?? null, $attributes['source_id'] ?? null);

        $commitment->update(array_merge(
            collect($attributes)->only(['source_type', 'source_id', 'reference_number', 'description', 'amount', 'currency', 'budget_amount', 'exchange_rate_to_budget', 'notes'])->all(),
            ['budget_item_id' => $item?->id, 'budget_line_id' => $line?->id, 'party_id' => $party?->id],
        ));

        return $commitment->refresh();
    }

    public function commit(User $actor, FinancialCommitment $commitment, ?string $reason = null): FinancialCommitment
    {
        $this->authorize($actor, 'commit', $commitment);
        if ($commitment->status !== 'draft')
            $this->transition('commitment', $commitment->status, 'committed');
        $overBudget = false;
        if ($commitment->budget_line_id) {
            $planned = (float) BudgetItem::withoutGlobalScopes()->where('budget_line_id', $commitment->budget_line_id)->whereHas('category.budget', fn($q) => $q->where('status', 'approved'))->value('planned_amount');
            $used = (float) FinancialCommitment::withoutGlobalScopes()->where('budget_line_id', $commitment->budget_line_id)->where('status', 'committed')->sum('budget_amount');
            $overBudget = $used + (float) $commitment->budget_amount > $planned;
            if ($overBudget && !$actor->can('overBudget', $commitment))
                throw new AuthorizationException;
            if ($overBudget && blank($reason))
                $this->invalid('reason', 'An over-budget reason is required.');
        }
        $commitment->forceFill(['status' => 'committed', 'committed_at' => now(), 'notes' => $overBudget ? trim(($commitment->notes ? $commitment->notes . "\n" : '') . "Over-budget reason: {$reason}") : $commitment->notes])->save();
        DB::afterCommit(function () use ($actor, $commitment, $overBudget) {
            $this->notify($actor, $commitment->project, $overBudget ? 'Over-budget commitment committed' : 'Financial commitment committed', $overBudget ? "{$commitment->description} exceeds its planned capacity." : "{$commitment->description} is now committed.");
        });
        return $commitment->refresh();
    }

    public function releaseCommitment(User $actor, FinancialCommitment $commitment, string $reason): FinancialCommitment
    {
        $this->authorize($actor, 'release', $commitment);
        if ($commitment->status !== 'committed')
            $this->transition('commitment', $commitment->status, 'released');
        if (blank($reason))
            $this->invalid('reason', 'A release reason is required.');
        $commitment->forceFill(['status' => 'released', 'released_at' => now(), 'notes' => trim(($commitment->notes ? $commitment->notes . "\n" : '') . "Released: {$reason}")])->save();
        DB::afterCommit(fn() => $this->notify($actor, $commitment->project, 'Commitment released', $commitment->description . ' was released.'));
        return $commitment->refresh();
    }

    public function cancelCommitment(User $actor, FinancialCommitment $commitment, string $reason): FinancialCommitment
    {
        $this->authorize($actor, 'cancel', $commitment);
        if (!in_array($commitment->status, ['draft', 'committed'], true))
            $this->transition('commitment', $commitment->status, 'cancelled');
        if (blank($reason))
            $this->invalid('reason', 'A cancellation reason is required.');
        $commitment->forceFill(['status' => 'cancelled', 'notes' => trim(($commitment->notes ? $commitment->notes . "\n" : '') . "Cancelled: {$reason}")])->save();
        DB::afterCommit(fn() => $this->notify($actor, $commitment->project, 'Commitment cancelled', $commitment->description . ' was cancelled.'));
        return $commitment->refresh();
    }

    public function requestCommitmentAmendment(User $actor, FinancialCommitment $commitment, array $attributes): FinancialCommitmentAmendment
    {
        $this->authorize($actor, 'create', new FinancialCommitmentAmendment(['company_id' => $commitment->company_id]));
        if ($commitment->status !== 'committed')
            $this->invalid('commitment', 'Only a committed obligation can be amended.');
        $this->money($attributes, 'amount_change', true);
        $this->money($attributes, 'budget_amount_change', true);
        if (blank($attributes['reason'] ?? null))
            $this->invalid('reason', 'An amendment reason is required.');
        return FinancialCommitmentAmendment::create(['company_id' => $commitment->company_id, 'financial_commitment_id' => $commitment->id, 'amount_change' => $attributes['amount_change'], 'budget_amount_change' => $attributes['budget_amount_change'], 'reason' => $attributes['reason'], 'status' => 'pending_approval', 'requested_by' => $actor->id, 'requested_at' => now()]);
    }

    public function approveCommitmentAmendment(User $actor, FinancialCommitmentAmendment $amendment): FinancialCommitmentAmendment
    {
        $this->authorize($actor, 'approve', $amendment);
        if ($amendment->status !== 'pending_approval')
            $this->transition('commitment amendment', $amendment->status, 'approved');
        $amendment->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
        DB::afterCommit(fn() => $this->notify($actor, $amendment->commitment->project, 'Commitment amendment approved', $amendment->reason));
        return $amendment->refresh();
    }

    public function createActualCost(User $actor, Project $project, array $attributes): ActualCost
    {
        $this->authorize($actor, 'create', new ActualCost(['company_id' => $project->company_id]));
        $this->assertProjectOpen($project);
        $this->money($attributes, 'amount');
        $this->money($attributes, 'budget_amount');
        $this->currency($attributes['currency'] ?? null);
        $this->occurredOnOrBeforeToday($attributes['incurred_at'] ?? null, 'incurred_at', 'Actual-cost date');
        $party = $this->party($project, $attributes['party_id'] ?? null, false);
        $commitment = null;
        if (!empty($attributes['financial_commitment_id'])) {
            $commitment = FinancialCommitment::withoutGlobalScopes()->findOrFail($attributes['financial_commitment_id']);
            $this->same($project, $commitment);
            if ($commitment->status !== 'committed')
                $this->invalid('financial_commitment_id', 'Actual costs may be linked only to an active committed obligation.');
            if ($commitment->party_id && (int) $commitment->party_id !== (int) $party->id)
                $this->invalid('party_id', 'The vendor must match the financial commitment.');
        }
        [$item, $line] = $commitment ? [$commitment->item, $commitment->line] : $this->budgetContext($project, $attributes['budget_item_id'] ?? null, true);
        if ($commitment && $commitment->budget_line_id && (int) $commitment->budget_line_id !== (int) ($line?->id))
            $this->invalid('budget_item_id', 'The budget item must match the commitment line.');
        return ActualCost::create(array_merge(collect($attributes)->only(['name', 'amount', 'currency', 'budget_amount', 'exchange_rate_to_budget', 'incurred_at'])->all(), ['company_id' => $project->company_id, 'project_id' => $project->id, 'financial_commitment_id' => $commitment?->id, 'budget_item_id' => $item?->id, 'budget_line_id' => $line?->id, 'party_id' => $party->id, 'created_by' => $actor->id]));
    }

    public function updateActualCost(User $actor, ActualCost $cost, array $attributes): ActualCost
    {
        $this->authorize($actor, 'update', $cost);
        $this->assertProjectOpen($cost->project);
        if ($cost->status !== 'draft') {
            $this->transition('actual cost', $cost->status, 'draft');
        }

        $this->money($attributes, 'amount');
        $this->money($attributes, 'budget_amount');
        $this->currency($attributes['currency'] ?? null);
        $this->occurredOnOrBeforeToday($attributes['incurred_at'] ?? null, 'incurred_at', 'Actual-cost date');
        $party = $this->party($cost->project, $attributes['party_id'] ?? null, false);
        $commitment = null;

        if (! empty($attributes['financial_commitment_id'])) {
            $commitment = FinancialCommitment::withoutGlobalScopes()->findOrFail($attributes['financial_commitment_id']);
            $this->same($cost->project, $commitment);
            if ($commitment->status !== 'committed') {
                $this->invalid('financial_commitment_id', 'Actual costs may be linked only to an active committed obligation.');
            }
            if ($commitment->party_id && (int) $commitment->party_id !== (int) $party->id) {
                $this->invalid('party_id', 'The vendor must match the financial commitment.');
            }
        }

        [$item, $line] = $commitment
            ? [$commitment->item, $commitment->line]
            : $this->budgetContext($cost->project, $attributes['budget_item_id'] ?? null, true);

        if ($commitment && $commitment->budget_line_id && (int) $commitment->budget_line_id !== (int) ($line?->id)) {
            $this->invalid('budget_item_id', 'The budget item must match the commitment line.');
        }

        $cost->update(array_merge(
            collect($attributes)->only(['name', 'amount', 'currency', 'budget_amount', 'exchange_rate_to_budget', 'incurred_at'])->all(),
            ['financial_commitment_id' => $commitment?->id, 'budget_item_id' => $item?->id, 'budget_line_id' => $line?->id, 'party_id' => $party->id],
        ));

        return $cost->refresh();
    }

    public function submitActualCost(User $actor, ActualCost $cost): ActualCost
    {
        $this->authorize($actor, 'submit', $cost);
        if ($cost->status !== 'draft')
            $this->transition('actual cost', $cost->status, 'pending approval');
        $cost->forceFill(['status' => 'pending_approval', 'submitted_by' => $actor->id, 'submitted_at' => now()])->save();
        DB::afterCommit(fn() => $this->notify($actor, $cost->project, 'Actual cost awaiting approval', "{$cost->name} was submitted for approval."));
        return $cost->refresh();
    }
    public function approveActualCost(User $actor, ActualCost $cost): ActualCost
    {
        $this->authorize($actor, 'approve', $cost);
        if ($cost->status !== 'pending_approval')
            $this->transition('actual cost', $cost->status, 'approved');
        $cost->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
        return $cost->refresh();
    }

    public function correctActualCost(User $actor, ActualCost $original, array $attributes): ActualCost
    {
        $this->authorize($actor, 'correct', $original);
        if ($original->status !== 'approved')
            $this->invalid('actual_cost', 'Only an approved actual cost may be corrected.');
        $this->money($attributes, 'amount', true);
        $this->money($attributes, 'budget_amount', true);
        if (blank($attributes['correction_reason'] ?? null))
            $this->invalid('correction_reason', 'A correction reason is required.');
        $this->occurredOnOrBeforeToday($attributes['incurred_at'] ?? now()->toDateString(), 'incurred_at', 'Correction date');
        $net = (float) $original->amount + (float) $original->corrections()->where('status', 'approved')->sum('amount') + (float) $attributes['amount'];
        $allocated = (float) $original->allocations()->sum('actual_cost_amount');
        if ($net < $allocated)
            $this->invalid('amount', 'A correction cannot reduce the cost below cash already allocated.');
        return ActualCost::create(['company_id' => $original->company_id, 'project_id' => $original->project_id, 'financial_commitment_id' => $original->financial_commitment_id, 'budget_item_id' => $original->budget_item_id, 'budget_line_id' => $original->budget_line_id, 'party_id' => $original->party_id, 'name' => $original->name . ' correction', 'amount' => $attributes['amount'], 'currency' => $original->currency, 'budget_amount' => $attributes['budget_amount'], 'exchange_rate_to_budget' => $attributes['exchange_rate_to_budget'] ?? $original->exchange_rate_to_budget, 'incurred_at' => $attributes['incurred_at'] ?? now()->toDateString(), 'created_by' => $actor->id, 'correction_of_actual_cost_id' => $original->id, 'correction_reason' => $attributes['correction_reason']]);
    }

    public function recordPayment(User $actor, Project $project, array $attributes): Payment
    {
        $this->authorize($actor, 'create', new Payment(['company_id' => $project->company_id]));
        $this->assertProjectOpen($project);
        $this->money($attributes, 'amount');
        $this->money($attributes, 'project_amount');
        $this->currency($attributes['currency'] ?? null);
        $this->occurredOnOrBeforeToday($attributes['payment_date'] ?? null, 'payment_date', 'Payment date');
        $party = $this->party($project, $attributes['party_id'] ?? null, false);
        return Payment::create(array_merge(collect($attributes)->only(['amount', 'currency', 'project_amount', 'exchange_rate_to_project', 'payment_date', 'reference_number', 'payment_method'])->all(), ['company_id' => $project->company_id, 'project_id' => $project->id, 'party_id' => $party->id, 'direction' => 'outgoing', 'status' => 'completed', 'completed_at' => now(), 'recorded_by' => $actor->id]));
    }

    public function voidPayment(User $actor, Payment $payment, string $reason): Payment
    {
        $this->authorize($actor, 'void', $payment);
        if ($payment->status !== 'completed')
            $this->transition('payment', $payment->status, 'voided');
        if (blank($reason))
            $this->invalid('reason', 'A void reason is required.');
        if ($payment->allocations()->exists())
            $this->invalid('payment', 'A payment with allocations requires an explicit reversal before it can be voided.');
        $payment->forceFill(['status' => 'voided', 'void_reason' => $reason])->save();
        DB::afterCommit(fn() => $this->notify($actor, $payment->project, 'Payment voided', $payment->reference_number ?: 'A payment was voided.'));
        return $payment->refresh();
    }

    public function allocatePayment(User $actor, Payment $payment, ActualCost $cost, array $attributes): PaymentAllocation
    {
        $this->authorize($actor, 'create', new PaymentAllocation(['company_id' => $payment->company_id]));
        $this->money($attributes, 'payment_amount');
        $this->money($attributes, 'actual_cost_amount');
        $this->money($attributes, 'project_amount');
        return DB::transaction(function () use ($payment, $cost, $attributes) {
            $lockedPayment = Payment::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
            $lockedCost = ActualCost::withoutGlobalScopes()->lockForUpdate()->findOrFail($cost->id);
            if ($lockedPayment->status !== 'completed')
                $this->invalid('payment', 'Only completed payments may be allocated.');
            if ($lockedCost->status !== 'approved')
                $this->invalid('actual_cost', 'Only approved actual costs may be allocated.');
            if ($lockedPayment->company_id !== $lockedCost->company_id || $lockedPayment->project_id !== $lockedCost->project_id || $lockedPayment->party_id !== $lockedCost->party_id)
                $this->invalid('payment', 'Payment and actual cost must belong to the same company, project, and vendor.');
            $paymentUsed = (float) PaymentAllocation::withoutGlobalScopes()->where('payment_id', $lockedPayment->id)->sum('payment_amount');
            $costUsed = (float) PaymentAllocation::withoutGlobalScopes()->where('allocatable_type', ActualCost::class)->where('allocatable_id', $lockedCost->id)->sum('actual_cost_amount');
            $net = (float) $lockedCost->amount + (float) $lockedCost->corrections()->where('status', 'approved')->sum('amount');
            if ($paymentUsed + (float) $attributes['payment_amount'] > (float) $lockedPayment->amount + 0.00001)
                $this->invalid('payment_amount', 'Allocation exceeds the available payment balance.');
            if ($costUsed + (float) $attributes['actual_cost_amount'] > $net + 0.00001)
                $this->invalid('actual_cost_amount', 'Allocation exceeds the approved actual-cost balance.');
            return PaymentAllocation::create(['company_id' => $lockedPayment->company_id, 'project_id' => $lockedPayment->project_id, 'payment_id' => $lockedPayment->id, 'allocatable_type' => ActualCost::class, 'allocatable_id' => $lockedCost->id, 'payment_amount' => $attributes['payment_amount'], 'actual_cost_amount' => $attributes['actual_cost_amount'], 'project_amount' => $attributes['project_amount'], 'exchange_rate_to_project' => $attributes['exchange_rate_to_project'] ?? null]);
        });
    }

    private function assertCategoryBelongsToBudget(ProjectBudget $budget, BudgetCategory $category): void
    {
        if ((int) $category->project_budget_id !== (int) $budget->id
            || (int) $category->company_id !== (int) $budget->company_id) {
            $this->invalid('category', 'The selected category must belong to this budget version.');
        }
    }

    private function assertItemBelongsToBudget(ProjectBudget $budget, BudgetItem $item): void
    {
        $item->loadMissing('category');

        if (! $item->category) {
            $this->invalid('budget_item', 'The selected budget item no longer has a category.');
        }

        $this->assertCategoryBelongsToBudget($budget, $item->category);
    }

    private function isCategoryDescendantOf(BudgetCategory $candidate, BudgetCategory $ancestor): bool
    {
        $current = $candidate;

        while ($current->parent_id) {
            if ((int) $current->parent_id === (int) $ancestor->id) {
                return true;
            }

            $current = BudgetCategory::withoutGlobalScopes()->find($current->parent_id);

            if (! $current) {
                return false;
            }
        }

        return false;
    }

    private function budgetContext(Project $project, mixed $itemId, bool $mustBeCurrent = false): array
    {
        if (!$itemId)
            return [null, null];
        $item = BudgetItem::withoutGlobalScopes()->with('category.budget', 'line')->findOrFail($itemId);
        if ((int) $item->company_id !== (int) $project->company_id || (int) $item->line->project_id !== (int) $project->id)
            $this->invalid('budget_item_id', 'The budget item must belong to this project.');
        if ($mustBeCurrent && $item->category->budget->status !== 'approved')
            $this->invalid('budget_item_id', 'New financial records must use an item from the current approved budget.');
        return [$item, $item->line];
    }
    private function party(Project $project, mixed $id, bool $nullable): ?Party
    {
        if (!$id) {
            if ($nullable)
                return null;
            $this->invalid('party_id', 'A vendor/counterparty is required.');
        }
        $party = Party::withoutGlobalScopes()->findOrFail($id);
        $this->same($project, $party);
        return $party;
    }
    private function source(Project $project, ?string $type, mixed $id): void
    {
        if (!$type && !$id)
            return;
        $allowed = [ProjectDesignPackage::class, DesignPackageAssignment::class, PropertyAcquisition::class];
        if (!$type || !$id || !in_array($type, $allowed, true))
            $this->invalid('source', 'The selected source type is not permitted.');
        $source = $type::withoutGlobalScopes()->findOrFail($id);
        $this->same($project, $source);
        if ($source instanceof ProjectDesignPackage && (int) $source->project_id !== (int) $project->id)
            $this->invalid('source', 'The source package must belong to this project.');
    }
    private function assertDraft(ProjectBudget $budget): void
    {
        if ($budget->status !== 'draft')
            $this->invalid('budget', 'Only a draft budget can be structurally changed.');
    }
    private function assertProjectOpen(Project $p): void
    {
        if (in_array($p->status, ['closed', 'cancelled'], true))
            $this->invalid('project', 'Financial records cannot be changed for a closed or cancelled project.');
    }
    private function money(array $attributes, string $key, bool $signed = false): void
    {
        $value = $attributes[$key] ?? null;
        if (!is_numeric($value) || ($signed ? (float) $value === 0 : (float) $value <= 0))
            $this->invalid($key, 'Enter a valid ' . ($signed ? 'non-zero' : 'positive') . ' amount.');
    }
    private function positive(mixed $value, string $key): void
    {
        if (!is_numeric($value) || (float) $value < 0)
            $this->invalid($key, 'Amount must be non-negative.');
    }
    private function currency(?string $currency): void
    {
        if (!preg_match('/^[A-Z]{3}$/', (string) $currency))
            $this->invalid('currency', 'Currency must be a three-letter ISO code.');
    }
    private function occurredOnOrBeforeToday(mixed $value, string $field, string $label): void
    {
        if (blank($value))
            $this->invalid($field, "{$label} is required.");
        try {
            $date = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            $this->invalid($field, "{$label} must be a valid date.");
        }
        if ($date->gt(now()->startOfDay()))
            $this->invalid($field, "{$label} cannot be in the future.");
    }
    private function same(object $a, object $b): void
    {
        if ((int) $a->company_id !== (int) $b->company_id)
            $this->invalid('company_id', 'Records from different companies cannot be associated.');
    }
    private function authorize(User $u, string $ability, object $record): void
    {
        if (!$u->can($ability, $record))
            throw new AuthorizationException;
    }
    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
    private function transition(string $subject, string $from, string $to): never
    {
        $this->invalid('status', "Cannot transition {$subject} from {$from} to {$to}.");
    }
    private function notify(User $actor, Project $project, string $title, string $body): void
    {
        $users = $project->activeMembers()->with('user')->get()
            ->pluck('user')->filter()->push($actor)->unique('id');
        $users->each->notify(new ProjectWorkflowNotification($title, $body, $project->id));
    }
}
