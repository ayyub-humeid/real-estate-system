# 00 — Domain Rules & Global Conventions

> **Project Type:** Existing production-oriented Real Estate SaaS  
> **Purpose of this file:** Define the global rules that every domain specification, migration, model, service, API, admin screen, and frontend workflow must follow.
>
> This file must be read before any phase-specific specification.

---

# 1. Purpose

> **Database compatibility:** PostgreSQL is the primary production database, while MySQL is used in the local development environment. All migrations, queries, relationships, constraints, indexes, and application logic must remain compatible with both PostgreSQL and MySQL whenever practical. Prefer Laravel's database-agnostic Schema Builder, Eloquent, and Query Builder APIs over database-specific SQL. PostgreSQL-specific features may only be used when there is a clear domain or performance requirement and no reasonable portable alternative; such usage must be explicitly documented and must not silently break MySQL development or testing.
> This document defines the architectural and domain-wide rules for evolving the existing real estate application into the approved target system.

The application already contains working modules, database tables, models, relationships, APIs, admin screens, and frontend flows. The goal is **not** to rebuild the project from zero.

The goal is to:

1. inspect the existing implementation,
2. compare it with the approved domain design,
3. preserve what is already correct,
4. evolve what is incomplete,
5. restructure what conflicts with the approved domain,
6. add only the missing concepts required by the business domain,
7. migrate existing data safely whenever schema changes are required.

The existing application is the **implementation baseline**.

The approved domain specifications are the **target design**.

When the current implementation conflicts with the approved domain model, the implementation must be evolved toward the approved domain model.

---

# 2. Core Implementation Principle
## Database Compatibility & Dual-Database Testing Gate

PostgreSQL is the primary production database.

MySQL is also used during local development and must remain supported unless an explicit architectural decision is made otherwise.

### Compatibility Rule

All application code introduced or modified by any phase must remain compatible with:

- PostgreSQL
- MySQL

This applies to:

- migrations
- foreign keys
- indexes
- unique constraints
- data types
- default values
- Eloquent relationships
- queries
- transactions
- aggregates
- ordering/filtering/search logic
- test fixtures
- services/actions that interact with the database

Prefer Laravel's database-agnostic APIs:

- Schema Builder
- Eloquent
- Query Builder

Avoid database-specific raw SQL when a reasonable portable Laravel implementation exists.

---

### Required Test Databases

The project maintains isolated test databases for both database engines:

MySQL:
`realState_test`

PostgreSQL:
`realstate_pg_test`

These are TEST databases only.

Never run destructive test commands against:
- the normal development database
- staging
- remote databases
- production databases

Test safety guards must explicitly validate the database connection and allowed test database name before destructive operations are allowed.

---

### Phase Completion Gate

A phase that introduces or modifies database-backed behavior is NOT considered technically complete until its relevant automated tests pass against BOTH:

1. MySQL
2. PostgreSQL

At minimum, run:

- the phase-specific test suite on MySQL
- the phase-specific test suite on PostgreSQL

Where practical, also run the full regression suite on both engines.

Expected gate:

MySQL phase tests       → PASS
PostgreSQL phase tests  → PASS

Only then may database compatibility for that phase be considered verified.

A test passing on one database engine must never be treated as proof that it works on the other.

---

### Existing Unrelated Test Failures

A pre-existing test failure that is clearly unrelated to the current phase does not automatically block completion of the phase.

However, it must be:

1. reproduced and identified,
2. clearly documented,
3. demonstrated to be unrelated to the current changes,
4. preserved for the appropriate future phase instead of being hidden, deleted, or weakened.

Never modify unrelated business logic merely to make an old test pass.

---

### Database-Specific Features

Do NOT introduce PostgreSQL-only or MySQL-only behavior merely for convenience.

If there is a critical architectural, domain, correctness, integrity, or significant performance reason that genuinely requires a database-specific feature:

STOP before implementing that dependency.

Report:

1. what database-specific feature is required,
2. why the portable implementation is insufficient,
3. why the feature is important,
4. impact on PostgreSQL,
5. impact on MySQL/local development,
6. available portable alternatives,
7. recommended architectural approach.

Wait for explicit approval before intentionally dropping dual-database compatibility.

Do not silently introduce database-specific dependencies.

---

### Compatibility Fixes

If a test or migration fails on one database:

- diagnose the actual incompatibility;
- prefer a portable fix;
- verify the fix on BOTH database engines;
- do not fix PostgreSQL by breaking MySQL;
- do not fix MySQL by breaking PostgreSQL;
- do not hide migration problems using unnecessary `Schema::hasTable()` or `Schema::hasColumn()` guards;
- do not weaken constraints merely to make tests pass.

Examples of areas requiring special attention:

- identifier/index name lengths
- enum behavior
- boolean handling
- JSON operations
- decimal/numeric behavior
- timestamps/default expressions
- case sensitivity
- ALTER COLUMN operations
- foreign-key behavior
- unique constraints with nullable values
- raw SQL
- transaction behavior

---

### Required Implementation Report

Every database-backed phase report must include:

Database Compatibility:
- MySQL migration result
- MySQL phase-test result
- PostgreSQL migration result
- PostgreSQL phase-test result
- Full regression result where executed
- Compatibility issues discovered
- Compatibility changes made
- Any intentionally database-specific behavior

Do not report a phase as fully database-verified if only one database engine was tested.

## Super Admin & Company Context

This is a project-wide multi-tenancy rule and applies to every current and future company-owned model, resource, workflow, and phase.

### Core Rule

Every company-owned business record must belong to a company.

- Keep `company_id` required unless the domain explicitly defines a platform-level record.
- Do NOT make `company_id` nullable merely to support Super Admin operations.
- Reuse the existing `HasCompany` trait/global scope. Do not create a second tenancy system.

### Normal Company Users

For normal company users:

- `company_id` must be derived automatically from the authenticated user's company context.
- The user must never be allowed to manually select, override, or submit another `company_id`.
- Company isolation must be enforced server-side through the existing tenancy architecture, policies, services/actions, and relationship validation.
- Never rely only on hidden Filament fields or frontend restrictions for tenant security.

### Platform Super Admin

A platform Super Admin may operate across companies and may not have a normal `company_id`.

For Super Admin:

- Super Admin must be able to view/manage company-owned records across companies through a deliberate and centralized privileged path.
- When creating a top-level company-owned record without an existing company context, Super Admin must explicitly select the target company.
- The Company selector must be required and validated server-side.
- Missing company context must produce a validation/domain error, never a SQL `company_id has no default value` error.
- Do not scatter `withoutGlobalScope()` calls throughout resources, controllers, or services. Cross-company access must use the project's centralized Super Admin tenancy mechanism.

### Mandatory Super Admin Company Selector

For **every top-level company-owned Resource** (a record created without an
existing parent aggregate), the create form must show a required `Company`
selector to a Super Admin. This applies to every current and future phase,
not only to Properties or Projects.

- Normal company users never see or submit this selector; their company is
  derived from their authenticated company context.
- Super Admin selectors must list valid Companies and server-side creation
  must validate and persist the selected `company_id`.
- A create action must never silently fall back to the Super Admin's nullable
  `user.company_id`.
- A missing Super Admin selection must return a clear validation error such as
  `Select the company that will own this record.`, never a database default
  value error.

This selector rule does **not** apply to a child created within an existing
parent screen. For example, a Budget Item created inside a Project Budget,
a Unit created inside a Property, or a Scope Item created inside a Design
Package must inherit the parent company's ID automatically and must not offer
an override selector.

### Child / Related Records

When creating a child record from an existing company-owned parent:

- Inherit `company_id` from the parent record whenever possible.
- Do not ask Super Admin to select the company again when the parent already establishes company context.
- Never derive a child record's company from the Super Admin user's own `company_id`.
- Reject cross-company relationships server-side.

Example:

Super Admin selects Company A
→ creates Acquisition for Company A
→ acquisition properties, parties, due diligence, ownership records, documents, etc. inherit Company A where applicable.

### Relation-Manager Action Rule

The rule applies equally to a standard Filament `CreateAction` and a custom
modal action that calls a domain service. A child record must always receive
its company from its existing parent aggregate, never from the authenticated
user and never from a browser payload.

- Standard relation-manager creates must set `company_id` in both
  `mutateFormDataUsing()` and `mutateFormDataBeforeCreate()` using explicit
  assignment (`$data['company_id'] = $this->getOwnerRecord()->company_id`).
- Custom relation-manager actions must call a service/action that derives and
  persists the child `company_id` from the parent record server-side.
- The service/action must verify that every selected child, category, item,
  vendor, or other related record belongs to that same parent company.
- Every phase test suite must include at least one Super Admin child-create
  path. A missing context must raise a validation error, never reach SQL as a
  `company_id has no default value` error.

### Filament UX

Normal user:
`Company context → automatic → selector hidden/not editable`

Super Admin:
`No parent/company context → required Company selector`
`Existing parent context → inherit company automatically`

### Authorization & Security

Super Admin bypass of company isolation is a privileged authorization path, not an absence of tenancy rules.

Always verify:

- Super Admin privilege server-side.
- Selected company exists and is valid.
- Normal users cannot inject another `company_id`.
- Related records belong to the same company.
- Policies remain enforced where applicable.

### Testing Requirement

Every phase introducing company-owned models must test, where applicable:

1. Normal user creates data only for their own company.
2. Normal user cannot inject another `company_id`.
3. Normal user cannot access another company's data.
4. Super Admin can operate for a selected company.
5. Super Admin creation without required company context fails validation safely.
6. Child records inherit the parent's company.
7. Cross-company relationships are rejected.
8. Super Admin cross-company access works only through the intended privileged mechanism.

Before implementing new company-owned models, inspect and reuse the existing Super Admin and `HasCompany` architecture rather than introducing phase-specific tenancy behavior.

For every module, table, relationship, field, status, and workflow, the coding agent must first determine how the existing system maps to the approved target design.

Use one or more of the following actions:

| Action             | Meaning                                                                                                               |
| ------------------ | --------------------------------------------------------------------------------------------------------------------- |
| `REUSE`            | Existing structure already represents the domain correctly.                                                           |
| `CREATE`           | New table, relation, model, or concept is missing completely.                                                         |
| `ALTER`            | Existing structure is conceptually correct but needs schema changes.                                                  |
| `ADD_RELATIONSHIP` | Existing entities are correct but required relationships are missing.                                                 |
| `RENAME`           | Existing concept is valid but its name is misleading or inconsistent.                                                 |
| `RESTRUCTURE`      | Existing implementation represents the domain incorrectly and requires significant redesign.                          |
| `SPLIT`            | One existing concept currently mixes multiple business responsibilities and must be separated.                        |
| `MERGE`            | Multiple existing structures represent the same business concept without a valid reason.                              |
| `DROP`             | Obsolete column, relation, constraint, table, or implementation should be removed after dependency and data analysis. |
| `MIGRATE_DATA`     | Existing records must be transformed into a new structure before old structures are removed.                          |

These actions are not mutually exclusive.

Example:

```text
Existing payments table
        ↓
RESTRUCTURE + SPLIT
        ↓
payment_schedules
installments
payments
payment_allocations
        ↓
MIGRATE_DATA
        ↓
DROP obsolete columns / legacy logic
```

---

# 3. Do Not Duplicate Existing Domain Concepts

Before creating any new table or model, inspect the current project.

Do not create parallel concepts such as:

```text
properties_v2
new_units
sales_contracts
new_payments
unit_features_new
```

if the existing structure can be safely evolved.

The preferred order is:

```text
REUSE
   ↓
ALTER
   ↓
ADD RELATIONSHIP
   ↓
RESTRUCTURE
   ↓
CREATE replacement only when truly necessary
```

A new table must represent a genuinely separate business concept, not merely a different implementation preference.

---

# 4. Domain Rules Override Legacy Schema Assumptions

Existing schema must not force incorrect business modeling.

Examples:

## 4.1 Payments

The current system may use one `payments` table for both:

- money due,
- money received.

The approved domain separates these concepts:

```text
Payment Schedule = payment plan
Installment      = amount due
Payment          = actual money received
Payment Allocation = distribution of a payment across installments
```

Therefore the old schema must be restructured rather than preserved merely because it already exists.

---

## 4.2 Contracts

Existing `contracts` should be reused where possible, but the target contract model must support the required domain cases.

Contracts are generic business agreements, not merely rental records.

Contract behavior may include:

```text
Sale
Rent
Acquisition-related agreements
Partnership-related agreements where applicable
```

The exact relationships and supported types are defined in the relevant phase specifications.

Do not create separate contract tables unless a future domain requirement truly demands separate aggregates.

---

## 4.3 Properties, Units, and Unit Features

Existing domain concepts such as and others :

```text
properties
units
unit features
```

must be reused if they already represent the approved domain correctly.

Only alter them when the target specifications require:

- additional fields,
- different nullability,
- new relationships,
- constraints,
- history,
- lifecycle behavior.

---

# 5. Existing System Inspection Is Mandatory

Before implementing a phase, inspect all relevant existing code.

At minimum inspect:

```text
migrations
models
relationships
enums / constants
services
repositories if present
controllers
API resources
requests / validation
Filament resources
Livewire components
jobs
events / listeners
notifications
seeders
factories
tests
frontend API contracts
frontend pages/components using the affected data
```

A database migration is not complete if application code still depends on the legacy structure.

Schema changes must include impact analysis across the full application.

---

# 6. Migration-First Safety Rule

Never destroy legacy structures before existing data and dependent logic are accounted for.

For destructive or structural changes, use this order where applicable:

```text
1. Add target schema
2. Backfill / transform existing data
3. Update models and relations
4. Update application logic
5. Update APIs
6. Update admin/frontend usage
7. Validate migrated data
8. Remove obsolete schema
9. Remove obsolete code
10. Add/adjust tests
```

Do not combine unsafe destructive operations into a single migration when a staged migration is safer.

---

# 7. Nullability Is a Domain Decision

Do not make fields nullable simply to make migrations succeed.

Every nullable field must be justified by the business domain.

Example:

```text
units.planned_unit_id
```

is nullable because an actual unit may exist without originating from a planned unit.

By contrast:

```text
installments.payment_schedule_id
```

should normally be required because an installment belongs to a payment schedule in the approved model.

General rule:

```text
nullable = valid absence in the business domain
not
nullable = easier database migration
```

When old records do not satisfy a newly required field:

1. determine whether a valid value can be derived,
2. backfill it,
3. introduce an explicit migration/default strategy,
4. only use nullable if the domain allows absence.

---

# 8. Required vs Optional Fields

Every phase specification must explicitly classify important fields as:

```text
required
nullable
conditionally required
derived
system-generated
```

Conditionally required fields must document the condition.

Example:

```text
cancelled_at
cancellation_reason
```

are nullable while a contract is active but become relevant when status becomes `cancelled`.

---

# 9. Multi-Tenancy Rule

The application is multi-tenant.

Business data must be scoped to the owning company whenever the concept belongs to a company.

Typical pattern:

```text
company_id
```

Do not add `company_id` mechanically to every table.

Use it when the entity is directly company-owned or when tenant isolation requires it.

For child tables whose company can be safely and unambiguously derived through a required parent, avoid redundant tenant columns unless there is a clear reason such as:

- tenant query performance,
- data isolation enforcement,
- independent lifecycle,
- cross-parent relationships.

All tenant-sensitive queries must prevent cross-company data access.

---

# 10. Company Isolation

No user should be able to:

- access another company's records,
- associate records across tenants accidentally,
- submit foreign IDs belonging to another tenant,
- infer another company's data through API endpoints.

Tenant validation must apply to:

```text
API requests
Filament actions
Livewire actions
background jobs
service methods
relationship selectors
imports
exports
reports
```

---

# 11. Party Model Principle

Where the domain needs reusable external actors, use the approved `parties` concept rather than duplicating separate identity tables unnecessarily.

A party may represent business actors such as:

```text
individual
company
engineering office
contractor
supplier
land owner
partner
customer where appropriate
```

Specific role is contextual.

Example:

```text
contract_parties
```

defines the role of a party within a contract.

Do not encode every role as a separate physical table if the same real-world actor can participate in multiple roles.

---

# 12. Role Is Contextual

Avoid storing permanent domain roles directly on reusable actors when the role belongs to a relationship.

Prefer:

```text
party
   ↓
contract_parties.role
```

over permanently labeling the party itself as:

```text
seller
buyer
land_owner
contractor
```

when that party can hold different roles in different transactions.

---

# 13. Separate Business Concepts Even When They Look Similar

Do not collapse different business meanings into one table simply because their columns look similar.

Approved examples:

```text
Property ≠ Acquisition ≠ Ownership

Planned Unit ≠ Actual Unit

Listing ≠ Unit

Lead ≠ Inquiry

Offer ≠ Reservation

Reservation ≠ Contract

Contract ≠ Ownership Transfer

Payment Schedule ≠ Installment ≠ Payment

Handover ≠ Ownership Transfer
```

The system should model business meaning, not merely similar database shapes.

---

# 14. Preserve History

Historical business records should not be overwritten when history matters.

Examples include:

```text
property ownership history
unit ownership history
unit status history
document versions
design submissions
review findings
revisions
approvals
contract cancellation
payment records
handover inspections
```

Prefer closing the previous record and creating a new record over rewriting historical truth.

Typical history fields may include:

```text
start_date
end_date
started_at
ended_at
effective_from
effective_to
```

depending on the domain.

---

# 15. Soft Delete vs Business Status

Do not use `deleted_at` as a substitute for a business state.

Examples:

```text
cancelled contract
expired reservation
closed listing
completed handover
```

are domain states, not deleted records.

Soft deletes may still be used for administrative recovery when appropriate, but they must not replace lifecycle statuses.

---

# 16. Cancellation Is Not Deletion

When a business record is cancelled:

```text
preserve the record
preserve related history
preserve related payments
preserve audit information
change business state
```

Do not delete records merely because a deal or contract was cancelled.

Example contract fields may include:

```text
status
cancelled_at nullable
cancellation_reason nullable
```

A cancelled contract does not return to active.

A later agreement should normally be represented by a new contract.

---

# 17. Status Design

Statuses must represent real business lifecycle states.

Each phase specification must define:

```text
allowed statuses
valid transitions
who/what can trigger them
side effects
conditions required before transition
```

Avoid arbitrary status strings scattered throughout application code.

Use centralized enums/constants where appropriate.

Do not introduce statuses without a clear business meaning.

---

# 18. State Changes Must Be Explicit

Important transitions should be implemented through explicit domain actions rather than raw field updates.

Example:

```text
Reservation: active → cancelled
```

may require:

```text
set cancellation data
evaluate unit availability
record activity
send notification
```

Therefore prefer a dedicated domain/service action over:

```php
$reservation->update(['status' => 'cancelled']);
```

when the transition has business side effects.

---

# 19. Financial State Separation

The application must distinguish:

```text
Planned
Committed
Actual
Paid
```

These are different financial meanings.

For example:

```text
Budget Item = planned amount
Commitment = obligation
Actual Cost = recognized/incurred cost
Payment = cash movement
```

Do not infer one automatically from another unless the domain specification explicitly defines such behavior.

---

# 20. Money Rules

Financial fields must define:

```text
amount
currency
precision
business meaning
```

Do not use floating-point storage for monetary amounts.

Use appropriate decimal database types.

The project may deal with multiple currencies. Never assume one global currency unless a specific module explicitly guarantees it.

Where money is stored, currency should either:

1. be stored with the record, or
2. be safely inherited from an immutable parent context.

Avoid silent currency conversion.

---

# 21. Payments Global Rule

`Payment` is the one canonical record of an actual cash movement. It must
never double as a due amount, installment, commitment, or expense.

`PaymentAllocation` is the one canonical allocation mechanism. Its permitted
target depends on the financial direction:

```text
Phase 04 — outgoing project cost
Payment → Payment Allocation → Actual Cost

Phase 08 — incoming customer collection
Payment → Payment Allocation → Installment
```

Phase 08 extends these same tables and allocation rules; it must not introduce
a second payments or allocations table.

Definitions:

### Payment Schedule

The agreed payment plan.

### Installment

An amount that becomes due on a specific date.

### Payment

Actual money moved. Phase 04 records outgoing project-cost payments; Phase 08
adds incoming customer receipts through the same model with an explicit
direction/context.

### Payment Allocation

The portion of an actual payment applied to one approved settlement target:
an `ActualCost` in Phase 04 or an `Installment` in Phase 08.

This enables:

```text
partial payments
one payment covering multiple installments
multiple payments covering one installment
custom payment plans
down payments
irregular schedules
overdue tracking
```

For customer installments, canonical calculations are:

```text
installment_paid =
SUM(payment_allocations.amount)

installment_remaining =
installment.amount - installment_paid
```

Do not use a manually maintained `paid_amount` column as the financial source of truth unless a later specification explicitly introduces it as a cached value.

---

# 22. Payments Are Never Deleted Due to Contract Cancellation

A real payment represents historical money movement.

Cancelling a contract does not erase the payment.

If money is returned, use an explicit future refund/reversal/adjustment mechanism rather than deleting the original payment.

---

# 23. Contracts Global Rule

A contract is a business agreement.

A contract is not:

```text
a PDF file
a payment schedule
an ownership record
a reservation
```

Contract documents belong in the document system.

Contract clauses belong in:

```text
contract_terms
```

Payment obligations belong in the payment schedule system.

Ownership changes belong in ownership history.

---

# 24. Contract Terms

Contract terms represent textual clauses and rules.

Target concept:

```text
contract_terms
- id
- contract_id
- title
- content
- sort_order
```

Do not store financial schedules or installment rows as contract terms.

---

# 25. Ownership Is Independent

Ownership must be modeled explicitly.

Do not infer ownership solely from:

```text
contract status
reservation status
payment status
handover status
unit status
```

Examples:

```text
reserved unit may still be company-owned
sold contract may exist before legal ownership transfer
handover may happen independently from legal title transfer
```

Ownership history must remain a separate concept.

---

# 26. Unit Global Rule

Actual units may exist either:

```text
from project planning
or
independently / pre-existing
```

Therefore:

```text
units.planned_unit_id
```

must be nullable.

Actual unit identity, operational status, ownership, listing, and pricing are separate concerns.

---

# 27. Unit Features

Reuse the existing Unit → UnitFeature implementation if it already correctly represents the domain.

Do not create a duplicate feature system.

Only modify it if required by later specifications.

---

# 28. Unit Status vs Ownership

Unit status and ownership are independent.

Example:

```text
status = reserved
owner = company
```

is valid.

Do not automatically transfer ownership when unit status becomes:

```text
sold
reserved
handed_over
```

unless a future explicit ownership action is executed.

---

# 29. Planned Unit vs Actual Unit

Project planning uses planned units.

Operational inventory uses actual units.

```text
Project
  ↓
Building
  ↓
Floor
  ↓
Planned Unit
  ↓
Actual Unit
```

A planned unit is a design/planning concept.

An actual unit is the real operational asset used in:

```text
listing
reservation
contract
ownership
handover
```

Do not use planned units directly for sales operations.

---

# 30. Property vs Project

A property/land asset and a development project are different concepts.

A project may involve multiple properties.

A property may participate in multiple project contexts over time if the domain permits it.

Use:

```text
project_properties
```

for the many-to-many relationship.

Do not embed land acquisition details directly into the project merely for convenience.

---

# 31. Acquisition Rule

Acquisition represents how the company obtained or entered into rights involving a property.

Examples may include:

```text
cash purchase
installment purchase
partnership
land-for-units
profit-sharing arrangement
owned_existing
```

Acquisition is not the property itself.

Acquisition is not ownership history.

Use dedicated relationships:

```text
property_acquisitions
acquisition_properties
acquisition_parties
```

as defined by the acquisition specification.

---

# 32. Existing Owned Property

Property already owned before the system can still be represented using an acquisition record such as:

```text
type = owned_existing
```

when required by the target specification.

This provides a consistent historical model without inventing fake purchase transactions.

---

# 33. Documents Are Generic

Documents should use a reusable generic document system.

Canonical concept:

```text
documents
document_versions
```

A document record represents the logical document.

A document version represents a specific uploaded/generated version.

Examples:

```text
Contract
 └── Contract PDF v1
 └── Contract PDF v2

Design Document
 └── Version 1
 └── Version 2
 └── Version 3
```

Never overwrite version history when a new formal version is created.

---

# 34. Files vs Business Records

Do not treat uploaded files as the business entity itself.

Example:

```text
Contract = business record
Contract PDF = document/version
```

Likewise:

```text
Design Package = business process
Drawing PDF = document version
```

---

# 35. Generic Documents Must Not Become a Junk Drawer

Use generic documents for files and versions.

Do not move unrelated business logic into the document system.

The parent domain remains responsible for:

```text
status
approval
ownership
financial meaning
workflow
```

Documents only represent the supporting files and their history.

---

# 36. Auditability

Important business actions should be auditable.

Examples:

```text
status change
ownership change
contract cancellation
payment creation
payment allocation
reservation cancellation
approval
handover completion
critical financial updates
```

Use the existing audit/activity infrastructure if suitable.

Do not build a duplicate audit system if one already exists and can be extended.

---

# 37. created_by / updated_by

Only add explicit actor columns when they provide real business value.

Do not mechanically add:

```text
created_by
updated_by
```

to every table if an existing audit log already provides the information.

Use explicit actor fields where the business meaning is important, for example:

```text
received_by
performed_by
inspected_by
approved_by
```

These are domain roles, not generic audit metadata.

---

# 38. Foreign Keys

Use foreign keys for core relational integrity when practical.

Every FK decision must define:

```text
required vs nullable
on delete behavior
on update behavior if relevant
tenant validity
```

Do not blindly cascade-delete historical business records.

---

# 39. Delete Behavior

Prefer restrictive behavior for important business history.

Examples of records that should generally not disappear because a parent is deleted:

```text
payments
ownership history
contracts
approvals
handover records
historical submissions
```

Where deletion of a parent is possible, explicitly define whether children should:

```text
RESTRICT
SET NULL
CASCADE
remain through archival strategy
```

Do not use cascade deletion merely for convenience.

---

# 40. Referential Integrity Before Deletion

Before allowing destructive deletes, check whether the record participates in:

```text
contracts
financial transactions
ownership
handover
approvals
historical records
```

In many cases the correct action is:

```text
archive
close
cancel
disable
```

rather than delete.

---

# 41. Date and Time Conventions

Use consistent timestamp handling.

Distinguish:

```text
date-only business values
timestamps representing exact events
```

Examples:

```text
due_date          → date
start_date        → date
expected_completion_date → date

created_at        → timestamp
reserved_at       → timestamp
cancelled_at      → timestamp
completed_at      → timestamp
```

Do not store a date as a timestamp unless time-of-day is meaningful.

---

# 42. Timezone

Application timestamps should follow one consistent storage convention.

Prefer database/application UTC storage where already supported by the existing architecture, with presentation converted at the application boundary.

Do not mix timezone assumptions across modules.

---

# 43. Naming Conventions

Use clear domain names.

Prefer:

```text
payment_schedules
payment_allocations
project_buildings
project_planned_units
unit_ownerships
unit_status_histories
```

Avoid vague names such as:

```text
data
details
records
info
items
transactions
```

unless the domain meaning is already explicit from context.

---

# 44. Pivot / Relationship Tables

For meaningful many-to-many relationships, use explicit domain-aware pivot names.

Examples:

```text
project_properties
contract_parties
contract_properties
acquisition_properties
acquisition_parties
payment_allocations
```

If the relationship contains important business data, it should generally have:

```text
its own primary key where useful
timestamps where useful
domain-specific fields
model class if behavior warrants it
```

Do not treat important relationship entities as invisible pivots.

---

# 45. Constraints

Use database constraints where they protect real invariants.

Examples may include:

```text
unique business identifiers
ownership validity
non-negative monetary values
valid relationship uniqueness
foreign keys
```

Do not rely exclusively on frontend validation.

Application validation and database integrity should complement each other.

---

# 46. Percentage Rules

Percentage fields must use appropriate decimal precision.

Examples:

```text
ownership_percentage
profit_share_percentage
```

Where the domain requires shares to total 100%, enforce this at the service/domain layer and validate transactionally.

Do not assume a simple database CHECK can safely enforce sums across multiple rows.

---

# 47. Transactions

Use database transactions for multi-record business operations where partial completion would create inconsistent state.

Examples:

```text
creating a contract with parties and terms
recording a payment and allocations
ownership transfer
reservation conversion
handover completion with required state updates
```

---

# 48. Business Logic Location

Avoid putting complex business workflows directly in:

```text
controllers
Filament resource callbacks
Livewire components
React components
```

Important domain actions should live in reusable backend services/actions/domain classes.

UI layers should orchestrate and display, not become the only location containing business rules.

---

# 49. Validation Layers

Use layered validation:

```text
Request/UI validation
        ↓
Domain/business validation
        ↓
Database constraints
```

Example:

A request may validate that `amount` is numeric.

The domain layer must also validate that a payment allocation does not exceed:

```text
payment amount
remaining installment balance
allowed tenant scope
```

---

# 50. Derived Values

Do not persist values that can be safely derived unless there is a clear performance/reporting reason.

Examples:

```text
installment paid amount
remaining installment amount
budget variance
```

If a derived value is cached:

1. define the source of truth,
2. define how it is synchronized,
3. ensure it can be rebuilt.

---

# 51. Source of Truth

Every important value must have one canonical source.

Example:

```text
Actual money received
→ payments.amount

Installment amount due
→ installments.amount

Allocated amount
→ payment_allocations.amount
```

Avoid two writable columns representing the same financial fact.

---

# 52. API Compatibility

When changing schema used by existing APIs:

1. identify affected endpoints,
2. preserve backward compatibility temporarily where required,
3. update frontend consumers,
4. remove legacy response fields only after migration is complete.

Do not allow database refactoring to silently break deployed frontend flows.

---

# 53. Frontend Contract

The frontend should consume domain-oriented API data.

Do not expose database implementation details unnecessarily.

Example:

Prefer an installment API object such as:

```json
{
    "amount": 10000,
    "paid": 4000,
    "remaining": 6000,
    "status": "partially_paid"
}
```

even if `paid` and `remaining` are derived.

The API contract and database schema do not need to be identical.

---

# 54. Admin UI

Filament/admin forms must follow the same business rules as the API.

Do not allow admin users to bypass:

```text
tenant restrictions
financial validation
state transition rules
required relationships
ownership rules
```

Administrative convenience must not create invalid business records.

---

# 55. Background Jobs

Long-running or asynchronous tasks should use jobs when appropriate.

Examples:

```text
document processing
report generation
bulk notifications
AI processing
scheduled reminders
large imports
```

Domain integrity must not depend on a job eventually running if the state must be immediately consistent.

---

# 56. Notifications Are Side Effects

Notifications should react to business events.

They should not become the source of business truth.

Example:

```text
installment becomes overdue
        ↓
business state exists
        ↓
notification may be sent
```

Not:

```text
notification sent
        ↓
therefore installment is overdue
```

---

# 57. SaaS Billing Is Separate from Real Estate Payments

Two payment domains exist:

```text
A. Real estate/customer payments
B. SaaS subscription billing
```

They must not share the same core payment tables.

Real-estate payment tables represent money handled inside a real-estate company's operations.

SaaS billing represents the company paying for use of the software.

Conceptually:

```text
subscriptions
subscription_plans
subscription_invoices
subscription_payments
```

are separate from:

```text
payment_schedules
installments
payments
payment_allocations
```

---

# 58. Payment Provider Abstraction

SaaS billing must not be designed around a single provider.

The system may initially support providers such as:

```text
binance_pay
manual
bank_transfer
cash
```

with future providers possible.

Provider-specific IDs and payloads must not dictate the core subscription domain model.

---

# 59. Provider Integration Rule

External payment providers are integration adapters.

Core billing logic should understand:

```text
invoice
amount
currency
payment status
payment reference
provider
```

while provider-specific authentication, signatures, callbacks, and payload structures remain in the integration layer.

---

# 60. External Integrations

External services must be treated as replaceable adapters where practical.

Examples:

```text
AI providers
payment providers
email providers
storage providers
WhatsApp integrations
```

Do not leak vendor-specific structures throughout the entire domain layer.

---

# 61. AI Is Not a Source of Business Truth

AI may assist with:

```text
descriptions
document extraction
classification
recommendations
summaries
search
```

but critical financial, contractual, ownership, and lifecycle changes must remain deterministic and validated.

AI output should not directly mutate critical business data without explicit validation/workflow.

---

# 62. Search and Reporting

Search/reporting requirements must not distort transactional domain tables unnecessarily.

Use:

```text
indexes
query optimization
derived queries
materialized/cached structures if eventually required
```

before duplicating transactional data into multiple writable sources.

---

# 63. Performance

Add indexes based on actual query patterns.

Typical candidates may include:

```text
company_id
status
foreign keys
due_date
project_id
unit_id
contract_id
listing_id
lead_id
created_at
```

Do not create indexes blindly on every column.

Composite indexes should follow real filtering/sorting patterns.

---

# 64. Enumeration Storage

Where status/type values are finite and domain-defined, use application enums/constants and database-compatible string values unless a phase explicitly requires a different strategy.

Avoid database-native enum types if they significantly reduce database portability.

The project should remain practical across MySQL and PostgreSQL where possible.

---

# 65. Database Portability

Avoid unnecessary vendor-specific schema behavior.

The application should remain reasonably portable between:

```text
MySQL
PostgreSQL
```

Do not use database-specific syntax unless there is a strong reason and the implementation includes an alternative or clearly documents the dependency.

---

# 66. IDs

Use the project's existing primary-key strategy unless there is a domain reason to change it.

Do not mix integer IDs, UUIDs, and ULIDs arbitrarily.

New tables should follow the established project convention unless a specification explicitly states otherwise.

---

# 67. Timestamps

Use the existing Laravel timestamp convention where appropriate:

```text
created_at
updated_at
```

Add business timestamps separately when they represent domain events.

Examples:

```text
published_at
reserved_at
cancelled_at
completed_at
approved_at
```

`updated_at` must not be used as a substitute for a domain event timestamp.

---

# 68. Historical Data Must Remain Queryable

After migration, old business history should remain understandable.

Do not produce a schema where:

```text
old payments cannot be tied to contracts
old ownership records lose owners
old contracts lose parties
old documents lose versions
```

Data migration quality is part of implementation quality.

---

# 69. Legacy Columns

Do not immediately delete legacy columns merely because a new structure exists.

First determine:

```text
Is old data migrated?
Is any backend code still reading it?
Is any frontend still reading it?
Are reports using it?
Are exports using it?
Are tests using it?
```

Only then remove it.

---

# 70. Data Backfills

Backfill scripts/migrations must be deterministic where possible.

If old data cannot be mapped reliably:

1. do not silently invent values,
2. record the ambiguity,
3. use an explicit migration strategy,
4. surface records that require manual review if necessary.

---

# 71. No Fake Domain Data

Do not invent:

```text
owners
acquisition types
contract parties
payment dates
currencies
ownership percentages
```

simply to satisfy new non-null columns.

If data is unknown, follow the approved nullable/migration strategy.

---

# 72. Existing Data Compatibility

When introducing stricter constraints, first test existing records against the future rule.

Example:

Before introducing a non-null FK:

```text
identify null rows
identify invalid foreign references
derive/fix valid values
then add the constraint
```

---

# 73. Testing Requirements

Every structurally significant phase should include tests for:

```text
core relationships
tenant isolation
status transitions
financial calculations
required fields
nullable rules
cancellation behavior
historical preservation
migration-sensitive logic
```

Payment and ownership logic require especially strong test coverage.

---

# 74. Financial Tests

At minimum, payment tests should eventually cover:

```text
full payment of one installment
partial payment
multiple payments to one installment
one payment allocated to multiple installments
overpayment prevention
cross-company allocation prevention
overdue calculation
cancelled installment behavior if supported
```

---

# 75. Migration Tests / Verification

For major schema restructuring, verify:

```text
record counts
foreign-key integrity
money totals
relationship mappings
orphaned records
legacy vs migrated totals
```

Financial migration must be reconciled.

---

# 76. No Hidden Automatic Business Effects

Avoid surprising implicit behavior.

Example:

```text
Contract becomes active
```

must not automatically transfer ownership unless the approved workflow explicitly says so.

Likewise:

```text
Contract cancelled
```

must not automatically delete payments.

```text
Reservation cancelled
```

may affect unit availability only according to explicit business rules.

---

# 77. Explicit Side Effects

Each phase specification should identify side effects such as:

```text
status changes
notifications
financial changes
ownership changes
availability changes
document generation
audit events
```

Side effects should not be guessed by the coding agent.

---

# 78. Domain Workflow Before CRUD

CRUD alone is not enough.

For every important entity, understand:

```text
how it is created
why it exists
what can change
what cannot change
which transitions are valid
what historical data must remain
what financial effect it has
```

Implementation must follow workflow, not merely generate resource pages.

---

# 79. CRUD Rules Must Be Domain-Aware

Examples:

A payment can be created but should not necessarily be freely edited after reconciliation.

An ownership history row should not be casually overwritten.

A completed handover should not be editable like a draft.

An approved design submission may require a new revision rather than direct file replacement.

CRUD permissions depend on lifecycle state.

---

# 80. Phase Boundaries

Do not move responsibilities into the wrong module.

Examples:

```text
Unit price → Marketing / Sales
not Unit Setup

Construction progress → Construction
not Budgeting

Ownership → Ownership history
not Contract status

Actual payment → Payments
not Installment row itself
```

Each phase specification defines its own responsibility.

---

# 81. Project Management Is a New Major Domain

Project management includes new concepts that may require mostly new schema.

Examples:

```text
project_buildings
project_building_floors
project_planned_units
planned_unit_specifications

project_design_packages
design_package_assignments
design_package_scope_items
design_package_submissions
design_package_reviews
design_review_findings
design_package_revisions
design_package_approvals

project_budgets
budget_categories
budget_items

project_constructions
construction_work_packages
construction_tasks
construction_progress_updates
construction_inspections
construction_issues
construction_delays
```

These should be created according to their phase specifications, while linking to existing project/company structures where appropriate.

---

# 82. Project Status Is Not Task Status

Do not infer overall project status directly from one task or work package.

Project lifecycle, construction lifecycle, design lifecycle, and task lifecycle are separate state machines.

---

# 83. Design Review History

Design review uses explicit rounds and history.

Do not overwrite prior submissions or document versions.

Conceptually:

```text
Package
  ↓
Submission
  ↓
Review
  ↓
Findings
  ↓
Revision
  ↓
New Submission
  ↓
Approval
```

A resubmission is a new submission, not mutation of the old one.

---

# 84. Construction Progress

Progress percentage and financial cost are different.

Do not treat:

```text
50% construction progress
```

as automatically:

```text
50% budget spent
```

unless an explicit financial rule later defines such behavior.

---

# 85. Task Completion

Where defined by the construction specification, completion may require both:

```text
progress = 100%
inspection passed
```

Do not mark tasks complete based only on percentage if inspection is required.

---

# 86. Marketing / CRM Separation

The sales pipeline uses distinct concepts:

```text
Listing
   ↓
Lead
   ↓
Interest
   ↓
Inquiry
   ↓
Activity / Follow-up
   ↓
Offer
   ↓
Reservation
   ↓
Contract
```

Do not collapse them into a single "customer request" table if the approved specification defines them separately.

---

# 87. Lead Can Have Multiple Interests

A lead may be interested in multiple listings.

Use a relationship such as:

```text
lead_interests
```

rather than forcing:

```text
leads.listing_id
```

as the only possible interest.

---

# 88. Offer Is Not Contract

An offer is negotiable/pre-contractual.

A contract is a formal agreement.

Do not use contract records merely to represent quotations or negotiations.

---

# 89. Reservation Is Not Sale

A reservation temporarily represents commercial intent/hold.

It does not by itself mean:

```text
ownership transferred
sale completed
full payment received
handover completed
```

---

# 90. Reservation Amount Is Not Payment

A reservation may specify an expected reservation amount.

That expected amount is not an actual payment.

Actual received money must enter the canonical payment system.

---

# 91. Handover Is a Separate Process

Handover should be modeled as a workflow.

Conceptually:

```text
Eligibility
   ↓
Handover record
   ↓
Inspection
   ↓
Punch list / issues
   ↓
Resolution
   ↓
Final inspection
   ↓
Handover protocol
   ↓
Completed
```

Do not represent handover merely with:

```text
units.handed_over = true
```

---

# 92. Handover vs Ownership

Handover is physical/operational delivery.

Ownership transfer is legal/economic ownership.

They must remain separate.

---

# 93. Project Closing

Project closing is the final project lifecycle phase and must not be treated as merely:

```text
projects.status = completed
```

Its detailed requirements will be defined in the project-closing specification.

Potential responsibilities may include:

```text
operational closure
financial reconciliation
final reporting
document completeness
open issue checks
archive/closure readiness
```

Do not implement details until the relevant specification is approved.

---

# 94. Do Not Invent Unspecified Domain Logic

If a phase specification does not define an important behavior, do not invent a permanent business rule casually.

Examples:

```text
automatic penalties
automatic ownership transfer
automatic contract cancellation
automatic refund rules
automatic commission calculation
```

Instead:

1. identify the missing rule,
2. preserve extensibility,
3. request clarification before committing irreversible schema/business logic.

---

# 95. Avoid Over-Engineering

The target is a robust real-world SaaS, not an enterprise architecture exercise.

Do not introduce unnecessary:

```text
event sourcing
microservices
CQRS
complex generic metadata engines
universal workflow engines
polymorphic abstraction everywhere
```

unless a concrete requirement justifies them.

Prefer clear domain tables and maintainable Laravel architecture.

---

# 96. Avoid Under-Modeling

At the same time, do not collapse real business concepts merely to reduce table count.

The correct question is:

> Does this concept have its own business meaning, lifecycle, relationships, or history?

If yes, it may deserve its own entity.

---

# 97. Existing Architecture Preference

Use the existing Laravel application structure unless there is a strong reason to refactor it.

Prefer incremental evolution.

Do not introduce a completely new architecture style in one module that conflicts with the rest of the project unless the change is intentionally project-wide.

---

# 98. Implementation Order

For each phase:

```text
1. Read this global rules file
2. Read the relevant phase specification
3. Inspect existing code/schema
4. Produce current → target mapping
5. Identify REUSE / CREATE / ALTER / etc.
6. Define migration/data strategy
7. Implement schema
8. Update domain/backend logic
9. Update admin/API/frontend dependencies
10. Add tests
11. Verify existing data
12. Remove obsolete legacy code only when safe
```

---

# 99. Required Pre-Implementation Output from Coding Agent

Before making substantial changes to a phase, the coding agent should summarize:

```text
Existing structures found
Target structures required
What will be reused
What will be created
What will be altered
What will be restructured
What legacy fields/tables may be removed
What data must be migrated
What application code is affected
Any unresolved domain ambiguity
```

This prevents accidental duplication and destructive schema decisions.

---

# 100. Gap Analysis Rule

The dedicated file:

```text
00-existing-system-gap-analysis.md
```

will map the current project to the approved target design.

This file should eventually document, per module:

```text
Current implementation
Target design
Gap
Action
Migration impact
Affected code
Risk
```

This global file defines the rules.

The gap analysis defines how those rules apply to the current repository.

---

# 101. Phase Specifications Are Authoritative for Detail

This file contains global rules only.

Detailed schema and workflow decisions belong to the phase files:

```text
01-pre-project-property-acquisition.md
02-project-planning.md
03-design-engineering-approvals.md
04-budgeting.md
05-construction.md
06-unit-setup.md
07-marketing-crm-sales.md
08-contracts-payments.md
09-handover.md
10-project-closing.md
11-platform-cross-cutting.md
12-saas-subscriptions-and-billing.md
```

If a phase file intentionally defines a more specific rule, the more specific approved phase rule takes precedence over a generic convention in this document.

---

# 102. Final Source-of-Truth Hierarchy

Use the following order when deciding implementation behavior:

```text
1. Approved phase specification
2. This global domain/conventions specification
3. Approved existing-system gap analysis
4. Existing code/schema
5. Implementation convenience
```

However, the existing repository must always be inspected before execution because it determines:

```text
migration path
compatibility impact
data preservation strategy
dependency changes
```

The specifications define **where the system should go**.

The existing codebase defines **where the system is starting from**.

---

# 103. Final Principle

The project should evolve from the current implementation into the approved domain model without:

```text
duplicating existing concepts
preserving incorrect legacy assumptions
losing historical data
mixing separate business concepts
breaking tenant isolation
breaking financial correctness
introducing unjustified complexity
```

Every schema change must have a domain reason.

Every destructive change must have a migration reason.

Every important state change must have a business reason.

And every new table must represent a real concept that the existing system does not already model correctly.

# 104. Existing Global Scope / Tenant Trait Is Canonical

The project already has an existing reusable Global Scope / Trait mechanism used by models for tenant/company scoping.

This mechanism is considered part of the approved existing architecture.

Rules:

```text
- Reuse the existing Global Scope / Trait.
- Do not replace it with a second tenant architecture.
- Do not duplicate company/tenant filters manually in every query.
- New tenant-scoped models must adopt the existing mechanism.
- Modified models must preserve compatibility with it.
```

The coding agent must inspect how the current trait/scope works before adding new tenant-aware models.

If a model belongs to a company-scoped domain, tenant isolation should be achieved consistently through the existing mechanism unless the specific model is intentionally global.

Do not introduce:

```text
duplicate global scopes
parallel tenant middleware
manual company_id filtering everywhere
new tenancy packages
alternative tenant traits
```

unless explicitly required by a later approved specification.

---

# 105. Model Relationship Quality Is Mandatory

Every model must expose clear, correct, and useful Eloquent relationships.

Examples include:

```text
belongsTo
hasOne
hasMany
belongsToMany
hasManyThrough
morph relationships only when truly justified
```

Relationships must reflect the approved domain, not merely database convenience.

For every model, verify:

```text
- parent relationships
- child relationships
- many-to-many relationships
- pivot models where the relation has business meaning
- inverse relationships
- nullable relation behavior
- tenant consistency
```

Do not leave relationships implicit if the application repeatedly depends on them.

Relationship names should be domain-oriented and readable.

Prefer:

```php
$contract->paymentSchedules
$unit->ownerships
$lead->interests
$project->buildings
```

over vague or technical names.

---

# 106. Eager Loading and N+1 Prevention

N+1 query problems must be actively prevented.

The coding agent must review query behavior in:

```text
Filament tables
Filament relation managers
Filament widgets
Livewire components
API resources
controllers
services
reports
exports
background jobs
loops processing Eloquent collections
```

Use deliberate eager loading where relationships are known to be required.

Typical tools include:

```php
with()
withCount()
withSum()
load()
loadMissing()
```

Choose the technique appropriate to the use case.

Do not access lazy-loaded relationships repeatedly inside large loops.

Bad pattern:

```php
foreach ($contracts as $contract) {
    echo $contract->customer->name;
}
```

when `customer` was not eager-loaded.

Preferred approach:

```php
Contract::query()
    ->with('customer')
    ->get();
```

where that relationship is actually required.

---

# 107. Avoid Blind Eager Loading

Do not solve N+1 issues by globally eager-loading every relationship.

Avoid adding large relationship sets to:

```php
protected $with = [...]
```

unless those relationships are truly required in almost every query.

Excessive eager loading can create:

```text
unnecessary queries
large payloads
high memory usage
slower Filament pages
slow API responses
duplicate data loading
```

Load only what the current use case needs.

Performance goal:

```text
prevent N+1
without
over-fetching
```

---

# 108. Query Performance Standards

Every important query path must be designed for performance.

Consider:

```text
appropriate eager loading
selecting only needed columns where beneficial
pagination
database indexes
composite indexes based on real query patterns
withCount / withSum instead of collection loops
database aggregation instead of PHP aggregation where appropriate
avoiding repeated identical queries
avoiding loading full collections when only existence/count is needed
```

Prefer:

```php
->exists()
->count()
->sum()
```

at the query level when full model hydration is unnecessary.

Do not load thousands of models into memory merely to compute simple aggregates.

---

# 109. Foreign-Key and Filtering Indexes

When adding or altering tables, inspect the expected query patterns.

Common index candidates include:

```text
company_id
project_id
property_id
unit_id
contract_id
lead_id
listing_id
payment_schedule_id
installment_id
status
due_date
created_at
```

Foreign keys used heavily for joins should normally be indexed.

Composite indexes should match actual common query patterns, for example:

```text
(company_id, status)
(company_id, due_date)
(project_id, status)
(contract_id, status)
```

Do not add indexes mechanically without considering write cost and actual usage.

---

# 110. Clean Code Is a Project Requirement

New implementation must follow clean, readable, maintainable Laravel code.

Avoid:

```text
fat controllers
fat Filament resource classes
duplicated query logic
duplicated validation
duplicated tenant filters
business rules inside Blade/React components
large anonymous callbacks containing domain logic
magic strings for statuses
copy-pasted relationship logic
```

Prefer:

```text
clear model relationships
Enums / constants for states
Actions / Services for important business operations
Form Requests / dedicated validation where appropriate
query scopes for reusable query intent
small focused methods
meaningful class and method names
database transactions for multi-step operations
```

Do not introduce unnecessary architectural layers merely to appear "clean".

The goal is:

```text
clarity
consistency
testability
maintainability
performance
```

---

# 111. Business Logic Must Be Reusable

Important domain operations should not exist only inside one UI action.

If the same business operation can be triggered from:

```text
Filament
API
Livewire
job
future mobile app
```

the core business logic should live in a reusable backend action/service/domain layer.

Example:

```text
CancelReservationAction
RecordPaymentAction
AllocatePaymentAction
CompleteHandoverAction
TransferUnitOwnershipAction
```

The exact project naming convention should follow the existing architecture.

---

# 112. Filament Must Use the Existing Architecture

Filament is an interface over the domain, not a separate business implementation.

Filament resources, pages, relation managers, widgets, and actions must:

```text
reuse model relationships
reuse tenant/global scope behavior
reuse domain services/actions
reuse validation/business rules
respect permissions
respect state transitions
use efficient Eloquent queries
```

Do not implement a second version of the same workflow inside Filament.

---

# 113. Filament / Livewire Should Avoid Full Page Reloads

Normal Filament interactions should be reactive and should not require a full browser page reload.

For ordinary operations such as:

```text
create
edit
delete
status change
attach/detach
approve
cancel
record payment
resolve issue
update table data
```

prefer Filament and Livewire mechanisms that update only the affected component/state.

Use, where appropriate:

```text
Filament Actions
Livewire component updates
table refresh
events
modals
notifications
reactive form state
relation managers
component re-rendering
```

Do not use full page reload as a general state synchronization technique.

### Reactive callback contract

When a Filament field uses `->live()` with `afterStateUpdated()`, use Filament's
supported named injections. The newly selected value must be declared as
`$state` (and state mutation through `Forms\Set $set`), for example:

```php
->afterStateUpdated(function ($state, Forms\Set $set): void {
    $record = Model::find($state);
    // Populate dependent fields only after verifying the selected parent.
})
```

Do not invent a parameter name such as `$id`: Filament resolves callback
parameters by name and will throw a `BindingResolutionException` when it cannot
resolve one. Every new reactive selector must be manually tested by opening its
modal, selecting a record, confirming dependent fields populate, then saving.

### Relationship labels

Foreign-key IDs are internal implementation details. In Filament forms, tables, and infolists, never expose a raw relationship field such as `review_id`, `submission_id`, or `budget_item_id` to a normal user. Give the field a concise human label (for example, `Review`, `Formal submission`, or `Budget item`) and use a meaningful related-record value in its options and display columns.

Relationship selectors must be scoped to the same parent aggregate, Company, and business state accepted by the server-side service. For example, a Project financial record may offer only that Project's current approved budget items. A selector must never offer a record that the service will necessarily reject. Custom relation-manager actions must convert validation and authorization failures into a clear Filament notification; a failed action must never appear to do nothing.

### Status badge colors

Every user-facing status must use a semantic Filament badge color rather than one uniform background. Keep the mapping simple and consistent across modules:

- `gray`: draft, planned, inactive, cancelled, archived.
- `info`: submitted, assigned, queued, in progress, under review.
- `warning`: pending approval, revision required, blocked, attention needed.
- `success`: approved, completed, paid, cleared, active/available when positive.
- `danger`: rejected, failed, overdue, voided, cancelled when it represents a negative outcome.

Choose the color by business meaning, not by the literal status name. A module may use a different color only when its workflow meaning genuinely differs; do not build a separate color system per resource.

### Business-date validation

Validate dates by their business meaning in both the Filament form and the server-side service; a DatePicker restriction alone is not security or domain validation.

- **Historical event dates** — payments, actual costs, corrections, receipts, inspections, and completed workflow events — may be today or in the past, but must not be future-dated.
- **Planned/target dates** — estimated completion, target submissions, and scheduled future work — may be future dates when the workflow permits it.
- **Derived audit timestamps** — submitted, approved, completed, created, and updated timestamps — are system-generated and must not be manually entered.
- Do not impose artificial ordering between separate valid financial events (for example, a payment can be an advance before an invoice). Add cross-date ordering only when the approved domain explicitly requires it.

The form must prevent an invalid date where practical and state the rule in helper text. The service must reject a crafted or bypassed request with a clear validation notification.

### Relation-manager usability review

For every important Project or parent-record tab, review the complete user journey before closing a phase:

1. Create a realistic child record.
2. Use each permitted workflow action in its valid sequence.
3. Open `View` and confirm it surfaces the record's important relationships and history (for example, budget categories/items, commitment amendments, actual-cost allocations, or payment allocations).
4. Confirm a read-only user can view but cannot mutate, and a user without the permission sees neither the action nor its data.

The automated suite must cover the underlying service, authorization, tenant isolation, and state transition. Manual Filament verification must cover action visibility, labels, status colors, and the View modal's relationship display. Do not declare a workflow complete merely because its database operation succeeds.

---

# 114. No Forced Browser Reloads for Normal CRUD

Avoid patterns such as:

```javascript
window.location.reload();
location.reload();
```

and avoid unnecessary redirect-back cycles after normal Filament actions.

The preferred flow is:

```text
User Action
    ↓
Domain Action / Service
    ↓
Database Transaction
    ↓
Livewire / Filament State Refresh
    ↓
Affected UI Updates
```

not:

```text
User Action
    ↓
Database Update
    ↓
Redirect
    ↓
Reload Entire Page
    ↓
Reload All Data
```

---

# 115. When Full Navigation Is Acceptable

A full page navigation or route change is acceptable when the workflow genuinely moves to another screen.

Examples:

```text
opening a dedicated detail page
navigating from creation wizard to final record page
switching to a completely different module
authentication flow
external payment redirect
```

The rule is not "never navigate".

The rule is:

> Do not use full-page refresh merely to make updated data visible.

---

# 116. Reactive UX Must Remain Efficient

Reactive Filament/Livewire behavior must not create excessive backend traffic.

Avoid:

```text
unnecessary live updates on every keystroke
large queries on every component render
reloading all relationships after every minor action
repeated aggregate queries
expensive widgets refreshing too frequently
```

Use debounce/lazy behavior where appropriate.

Refresh only the affected component/data where possible.

Performance and reactivity must be designed together.

---

# 117. Filament Tables Must Be Query-Efficient

For Filament tables:

```text
- eager-load relationships used in columns/actions
- avoid N+1 in badges, formatters, and closures
- paginate large datasets
- use database sorting/filtering
- use withCount/withSum for relationship metrics
- avoid loading full related collections just to display counts
- select required relationships deliberately
```

Any table column closure that accesses a relationship should be reviewed for query count.

---

# 118. Filament Forms Must Respect Domain Nullability and Rules

Forms must reflect the actual domain specification.

Do not make a field optional in the UI simply because the database currently allows null.

Do not make a field required in the UI if the domain legitimately allows absence.

Form behavior must follow:

```text
required
nullable
conditionally required
derived
read-only
system-generated
```

rules from the relevant phase specification.

---

# 119. Filament Relation Managers Must Use Real Relationships

Use proper Eloquent relationships for relation managers.

Do not build ad-hoc queries that reproduce relationships already defined on models.

This improves:

```text
readability
tenant safety
performance tuning
reuse
testability
```

If a relation manager becomes difficult to implement because the model relationship is unclear, inspect and correct the domain relationship first.

---

# 120. Performance Review Is Part of Definition of Done

A feature is not considered complete merely because it works functionally.

Before considering a phase complete, review:

```text
query count
N+1 behavior
eager-loading strategy
indexes
large-table pagination
relationship correctness
repeated database queries
memory-heavy collection operations
Filament/Livewire refresh behavior
```

For critical pages, especially:

```text
dashboards
property/unit listings
contracts
payments
CRM
project management tables
reports
```

performance must be treated as a first-class requirement.

---

# 121. Final Engineering Standard

All future phase implementations must satisfy four engineering goals simultaneously:

```text
1. Correct Domain Modeling
2. Clean and Maintainable Code
3. High Database/Application Performance
4. Smooth Reactive Filament UX
```

A solution that is domain-correct but creates N+1 queries is incomplete.

A solution that is fast but duplicates existing tenant architecture is incomplete.

A solution that works but relies on full page reloads for normal Filament actions is incomplete.

A solution that looks clean but hides business logic inside UI callbacks is incomplete.

The target implementation must preserve the existing architectural strengths of the project while evolving the schema and workflows according to the approved domain specifications.

# 122. Filament Shield + Policy Is Mandatory for Every New Eloquent Model

The project already uses Filament Shield and model policies as part of its authorization architecture.

This architecture is approved and must be preserved.

For **every new Eloquent model introduced by any phase**, the implementation must include an authorization strategy before the model is considered complete.

Default requirement:

```text
New Model
   ↓
Policy
   ↓
Filament Shield permissions
   ↓
Filament Resource / Relation Manager authorization
   ↓
API / Service authorization where applicable
```

Do not create a domain model and postpone its permissions until later.

For normal resource models, generate or maintain the standard permission set supported by the project/Shield convention, typically equivalent to:

```text
view_any
view
create
update
delete
delete_any
restore
restore_any
force_delete
force_delete_any
replicate
reorder
```

Only expose permissions that make sense for the model and the existing project convention.

For workflow-heavy models, add explicit action permissions where business operations are more important than generic CRUD, for example:

```text
approve
reject
cancel
submit
review
close
complete
record_payment
allocate_payment
transfer_ownership
```

A generic `update` permission must not automatically authorize sensitive business transitions.

All policies must also respect:

```text
tenant/company isolation
record ownership/context
parent resource access
business state restrictions
```

If the model is only managed through a parent relation manager, it still requires an explicit policy/authorization rule according to the existing project architecture.

---

# 123. Filament Shield Must Follow the Existing Permission Architecture

Before generating permissions for a new model:

```text
1. inspect existing Shield configuration
2. inspect permission naming
3. inspect role seeding/synchronization
4. inspect existing Policies
5. follow the same convention
```

Do not introduce a second permission naming system.

Do not hard-code role names inside domain services when permission checks are more appropriate.

Filament navigation visibility, resource access, table actions, bulk actions, page actions, and relation-manager actions must all respect the corresponding policy/Shield permissions.

Sensitive actions should be hidden **and** authorized server-side.

UI hiding alone is not authorization.

## Shield Generation, Custom Permissions, and Release Verification

Filament Shield is the source of truth for the **standard permissions of every top-level Filament Resource**. A phase that introduces or changes a class extending `Filament\Resources\Resource` must:

1. Run `php artisan shield:generate --all` in the target environment.
2. Inspect the generated `permissions` records.
3. Make every standard Policy check use the exact generated name — never a guessed name.
4. Assign the generated permissions in the existing Roles page and verify both an allowed and denied user.

Permission convention remains mandatory:

```text
Top-level Resource → Shield-generated project convention
                     e.g. Project = view_project
                          PropertyAcquisition = view_property::acquisition

Relation manager / workflow-only model → custom underscore convention
                                         e.g. attach_project_property
                                              approve_planned_unit
```

Never infer a multi-word Shield permission from a two-word example. Shield
tokenizes every word after the action. For example, the generated permissions
for `ProjectDesignPackage` are `view_project::design::package` and
`create_project::design::package` — not `view_project::design_package`.
Run the generator, inspect the exact records, and copy those strings into the
Policy and tests before releasing.

Shield does not create custom relation-manager or workflow permissions. Each must be declared with its exact underscore name in `RolesAndPermissionsSeeder`, used unchanged by the Policy and service, and created with:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder --force
```

### Required Phase Release Checklist

Every implementation report and handoff must state exactly which production commands are required:

```bash
php artisan migrate --force
php artisan shield:generate --all              # only when a top-level Resource changed
php artisan db:seed --class=RolesAndPermissionsSeeder --force  # when custom permissions changed
php artisan optimize:clear
php artisan filament:cache-components
```

`migrate --force` and idempotent permission seeders may remain in a CI/CD release step. Shield generation is required only for releases that add or change top-level resources. Do not put one-off permission commands in a Dockerfile permanently; if a temporary Docker release command is used, verify production permissions and then remove that one-off command.

---

# 124. Important Business Operations Should Produce Notifications

The system should provide notifications after meaningful business events.

Notifications are required for **important operations**, not every trivial CRUD update.

Good notification candidates include:

```text
approval / rejection
assignment
submission
revision request
cancellation
reservation conversion
contract activation/cancellation
payment recording
overdue financial obligation
budget approval
budget overrun
construction inspection result
critical construction issue
handover scheduling/completion
ownership change
important document approval
subscription payment/result
```

Avoid notification noise for operations such as:

```text
editing a description
changing a non-critical note
opening a record
minor formatting changes
ordinary internal CRUD with no business consequence
```

Each phase specification must define its own **Notification Matrix** with:

```text
trigger
recipient(s)
channel
timing
purpose
deduplication considerations
```

---

# 125. Notification Architecture Rules

Notifications should be triggered from domain events/actions, not duplicated independently in every UI.

Preferred flow:

```text
Business Action
   ↓
Database Transaction
   ↓
Commit Successfully
   ↓
Domain Event / Notification Dispatcher
   ↓
Database/In-App Notification
   ↓
Optional queued external channels
```

Important rules:

```text
- Do not notify before a transaction commits.
- Do not send duplicate notifications from both Filament and service layer.
- Keep recipients tenant-scoped.
- Queue slow external channels where appropriate.
- Persist important in-app notifications when useful.
- Use Filament visual toasts for immediate UX feedback without confusing them with persistent notification records.
- Make repeatable jobs/events idempotent where duplicate sends are possible.
```

External channels such as:

```text
email
WhatsApp
SMS
```

should remain configurable and must not be required for core transaction correctness.

---

# 126. Notification Recipients Must Be Domain-Based

Do not notify every company user after every important event.

Recipients should be derived from business responsibility, for example:

```text
project manager
assigned project member
finance role
sales role
record creator
assigned reviewer
engineering office contact
company administrators
specific permission holders
```

Where no exact responsible user exists yet, notify an appropriate permission-based group rather than all users.

Notification recipient selection must respect company isolation.

---

# 127. New Model Definition of Done

A newly introduced Eloquent model is not complete until the implementation has considered all of the following:

```text
database migration
correct nullability
foreign keys
indexes
casts
Eloquent relationships
inverse relationships where useful
existing tenant Global Scope / Trait
Policy
Filament Shield permissions
Filament integration where applicable
API serialization where applicable
business-state rules
notifications for important operations
factories/tests where useful
N+1 / eager-loading implications
audit/activity implications
```

This checklist applies to every phase file.

---

# 128. Important Workflow Actions Need Explicit Permissions

For models with meaningful lifecycle transitions, authorization should distinguish between editing data and performing business actions.

Examples:

```text
Project:
update != approve_project

DesignPackage:
update != submit_design_package
update != approve_design_package

Budget:
update != approve_budget

Contract:
update != cancel_contract

Handover:
update != complete_handover
```

The exact permission names should follow the existing Filament Shield/project naming convention.

---

# 129. Policy + Domain State

Policies may incorporate domain state restrictions.

Examples:

```text
approved budget may not be structurally edited
completed handover may not be casually deleted
approved design submission may be immutable
cancelled contract may not be reactivated
```

Authorization is therefore:

```text
user capability
+
tenant ownership
+
record state
```

not merely role membership.

---

# 130. Notification + Audit Are Different Concerns

A notification tells someone that an event happened.

An audit/activity record preserves that the event happened.

Important operations may require both.

Do not treat notifications as historical audit records, and do not treat audit logs as user-facing notifications.

# 131. Automated Testing Is a Phase Completion Gate

No implementation phase is complete merely because the application boots or the Filament screens appear to work.

Every phase must include automated tests that cover the business behavior introduced or changed by that phase.

Use the correct test level:

```text
Unit Tests
→ isolated calculations / pure business logic / small services

Feature / Integration Tests
→ database workflows
→ Eloquent relationships
→ tenant isolation
→ policies / authorization
→ validation
→ state transitions
→ notifications
→ transactions
→ Filament / Livewire critical actions when practical
```

Do not force all tests into the `Unit` directory when the behavior requires the Laravel container or database.

---

# 132. Test Both Success and Failure Paths

For every important business rule, consider:

```text
happy path
invalid input
unauthorized user
wrong tenant/company
invalid state transition
missing dependency
edge case
transaction failure
duplicate/repeated action
```

A phase with only happy-path tests is not considered sufficiently protected.

---

# 133. Regression Test Before Fixing a Discovered Bug

When implementation reveals a reproducible bug in behavior touched by the current phase:

```text
1. create a regression test that reproduces the bug
2. confirm the test fails for the expected reason
3. fix the bug
4. confirm the regression test passes
5. run related tests again
```

Do not create meaningless tests that merely mirror implementation internals.

---

# 134. Never Weaken a Valid Test to Make the Suite Green

A coding agent must not:

```text
delete a valid test
skip a valid test
replace a meaningful assertion with a weaker assertion
change expected business behavior
```

merely to make the suite pass.

If an existing test conflicts with the newly approved domain specification:

```text
inspect the reason
update the implementation and migration carefully
change the test only when the approved business behavior genuinely changed
document that change in IMPLEMENTATION-REPORT.md
```

---

# 135. Test Execution Before Phase Completion

For each phase:

```text
1. run the most relevant targeted tests during implementation
2. fix failures caused by the phase
3. run the full Laravel test suite:
   php artisan test
4. distinguish pre-existing unrelated failures from failures introduced by the phase
5. document the final result
```

If the full suite contains a known pre-existing unrelated failure, do not hide it.

Report it clearly.

The current phase must not introduce new unresolved failures.

---

# 136. Manual Review Gate After Automated Tests

The coding agent does not automatically continue into the next phase.

After:

```text
implementation
+
automated tests
+
IMPLEMENTATION-REPORT.md
```

the agent stops.

The developer then performs:

```text
manual Filament testing
manual UX review
code review
optional database inspection
```

Only after that review should the project move to the next phase.

Do not automatically start the next phase.

Do not automatically create a Git commit unless explicitly requested by the developer.

---

# Relation Manager Error Handling & User Feedback Convention

## Problem

When a relation manager's `CreateAction`, `EditAction`, or custom `Action` delegates to a service method that may throw `ValidationException` or `AuthorizationException`, Filament does not automatically surface these exceptions as user-friendly notifications. This causes operations to appear to fail silently — the user clicks a button, nothing happens, and there is no feedback explaining why.

## Mandatory Pattern

Every relation manager action that calls a service method (or any method that can throw) **must** wrap the call in a `try/catch` block and convert the exception into a `Filament\Notifications\Notification`:

```php
Tables\Actions\CreateAction::make()
    ->using(function (array $data) {
        try {
            return app(SomeService::class)->someMethod(
                auth()->user(),
                $this->getOwnerRecord(),
                // ... other args
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Cannot Perform Action')
                ->body(collect($e->errors())->flatten()->first())
                ->send();

            return null;
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Unauthorized')
                ->body('You do not have permission for this action.')
                ->send();

            return null;
        }
    }),
```

This rule also applies to custom workflow actions (e.g., "Clear", "Waive", "Approve"). On success, send a `->success()` notification so the user knows the action completed.

## Parent Workflow Reassessment Rule

Any create, update, replacement, or removal of a child record that is a prerequisite for a parent workflow must reassess the parent state in the **service/domain layer**. Do not leave a parent marked as approved merely because it was valid before new evidence or a new requirement was added.

For each such operation, the phase specification and implementation must explicitly define all of the following:

1. Whether the operation is allowed for every parent status.
2. Which statuses are terminal and must reject the operation server-side.
3. Whether a reversible approval must be invalidated and which earlier state the parent returns to.
4. Which approval/completion metadata must be cleared when the parent is reopened.
5. The exact authorization, transaction boundary, user feedback, and regression tests.

Example: a new Due Diligence Case added after an Acquisition is `approved` reopens it to `under_due_diligence` and clears `approved_by` / `approved_at`; a `completed` or `cancelled` Acquisition rejects a new Case. The same reasoning applies to later phases for changed budgets, new compliance findings, contract amendments, or replacement ownership records.

The UI may hide an invalid action, but the service must enforce the rule because requests can bypass the UI.

## Percentage / Total Validation Rule

For any field where the total across related records must not exceed a business limit (e.g., `share_percentage` must not exceed 100%), the validation **must be scoped by the appropriate grouping key** — never summed globally across all records.

### AcquisitionParty.share_percentage — Per-Role Scoping

An acquisition can have multiple parties with different roles (seller, buyer, broker, etc.). Each role group independently totals to 100%. Example of a **valid** scenario:

| Party        | Role   | Share % |
|-------------|--------|---------|
| Ahmed       | seller | 100%    |
| Our Company | buyer  | 60%     |
| Company B   | buyer  | 40%     |

- Seller total = 100% ✅
- Buyer total = 60% + 40% = 100% ✅

The validation must:

1. **Query only records with the same `role`** when summing existing percentages.
2. **Show a role-specific error message**, e.g.: "Total 'buyer' share would be 115%, exceeding 100%. Currently allocated for 'buyer': 80%. Maximum you can assign: 20%."
3. **Show role-specific helper text** below the field with current allocation for the selected role.
4. **Make the role field `->live()`** so the helper text updates reactively when the user changes the role.

For any percentage field with a 100% cap, always identify the correct grouping key (role, category, type, etc.) and scope the sum query accordingly. Never assume a flat global sum is correct.

---

## Dual-Database Testing Requirement (MySQL & PostgreSQL)

To ensure the application behaves identically across diverse environments (e.g., local MySQL development vs. Render/Production PostgreSQL deployment), **every phase or critical feature must be tested against both MySQL and PostgreSQL**. 

1. **Why It Matters**: PostgreSQL handles data types, unique constraints, and strict typing differently than MySQL. Migrations or queries that succeed silently in MySQL might throw exceptions in PostgreSQL (e.g., boolean/integer conversions, implicit casting, grouping).
2. **Execution**: Always run the PHPUnit test suite using both testing configurations before declaring a phase complete:
   ```bash
   php artisan test
   php artisan test --configuration phpunit.pgsql.xml
   ```

---

## Filament Relation Manager Create Actions & Authorization

For complex `belongsToMany` or polymorphic relationships where Filament's standard `CreateAction` or `AttachAction` buttons inexplicably fail to render:

1. **The Cause**: Filament implicitly checks for `attachAny` / `create` permissions on the pivot policy, which are often undefined or incorrectly inferred.
2. **The Solution**: Bypass the implicit checks by using a custom `Action::make()` instead of `CreateAction::make()`.
3. **Implementation**:
    ```php
    Tables\Actions\Action::make('createRecord')
        ->label('Create')
        ->visible(fn (): bool => auth()->user()->can('create', TargetModel::class))
        ->form([ /* ... duplicate or call schema ... */ ])
        ->action(function (array $data) {
            // Manually create and attach the record
        })
    ```
This guarantees the "Create" button displays based strictly on the explicitly provided `visible()` logic rather than hidden underlying policy requirements.

---

## Filament UI/UX Action Guidelines

To prevent silent failures, confusing states, or inaccessible nested data, follow these strict rules when adding actions to Pages or Relation Managers:

1. **Explicit Notifications for State Changes**: Any custom action that alters the database (e.g., transitions, cancellations, approvals) **must** end with a visible notification to inform the user the action succeeded.
    ```php
    ->action(function () {
        app(WorkflowService::class)->execute($this->record);
        \Filament\Notifications\Notification::make()->success()->title('Action Successful')->send();
    })
    ```
2. **Conditional Visibility**: If an action is no longer valid (e.g., you cannot "Cancel" a record that is already "Cancelled" or "Completed"), use `->visible()` to hide the button so the user isn't misled.
    ```php
    ->visible(fn () => !in_array($this->record->status, ['cancelled', 'completed']))
    ```
3. **View Actions for Repeaters**: If a Relation Manager table contains complex items (like a `Repeater` in the form) but the table only shows an `items_count`, you **must** include a `Tables\Actions\ViewAction::make()` in the table actions so the user can actually inspect the nested data without needing to explicitly edit it.

4. **Feedback for Every Mutating Operation**: Every user-initiated create, edit, attach, detach, replacement, delete, or workflow action must give an immediate, understandable result in the same screen:
   - success: a `success()` toast that says what changed and, for state changes, the resulting status;
   - validation/domain rejection: a `danger()` toast with the first actionable reason;
   - authorization rejection: a `danger()` toast saying the action is not permitted;
   - unexpected failure: preserve Laravel's error handling/logging and do not display a false success state.

   Wrap custom/service-backed actions in `try/catch` as defined above. Standard Filament CRUD actions may use Filament's built-in success notifications, but they must be configured when the default message would be unclear. Refresh only the affected Livewire component after success; do not use a full-page reload to communicate completion.

5. **Persistent Notifications Are Deliberate**: Immediate toast feedback is required for every mutation, but a stored/in-app business notification is only required for material events (approval, rejection, reopening, completion, cancellation, ownership replacement, etc.). Dispatch those only after the database transaction commits and only to the intended same-company recipients; do not create notification noise for ordinary edits.

---

# Super Admin & Global Scope Bypass in Relation Managers

## Problem

The `HasCompany` trait applies a `CompanyScope` global scope that filters records by `company_id` for non-super-admin users. For super admins, this scope is **not applied**, which means:

1. **Select dropdowns** using `->relationship('relation', 'name')` will show records from **all companies**, not just the parent record's company.
2. **`Model::findOrFail($id)`** will find records from any company, bypassing company isolation.
3. **`HasCompany::creating()`** skips auto-injection of `company_id` for super admins, so `company_id` must always be set explicitly.

## Mandatory Pattern for Relation Manager Selects

When a relation manager form contains a `Select` for a related model that uses the `HasCompany` trait, **never** use `->relationship()`. Instead, manually query with `withoutGlobalScopes()` filtered by the parent record's `company_id`:

```php
Forms\Components\Select::make('party_id')
    ->label('Party')
    ->options(function () {
        $companyId = $this->getOwnerRecord()->company_id;

        return Party::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->pluck('name', 'id');
    })
    ->searchable()
    ->preload()
    ->required(),
```

## Mandatory Pattern for Record Lookups in Actions

When looking up a record by ID inside a `->using()` callback, use `withoutGlobalScopes()` so the lookup works for super admins:

```php
$party = Party::withoutGlobalScopes()->findOrFail($data['party_id']);
```

The service layer's `sameCompany()` check will then validate that the selected record belongs to the correct company, providing proper error feedback if not.

## Checklist

- [ ] All relation manager `Select` fields for company-scoped models use `withoutGlobalScopes()->where('company_id', ...)` instead of `->relationship()`
- [ ] All `findOrFail()` calls in relation manager actions use `withoutGlobalScopes()`
- [ ] `company_id` is explicitly set in every create action (via service layer or `mutateFormDataUsing`)
- [ ] Service layer exceptions are caught and converted to Filament Notifications

---

## Controlled Planning Type Catalogues and Icon Accessibility

For reusable business categories such as Project Building Type and Planned Unit Type:

1. Store a stable string key in the database (for example, `residential_tower` or `apartment`), not a database-specific enum.
2. Define the approved key/label catalogue once on the domain model and reuse it in Filament forms and service validation.
3. Use a searchable Filament `Select` so staff choose a consistent reporting value instead of creating spelling variants in a free-text input.
4. Validate the submitted key in the service layer; a forged request must not store an unsupported type.
5. Add new catalogue entries deliberately in a future release. Do not rename or remove a stored key without an explicit data-migration plan.

For compact Filament action buttons and action groups that rely on icons, always add a concise `->tooltip()` naming the action group or purpose. Icons improve scanning, but the tooltip is required so unfamiliar users and keyboard/mouse users can understand the action before opening it.

When running `shield:generate` in CI/CD, Docker, or any non-interactive environment, always supply the explicit Filament panel ID (currently `--panel=admin`) and `--no-interaction`. Without a panel ID Shield opens an interactive selector and deployment fails with `Required.`.

---

## Record Policy Tenant-Isolation Audit

Global query scopes protect ordinary lists, but they are **not authorization**. A forged URL, a nested action payload, a queued job, or an explicit `withoutGlobalScopes()` lookup can still provide a cross-company record to a policy.

For every company-owned model policy:

1. Every record-based ability (`view`, `update`, `delete`, `approve`, `cancel`, `complete`, `attach`, `detach`, and similar) must verify both the exact permission and record-company access through `CompanyOwnedPolicy::canForRecord()` or an equivalent centralized check.
2. Class-level abilities (`viewAny`, `create`) still require their exact Shield/custom permission; company inheritance and validation belongs in the service/create path.
3. A platform Super Admin may cross company boundaries only through the existing privileged path, while still requiring the relevant permission unless an explicit global authorization rule has been approved.
4. Each phase must include a role-based regression matrix that verifies: same-company record allowed, another-company record denied, workflow permission denied without its exact permission, and all custom permissions are present in the Roles UI after the idempotent seeder runs.

Never treat a correctly filtered Filament table as proof that direct record authorization is secure.

---

## Company-Scoped Roles (Without Spatie Teams)

Permissions are platform-defined and global. Roles belong either to the platform (`roles.company_id = null`) or exactly one Company (`roles.company_id = company.id`). The database uniqueness rule is therefore `(company_id, name, guard_name)`, allowing different companies to independently use the same role name.

When a release introduces new platform-defined permissions, the deployment
must seed those permissions and run `app:backfill-company-roles`. The command
adds newly introduced non-platform permissions to the three built-in,
company-owned default roles (`company_admin`, `property_manager`, and
`financial_manager`) without removing existing grants or modifying any
company-created custom role. This prevents a Super Admin's platform template
from receiving a new permission while the corresponding existing Company role
silently remains stale.

1. Do **not** enable Spatie Teams for this application while users have one active `company_id`. Teams would require active-team context on every role and permission lookup and would make platform roles needlessly ambiguous.
2. Use `App\\Models\\Role` and `CompanyRoleService` for every role lookup or assignment. Never resolve a company role by name alone and never call `assignRole('name')` for a company user.
3. New companies receive company-owned copies of the approved default roles. Existing companies can be migrated deliberately with `php artisan app:backfill-company-roles` after deployment; use `--dry-run` first.
4. A company user can list, edit, delete, assign, and grant permissions only to roles whose `company_id` equals the user's current company. This must be enforced in the policy/service, not only by a Filament query.
5. A Company role must never receive platform-administration permissions (Company, subscription, plan, platform-role, or Super Admin operations). Validate this on the server when permissions are synced.
6. Platform roles remain Super Admin-only to manage and assign. Super Admin may see all roles and permissions. The Role page must make scope visible to Super Admin and keep other companies' roles invisible to company users.
7. Company-role permission forms must hide and server-side reject all platform resources: Company, User, Plan, Subscription, and platform Role administration. Company Setting is explicitly tenant-owned and remains available. When adding a future platform-only Resource, add it to the centralized platform-resource list and its permission guard in the same change.
