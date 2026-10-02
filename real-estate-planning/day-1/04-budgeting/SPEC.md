# 04 — Project Budgeting & Financial Commitments

> **Phase:** Budgeting  
> **Depends on:** Project planning and enough approved design information to estimate cost  
> **Goal:** Model planned project cost and financial commitments without confusing budget, commitment, actual cost, and cash payment.

---

# 1. Purpose

This phase answers:

```text
What do we expect the project to cost?
How is the budget structured?
Which budget version is approved?
How much has already been committed?
Which commitments are outside the approved budget?
Where are we exceeding planned amounts?
```

---

# 2. Fundamental Financial Separation

The system must distinguish:

```text
Planned
Committed
Actual
Paid
```

Definitions:

```text
Planned
= approved budget expectation.

Committed
= company has created an obligation.

Actual
= cost has actually been incurred/recognized.

Paid
= cash has moved.
```

This phase focuses primarily on:

```text
Planned
Committed
```

Do not use payment records to represent a budget.

---

# 3. Core Entities

```text
Project Budget
   ↓
Budget Categories
   ↓
Budget Items

Financial Commitment
   ↓
optionally linked to Budget Item
```

---

# 4. Existing-System Mapping

Inspect:

```text
projects
expenses
costs
contracts
payments
reports
dashboard calculations
project financial fields
```

Expected actions:

| Concept | Action |
|---|---|
| Project budgets | CREATE |
| Budget categories | CREATE |
| Budget items | CREATE |
| Financial commitments | CREATE |
| Existing expenses/costs | VERIFY; do not replace blindly |
| Existing payments | do not use as budget |
| Reports | REUSE + later update |
| Policies / Shield | EXTEND |

---

# 5. Budget Versioning Principle

A project's budget changes over time.

Do not overwrite an approved budget silently.

Recommended model:

```text
Project
├── Budget Version 1 (superseded)
└── Budget Version 2 (approved/current)
```

Draft budget may be edited freely.

Approved budget becomes a historical baseline.

Material revision should create a new budget version rather than rewriting approved historical values.

---

# 6. Entity: Project Budget

## Table

```text
project_budgets
```

## Recommended Fields

| Field | Type | Nullability | Notes |
|---|---|---:|---|
| id | PK | required | |
| project_id | FK | required | |
| version_number | integer | required | |
| name | string | nullable | e.g. Initial Budget |
| currency | string | required | Accounting/base budget currency |
| status | string | required | |
| notes | text | nullable | |
| submitted_by | FK users | nullable | |
| submitted_at | timestamp | nullable | |
| approved_by | FK users | nullable | |
| approved_at | timestamp | nullable | |
| closed_at | timestamp | nullable | |
| timestamps | timestamps | required | |

Unique:

```text
(project_id, version_number)
```

Do not store writable `total_budget` if it is simply the sum of budget items.

Expose total as aggregate/derived value.

---

# 7. Budget Status Lifecycle

Recommended:

```text
draft
  ↓
pending_approval
  ↓
approved
  ↓
superseded
```

Optional terminal states:

```text
closed
cancelled
```

Rules:

```text
draft:
  editable.

pending_approval:
  structural edits restricted while awaiting decision.

approved:
  baseline is immutable except controlled administrative correction.

superseded:
  replaced by newer approved version; remains historical.

closed:
  project budget lifecycle ended.

cancelled:
  draft/pending budget abandoned.
```

If approval is rejected:

```text
pending_approval → draft
```

with rejection reason captured in audit/activity and notification.

---

# 8. One Current Approved Budget

Normally only one budget version per project should be considered the current approved baseline.

When a new budget version is approved:

```text
transaction:
  mark prior approved budget as superseded
  mark new version approved
  persist approval metadata
  audit
commit
notify
```

Do not delete the previous version.

---

# 9. Entity: Budget Category

## Table

```text
budget_categories
```

## Purpose

Hierarchical cost grouping.

Examples:

```text
Land
Design
Construction
  ├── Concrete
  ├── Electrical
  └── Finishing
Marketing
Permits
Contingency
```

## Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_budget_id | FK | required |
| parent_id | self FK | nullable |
| name | string | required |
| code | string | nullable |
| description | text | nullable |
| sort_order | integer | required/default |
| timestamps | timestamps | required |

Avoid deep hierarchy unless business requires it.

---

# 10. Entity: Budget Item

## Table

```text
budget_items
```

## Purpose

Lowest practical planned-cost line.

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| budget_category_id | FK | required |
| code | string | nullable |
| name | string | required |
| description | text | nullable |
| planned_amount | decimal | required |
| quantity | decimal | nullable |
| unit | string | nullable |
| unit_cost | decimal | nullable |
| notes | text | nullable |
| timestamps | timestamps | required |

`planned_amount` is the canonical planned amount.

`quantity` and `unit_cost` are supporting estimate detail.

If the UI offers automatic calculation:

```text
quantity × unit_cost → proposed planned_amount
```

but avoid maintaining conflicting writable truth.

---

# 11. Currency Rule

`project_budgets.currency` is the budget accounting currency.

All `planned_amount` values in that budget are expressed in that currency.

If the business commitment originates in another currency, preserve the original amount/currency and record the budget-equivalent value explicitly.

Do not silently use current FX rates later to rewrite historical commitments.

---

# 12. Entity: Financial Commitment

## Table

```text
financial_commitments
```

## Purpose

Represents an obligation that has been commercially committed.

Examples:

```text
signed contractor agreement
engineering-office contract
supplier purchase commitment
permit obligation
land-related project obligation
```

A commitment is not an actual payment.

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_id | FK | required |
| budget_item_id | FK | nullable |
| contract_id | FK | nullable |
| party_id | FK | nullable |
| reference_number | string | nullable |
| description | text | required |
| amount | decimal | required |
| currency | string | required |
| budget_amount | decimal | nullable |
| exchange_rate_to_budget | decimal | nullable |
| status | string | required |
| committed_at | timestamp/date | nullable |
| released_at | timestamp/date | nullable |
| notes | text | nullable |
| created_by | FK users | nullable |
| timestamps | timestamps | required |

If commitment currency equals budget currency:

```text
budget_amount = amount
exchange_rate_to_budget may be null/1
```

If currency differs, `budget_amount` should represent the agreed accounting snapshot used for budget comparison.

---

# 13. Why budget_item_id Is Nullable

Real operations may create an unbudgeted commitment.

The system must not force users to attach it to an unrelated budget item merely to satisfy an FK.

Therefore:

```text
budget_item_id nullable
```

means:

```text
explicitly unbudgeted / not yet allocated
```

This should be visible and should trigger a warning/notification.

---

# 14. Commitment Status

Recommended:

```text
draft
committed
released
fulfilled
cancelled
```

Definitions:

```text
draft:
  proposed/not yet binding.

committed:
  obligation is active.

released:
  obligation no longer applies without fulfillment.

fulfilled:
  obligation has been operationally fulfilled; actual/paid tracking is separate.

cancelled:
  proposal/commitment cancelled according to workflow.
```

Do not infer paid status from `fulfilled`.

---

# 15. Budget Calculations

Canonical derived values:

```text
Budget Planned =
SUM(budget_items.planned_amount)

Item Committed =
SUM(active financial_commitments.budget_amount)
for that budget_item

Item Remaining Planned Capacity =
planned_amount - committed_amount
```

At category level:

```text
SUM descendant item values
```

Do not store category totals as separate writable values.

---

# 16. Planned vs Committed Overrun

If:

```text
item committed amount > item planned amount
```

the system should:

```text
allow or block according to permission/business rule
show clear overrun
audit the action
notify responsible users
```

Initial flexible recommendation:

```text
do not hard-block every overrun
```

because real projects may legitimately exceed budget.

Instead require:

```text
authorized permission
reason/comment
notification
```

for over-budget commitment.

---

# 17. Unbudgeted Commitments

If `budget_item_id = null`:

```text
mark commitment as unbudgeted in UI
show it prominently
notify finance/project responsible users
include it in reports
```

Do not silently exclude it from financial oversight.

---

# 18. Budget Approval Rules

Before approval:

```text
budget has at least one item
planned amounts are valid/non-negative
currency exists
project belongs to tenant
approver authorized
version number valid
```

Approval transaction:

```text
supersede prior current budget if applicable
approve target version
record approver/time
audit
commit
notify
```

---

# 19. Editing Approved Budget

Avoid editing approved budget items directly.

Preferred flow:

```text
Approved Budget V1
   ↓
Create Draft V2 cloned from V1
   ↓
Modify V2
   ↓
Submit
   ↓
Approve V2
   ↓
V1 superseded
```

This preserves history and reporting accuracy.

---

# 20. Budget Revision Cloning

A "Create Revision" action may:

```text
create new project_budget version
copy categories
copy budget items
leave commitments linked to original business records, not duplicated
```

Do not clone actual financial commitments as if they were new obligations.

---

# 21. Actual Cost / Payment

This phase does not redefine actual-cost and payment accounting.

Do not treat:

```text
commitment amount
```

as:

```text
actual cost
```

and do not treat:

```text
actual cost
```

as:

```text
paid cash
```

Later modules can integrate actual costs/expenses and payments.

---

# 22. Contracts Integration

A financial commitment may reference:

```text
contract_id nullable
```

when an obligation comes from a formal contract.

Not every commitment requires a contract.

Not every contract is necessarily a project-cost commitment.

Keep the relation optional and business-driven.

---

# 23. Parties Integration

`party_id` may identify:

```text
contractor
supplier
engineering office
other counterparty
```

Use generic Party model.

Do not create separate commitment tables by counterparty type.

---

# 24. Important Notifications

## Notification Matrix

| Trigger | Recipients | Channel | Notes |
|---|---|---|---|
| Budget submitted for approval | authorized budget approvers | persistent in-app | |
| Budget returned/rejected to draft | creator/submitting user + project manager | in-app | include reason |
| Budget approved | project manager + finance responsibility | in-app | major lifecycle event |
| New version supersedes previous budget | relevant project/finance users | in-app | |
| Budget cancelled | creator/project manager/finance | in-app | |
| Unbudgeted commitment created | project manager + finance permission holders | in-app | high importance |
| Commitment pushes item over planned amount | project manager + finance approvers | in-app | high importance |
| Commitment cancelled/released after being active | responsible finance/project users | in-app | |
| Material commitment created | responsible project/finance users | in-app based on configured threshold/importance | avoid noise |

Do not notify for every minor budget item edit while budget is draft.

---

# 25. Authorization / Filament Shield

Every new model requires Policy + Shield coverage.

## Models / Policies

```text
ProjectBudget       → ProjectBudgetPolicy
BudgetCategory      → BudgetCategoryPolicy
BudgetItem          → BudgetItemPolicy
FinancialCommitment → FinancialCommitmentPolicy
```

## Custom Workflow Permissions

Consider:

```text
submit_project_budget
approve_project_budget
reject_project_budget
create_budget_revision
cancel_project_budget
create_unbudgeted_commitment
approve_over_budget_commitment
release_financial_commitment
cancel_financial_commitment
```

Generic update is not enough for financial approval actions.

---

# 26. Policy State Rules

Examples:

```text
approved budget:
  cannot structurally edit through normal update.

superseded budget:
  read-only.

closed budget:
  read-only except privileged administrative correction.

committed financial commitment:
  sensitive edits restricted.

cross-company budget_item assignment:
  denied.
```

---

# 27. Filament UX

Recommended Project Budget area:

```text
Project
  └── Budgets
       ├── Versions
       ├── Categories / Items
       ├── Commitments
       └── Variance Summary
```

Useful dashboard values:

```text
planned
committed
remaining
unbudgeted commitments
over-budget items
```

Use aggregate queries, not PHP loops over all records.

---

# 28. Reactive Filament Behavior

Actions:

```text
submit budget
approve budget
create revision
add commitment
allocate commitment to item
release commitment
```

should update:

```text
status badge
planned/committed totals
variance
alerts
available actions
```

without full browser reload.

---

# 29. Performance

Use DB aggregates:

```text
SUM(planned_amount)
SUM(budget_amount)
withSum()
grouped aggregate queries
```

Avoid loading all budget items/commitments merely to calculate dashboard cards.

For list pages, eager-load:

```text
project
budget item/category when displayed
party
contract only when displayed
```

Do not eager-load entire budget hierarchy for every commitment row.

---

# 30. Indexes

Likely:

```text
project_budgets(project_id, status)
project_budgets(project_id, version_number)

budget_categories(project_budget_id, parent_id)
budget_items(budget_category_id)

financial_commitments(project_id, status)
financial_commitments(budget_item_id, status)
financial_commitments(contract_id)
financial_commitments(party_id)
```

---

# 31. Delete Rules

Draft budgets with no dependency may be deleted if policy allows.

Approved/superseded budgets should remain historical.

Active commitments should not be hard-deleted.

Use:

```text
cancelled
released
```

business states.

---

# 32. Real-World Scenario

```text
Project Budget V1: $1,000,000

Construction:
  Concrete: $250,000
  Electrical: $120,000
  Finishing: $180,000

Design:
  Architecture: $30,000
  Structural: $25,000
```

A structural engineering contract creates:

```text
Financial Commitment = $28,000
Budget Item = Structural Design ($25,000)
```

Result:

```text
Planned = $25,000
Committed = $28,000
Variance = -$3,000 / overrun
```

The system warns/notifies but does not falsely mark $28,000 as paid.

---

# 33. Multi-Currency Scenario

Budget accounting currency:

```text
USD
```

Supplier commitment:

```text
50,000 ILS
```

At commitment time:

```text
store original:
amount = 50,000
currency = ILS

store accounting snapshot:
budget_amount = approved USD equivalent
exchange_rate_to_budget = rate used
```

Do not later mutate historical commitment value when market FX changes.

---

# 34. Tests

Minimum:

```text
tenant isolation
budget version uniqueness
approval lifecycle
only one current approved baseline
approved budget immutability
revision cloning
hierarchical category integrity
planned total calculation
commitment total calculation
unbudgeted commitment
over-budget detection
multi-currency budget equivalent validation
custom permissions
Policy/Shield
important notifications
notifications after commit
aggregate query efficiency
```

---

# 35. Definition of Done

```text
budget versions preserve history
categories/items work
planned totals are derived correctly
commitments are separate from actual/paid
unbudgeted commitments are visible
over-budget commitments are controlled
every model has Policy + Shield
important events notify correct recipients
Filament is reactive
aggregates are query-efficient
tests pass
```

---

# 36. Handoff

Approved budget and commitments become inputs for:

```text
05-construction.md
```

while actual payment logic remains governed by:

```text
08-contracts-payments.md
```
