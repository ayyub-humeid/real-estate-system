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

This phase implements the entire chain:

```text
Planned
Committed
Actual
Paid
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

Actual Cost
   ↓
linked to Financial Commitment or Budget Item

Payment
   ↓
allocated to Actual Cost via Payment Allocation
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
| Existing expenses/costs | REPLACE with clean Actual Cost domain |
| Existing payments/allocations | REPLACE with clean Payment / Allocation domain |
| Reports | REUSE + later update |
| Policies / Shield | EXTEND |

## Legacy Financial Cutover Rule

The current `payments` table represents lease due installments (`lease_id`,
`due_date`, `paid_amount`, `remaining_amount`, and overdue states). It is not
the Phase 04 `Payment` entity and must not be adapted by keeping mixed
semantics in one row.

For Phase 04, replace that conflicting payment implementation and its legacy
Payment model/resource/observer/API paths with the canonical actual-cash
Payment + Payment Allocation design. A trustworthy historical migration may be
written only if records clearly distinguish a real cash event from a due
obligation. Otherwise legacy data may be retired as approved by the product
owner; do not invent a financial migration.

Likewise, legacy `expenses` are not the Actual Cost domain and must be retired
or explicitly migrated only when their financial meaning is trustworthy.
`subscription_payments` remains a separate platform/SaaS billing domain and is
outside this phase.

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
| company_id | FK | required | tenant isolation |
| project_id | FK | required | |
| version_number | integer | required | |
| name | string | nullable | e.g. Initial Budget |
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

Concurrency Rule: Use `lockForUpdate` on drafts and limit to one draft budget at a time per project to prevent race conditions.

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
| company_id | FK | required |
| project_budget_id | FK | required |
| parent_id | self FK | nullable |
| cloned_from_id | FK | nullable | For version lineage |
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
| company_id | FK | required |
| budget_category_id | FK | required |
| budget_line_id | FK | required | Stable logical line across versions |
| cloned_from_id | FK | nullable | For version lineage |
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

## 10.1 Entity: Budget Line (Required Stable Identity)

`budget_items` are version-specific snapshots. A stable logical line is needed
so revisions do not rewrite financial history.

```text
budget_lines
```

| Field | Type | Nullability | Notes |
|---|---|---:|---|
| id | PK | required | stable project-level identity |
| company_id | FK | required | tenant isolation |
| project_id | FK | required | |
| created_from_budget_item_id | FK | nullable | provenance only |
| timestamps | timestamps | required | |

Every `budget_item` must reference `budget_line_id`. When a budget is revised,
the cloned item for an unchanged logical line keeps the same `budget_line_id`.
`cloned_from_id` remains useful as snapshot provenance, but must not be the
only identity used for financial reporting.

---

# 11. Currency Rule

`projects.currency` (ISO-4217) is the budget accounting currency. The project currency is locked once a budget is approved or a commitment exists.

All `planned_amount`, `budget_amount` (Commitments, Actuals) values in that project are expressed in that currency.

If the business commitment originates in another currency, preserve the original amount/currency and record the budget-equivalent value explicitly.

Do not silently use current FX rates later to rewrite historical commitments.

## 11.1 Project Currency Implementation Rule

Add a required three-character `projects.currency` field before creating any
Phase 04 financial record. Existing projects must be assigned a deliberate
currency during the migration/backfill; do not fabricate a default merely to
satisfy a non-null constraint.

The project currency is the reporting/base currency for every Phase 04 total:

```text
Planned, Committed, Actual, Paid, variance, and remaining capacity.
```

Original transaction currency is always retained separately. Every conversion
used for a financial event is an immutable snapshot, never a live FX lookup.

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
| company_id | FK | required |
| project_id | FK | required |
| budget_item_id | FK | nullable | Immutable version-item snapshot used when booked |
| budget_line_id | FK | nullable | Stable logical budget line for current reporting |
| source_type | string | nullable | e.g. DesignPackageAssignment |
| source_id | integer | nullable | |
| party_id | FK | nullable | |
| reference_number | string | nullable |
| description | text | required |
| amount | decimal | required |
| currency | string | required |
| budget_amount | decimal | required | Amount in Project Currency. NOT NULL |
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
for that stable budget_line

Item Remaining Planned Capacity =
planned_amount - committed_amount

Item Actual =
SUM(approved actual_costs.budget_amount) for that stable budget_line

Item Paid =
SUM(completed-payment allocation.project_amount linked to actual costs)
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
copy categories (storing cloned_from_id)
copy budget items (storing cloned_from_id)
```

Approving V2 must **not** re-point a commitment, actual cost, payment, or
payment allocation. Those records retain both their original
`budget_item_id` snapshot and their stable `budget_line_id` forever.

For unchanged lines, the V2 item uses the same `budget_line_id`. Current
Budget reporting joins financial activity through that stable line to the V2
item; historical reporting reads the original item snapshot and event dates.

If a V1 line is omitted from V2 while it still has active commitments or
actual costs, V2 may be approved only when the omission is explicitly
acknowledged. Its exposure appears as a prominent **retired/unplanned line**
in Current Budget reporting; it must never disappear or be silently mapped to
another line.

Do not clone actual financial commitments, actual costs, payments, or
allocations as if they were new financial events.

---

# 21. Entity: Actual Cost

Represents a cost that has been incurred (e.g. approved invoice, received service). Legacy `expenses` and `costs` tables should be replaced/migrated to this new clean entity.

## Table

```text
actual_costs
```

## Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| company_id | FK | required |
| project_id | FK | required |
| financial_commitment_id | FK | nullable | Link to commitment if applicable |
| budget_item_id | FK | nullable | Immutable version-item snapshot used when booked |
| budget_line_id | FK | nullable | Stable line for current reporting |
| party_id | FK | required | Vendor/Supplier/counterparty |
| name | string | required |
| amount | decimal | required |
| currency | string | required |
| budget_amount | decimal | required | Amount in Project Currency |
| exchange_rate_to_budget | decimal | nullable |
| incurred_at | date | required |
| status | string | required | draft, pending_approval, approved, rejected, cancelled |
| created_by | FK users | nullable |
| submitted_by / submitted_at | FK / timestamp | nullable | approval request metadata |
| approved_by | FK users | nullable |
| approved_at | timestamp | nullable |
| correction_of_actual_cost_id | FK | nullable | Immutable correction/offset lineage |
| correction_reason | text | nullable | required for a correction/offset |
| timestamps | timestamps | required |

## Actual Cost Workflow and Immutability

Only a draft may be edited normally. Submission and approval are explicit
service actions; an approved cost's amount, currency, party, project,
commitment, budget line/item, and incurred date are immutable.

Corrections are new offset/adjustment Actual Cost records linked by
`correction_of_actual_cost_id`; they require a reason and their own approval.
They must never silently mutate the approved original. A negative correction
that would reduce a root cost below already allocated cash is blocked until a
corresponding payment reversal/refund is recorded.

When an Actual Cost references a Financial Commitment, the service must enforce
the same company and project, and inherit/validate the commitment's stable
budget line. If the commitment has a counterparty, the Actual Cost must use
that same party.

---

# 21.1 Entity: Payment & Payment Allocation

Represents actual cash movement and how it is applied to actual costs. Replaces legacy `payments` which mixed obligation and receipt.

## Tables

```text
payments
payment_allocations
```

## Fields for `payments`

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| company_id | FK | required |
| project_id | FK | nullable | Required by Phase 04 service rules for every outgoing cost payment; nullable only for a future non-project Phase 08 receipt context |
| party_id | FK | required |
| direction | string | required | `outgoing` in Phase 04; Phase 08 adds `incoming` |
| amount | decimal | required |
| currency | string | required |
| project_amount | decimal | required | Immutable paid snapshot in project currency |
| exchange_rate_to_project | decimal | nullable | Rate used for the project snapshot |
| payment_date | date | required |
| reference_number | string | nullable |
| payment_method | string | nullable |
| status | string | required | pending, completed, failed, voided |
| completed_at | timestamp | nullable |
| recorded_by | FK users | nullable |
| timestamps | timestamps | required |

## Fields for `payment_allocations`

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| company_id | FK | required |
| project_id | FK | required | Denormalized and must match both ends |
| payment_id | FK | required |
| allocatable_type | string | required | `ActualCost` only in Phase 04 |
| allocatable_id | integer | required |
| payment_amount | decimal | required | Portion in `Payment.currency` |
| actual_cost_amount | decimal | required | Portion settled in `ActualCost.currency` |
| project_amount | decimal | required | Immutable project-currency paid snapshot |
| exchange_rate_to_project | decimal | nullable | Snapshot used by this allocation |
| timestamps | timestamps | required |

## Payment Allocation Invariants

1. **Completion**: Allocations may be created only for a `completed` payment; therefore only completed payments count toward Paid.
2. **Payment Balance Guard**: `SUM(payment_amount)` cannot exceed the payment's original `amount`.
3. **Cost Balance Guard**: `SUM(actual_cost_amount)` cannot exceed the approved Actual Cost's net payable original amount after approved corrections.
4. **Approval Gate**: The target Actual Cost must be `approved`.
5. **Consistency Guard**: Payment, allocation, and Actual Cost must have the same `company_id`, `project_id`, and `party_id`.
6. **Currency Guard**: Never sum mixed original currencies. Project Paid is only `SUM(payment_allocations.project_amount)`.
7. **Concurrency**: Allocation runs in one database transaction while locking the payment and target Actual Cost rows (`lockForUpdate`), then recalculating both balances under those locks.
8. **Atomicity**: An invalid allocation, failed write, or notification failure before commit rolls back all related writes. Business notifications dispatch only with `DB::afterCommit`.
9. **Allowed Targets**: The Phase 04 allow-list contains only `ActualCost`. Phase 08 adds `Installment` to this same polymorphic allocation path; it must not create a competing allocations table.
10. **Project Rule**: `Payment.project_id` is mandatory for every Phase 04 outgoing payment and is copied to every Phase 04 allocation. The nullable physical column exists only so Phase 08 can use this same cash-movement table for a legitimate non-project receipt without inventing a fake Project.

---

# 22. Contracts / Source Integration

A financial commitment may reference an approved allow-listed source record:

```text
source_type
source_id
```

when an obligation comes from a formal workflow (e.g. `DesignPackageAssignment`
or `PropertyAcquisition`). The service must resolve the source type from a
closed allow-list, load the source without tenant scope only through the
central privileged path, and verify the source has the same company and
project context where applicable.

In Phase 08, a dedicated `Contract` model will be introduced. Instead of creating a competing linkage mechanism (like adding a `contract_id` column), Phase 08 will simply add `Contract` to the allowed `source_type` list for Financial Commitments.

Not every commitment requires a source. Keep the relation optional and business-driven.

---

# 22.1 Entity: Financial Commitment Amendment

To satisfy the "no raw update on financial history" rule, value changes to a committed obligation require an amendment record.

## Table

```text
financial_commitment_amendments
```

## Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| company_id | FK | required |
| financial_commitment_id | FK | required |
| amount_change | decimal | required |
| budget_amount_change | decimal | required |
| reason | string | required |
| status | string | required | draft, pending_approval, approved, rejected |
| requested_by / requested_at | FK / timestamp | nullable | |
| approved_by | FK users | nullable |
| approved_at | timestamp | nullable |
| timestamps | timestamps | required |

An approved amendment is a new immutable financial event. The commitment's
effective original and project-currency amounts are derived from its committed
base plus approved amendments; raw edits to a committed obligation are not
allowed.

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
| Actual Cost submitted for approval | finance approvers | in-app | |
| Payment allocated to Actual Cost | finance responsibility | in-app | |

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
FinancialCommitmentAmendment → FinancialCommitmentAmendmentPolicy
ActualCost          → ActualCostPolicy
Payment             → PaymentPolicy
PaymentAllocation   → PaymentAllocationPolicy
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
approve_actual_cost
submit_actual_cost
create_actual_cost_correction
record_payment
allocate_payment
void_payment
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

approved actual cost:
  amount, currency, and core financial fields are immutable. Corrections require an offsetting entry.

completed payment:
  amount, currency, project, party, and allocations are not editable through generic CRUD.
  Void/reversal is an explicit authorized workflow with an audit reason.

financial statuses:
  must never be freely editable through a generic Filament form. Every transition
  uses an authorized service action with state validation, a transaction, and
  after-commit notification dispatch where required.
```

---

# 27. Filament UX

The implementation must use both top-level resources and nested relation managers.

Top-level resources (Convention 1 Shield permissions):
- `ProjectBudgetResource`
- `FinancialCommitmentResource`
- `ActualCostResource`
- `PaymentResource`

Nested Relation Managers (Convention 2 custom permissions, reusing policies):
- `Project` -> Budgets, Commitments, Actual Costs, Payments
- `ProjectBudget` -> Categories / Items

Recommended Project Budget area:

```text
Project
  └── Budgets
       ├── Versions
       ├── Categories / Items
       ├── Commitments
       ├── Actual Costs
       ├── Payments
       └── Variance Summary
```

Useful dashboard values:

```text
planned
committed
actual
paid
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
record actual cost
allocate payment
```

should update:

```text
status badge
planned/committed/actual/paid totals
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
source record only when displayed
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

budget_lines(project_id)
budget_items(budget_line_id)

financial_commitments(project_id, status)
financial_commitments(budget_item_id, status)
financial_commitments(budget_line_id, status)
financial_commitments(source_type, source_id)
financial_commitments(party_id)

actual_costs(project_id, status)
actual_costs(financial_commitment_id)
actual_costs(budget_line_id, status)
actual_costs(party_id, status)
payments(project_id, status, payment_date)
payments(company_id, party_id, payment_date)
payment_allocations(payment_id)
payment_allocations(project_id, allocatable_type, allocatable_id)
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

## 33.1 Multi-Currency Payment Scenario

An approved USD 1,000 Actual Cost can be settled by an ILS 3,750 bank payment.
The records preserve each distinct fact:

```text
Actual Cost:
amount = 1,000 USD
budget_amount = 1,000 USD

Payment:
amount = 3,750 ILS
project_amount = 1,020 USD      (cash-date project-currency snapshot)

Payment Allocation:
payment_amount = 3,750 ILS
actual_cost_amount = 1,000 USD
project_amount = 1,020 USD
```

The Actual balance is settled using `actual_cost_amount`; Project Paid uses the
allocation's `project_amount`. The USD 20 FX difference is visible rather than
silently corrupting either Actual or Paid. A future dedicated FX reporting
layer may classify that difference, but it must not rewrite historical values.

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
planned/committed/actual/paid total calculation
unbudgeted commitment
over-budget detection
multi-currency budget equivalent validation
actual cost approval and payment allocation
payment allocation concurrency and rollback
same-project/same-party allocation rejection
project-currency Paid aggregation across mixed original currencies
approved-actual-cost correction/offset integrity
revision reporting preserves original budget-item snapshot and stable budget-line reporting
custom permissions (Convention 1 and 2)
Policy/Shield
important notifications
aggregate query efficiency
```

---

# 35. Definition of Done

```text
budget versions preserve history
categories/items work
planned/committed/actual/paid totals are derived correctly
commitments, actuals, and payments have clean separation
Every Phase 04 cost payment has a required project and immutable project-currency snapshots
No mixed original currencies are summed for Project Paid
Budget revisions never re-point historical financial records
unbudgeted commitments are visible
over-budget commitments are controlled
every model has Policy + Shield (Convention 1 + 2 integrated)
important events notify correct recipients
Filament is reactive for all financial stages
aggregates are query-efficient
tests pass
```

---

# 36. Handoff

The complete cost-control chain (Planned, Committed, Actual, Paid) becomes the foundation for:

```text
05-construction.md
```

Phase 08 (`08-contracts-payments.md`) will build upon these payment foundations to introduce full multi-party Contracts, Revenue/Customer schedules, and Installment billing.
