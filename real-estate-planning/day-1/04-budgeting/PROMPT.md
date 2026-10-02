# EXECUTION PROMPT — Phase 04: Project Budgeting & Financial Commitments

## Mission

Implement **Phase 04 only**.

Do not redesign customer payments/installments and do not start Construction.

## Mandatory Reading

Read:

```text
real-estate-planning/README.md
real-estate-planning/00-domain-rules-and-global-conventions.md
real-estate-planning/00-existing-system-gap-analysis.md
real-estate-planning/day-1/01-property-acquisition/IMPLEMENTATION-REPORT.md
real-estate-planning/day-1/02-project-planning/IMPLEMENTATION-REPORT.md
real-estate-planning/day-1/03-design-engineering/IMPLEMENTATION-REPORT.md
real-estate-planning/day-1/04-budgeting/SPEC.md
```

If a prior report is missing, stop.

Inspect the repository after reading.

## Scope

Implement/adapt:

```text
Project Budget versions
Budget Categories
Budget Items
Financial Commitments
budget lifecycle/approval
over-budget/unbudgeted handling
currency snapshot rules
Filament financial UI
Policies + Shield
notifications
aggregates/performance
tests
```

Keep separate:

```text
Planned
Committed
Actual
Paid
```

This phase primarily implements Planned + Committed.

## Inspection

Inspect existing project financial fields, expenses/costs, contracts, payments, reports/dashboard calculations, currency handling, Projects, Parties, Policies/Shield, tenant scope, Filament finance UI, and tests.

Do not repurpose customer payments as a budget engine.

Do not destroy expense/payment data.

## Versioning

Approved budgets are historical baselines.

Material revision creates a new version.

Approving a replacement version must transactionally supersede the old current approved version.

## Commitments

A commitment is an obligation, not a payment.

Support budgeted, unbudgeted, over-budget, optional contract/party links, and multi-currency original amount + budget-equivalent snapshot.

Do not force a fake budget item.

## Authorization

Every new model requires Policy + Shield.

Explicitly protect:

```text
submit budget
approve/reject budget
create revision
cancel budget
create unbudgeted commitment
authorize over-budget commitment
release/cancel commitment
```

## Notifications

Implement important submission/approval/supersession/unbudgeted/over-budget/material commitment events.

Avoid noise while editing draft rows.

## Filament

Show versions, categories/items, planned totals, commitments, committed totals, remaining/variance, unbudgeted commitments, and over-budget indicators.

Use reactive actions.

## Performance

Use DB aggregates:

```text
SUM()
withSum()
withCount()
grouped queries
```

Do not load all rows into PHP for summary cards.

# Automated Tests

At minimum cover:

### Budget Lifecycle
```text
version uniqueness
draft editing
submission
approval permission
rejection/return
one current approved baseline
prior version superseded
approved/superseded immutability
revision cloning
commitments not duplicated during revision
```

### Categories / Items
```text
hierarchy integrity
same-budget parent validation
planned totals
invalid amounts rejected
tenant isolation
```

### Commitments
```text
budgeted
unbudgeted
over-budget detection
over-budget permission
status transitions
does not create payment
optional contract/party tenant validation
```

### Currency
```text
same currency
different currency snapshot
historical budget equivalent not silently recalculated
```

### Policies / Notifications
```text
CRUD + workflow permissions
tenant/state rules
correct notification recipients
rollback sends no business notification
draft edits avoid notification noise
```

### Aggregates
Verify important calculated totals against controlled fixtures.

Add regression tests before fixing discovered current-phase bugs.

## Test Execution

Run targeted tests then:

```bash
php artisan test
```

Resolve Phase 04-caused failures.

# Completion

Create:

```text
real-estate-planning/day-1/04-budgeting/IMPLEMENTATION-REPORT.md
```

Then STOP.

Do not start Day 2 automatically.
