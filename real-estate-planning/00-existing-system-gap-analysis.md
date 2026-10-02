# 00 — Existing System Gap Analysis

> **Project:** Existing Real Estate SaaS  
> **Purpose:** Map the current implementation to the approved target domain before phase-by-phase implementation.
>
> This document is a **working implementation bridge**, not a substitute for repository inspection.
>
> It is based on the currently known state of the project and must be verified against the actual codebase before migrations are written.

---

# 1. Why This File Exists

The application already contains an existing schema, models, Filament resources, frontend code, business logic, permissions, tenant scoping, and working modules.

The target domain has now been redesigned and approved at a much deeper level.

Therefore implementation must not start by blindly creating all target tables.

The correct process is:

```text
CURRENT PROJECT
     ↓
Inspect Existing Schema + Code
     ↓
Compare With Approved Domain
     ↓
Identify Gap
     ↓
REUSE / CREATE / ALTER / RESTRUCTURE / SPLIT / DROP / MIGRATE_DATA
     ↓
Implement Safely
     ↓
Verify Data + Relationships + Performance
```

This file documents the expected gaps so the coding agent knows what to look for.

---

# 2. Important Limitation

This file contains two kinds of information:

## Confirmed / Known
Items already known from the current project and prior analysis.

## Must Verify in Repository
Items that must be confirmed from:

```text
database/migrations
app/Models
app/Traits
app/Scopes
Filament resources/pages/relation managers
Livewire components
API controllers/resources
services/actions
frontend consumers
tests
```

The coding agent must not treat an unverified assumption as fact.

---

# 3. Gap Analysis Actions

Use these exact classifications.

| Action | Meaning |
|---|---|
| `REUSE` | Existing structure already satisfies target domain. |
| `CREATE` | Target concept is missing. |
| `ALTER` | Existing structure is correct in concept but incomplete. |
| `ADD_RELATIONSHIP` | Existing entities exist but target relationship is missing. |
| `RENAME` | Existing concept is correct but naming is misleading. |
| `RESTRUCTURE` | Existing design conflicts with target business model. |
| `SPLIT` | One current concept mixes multiple approved concepts. |
| `MERGE` | Duplicate existing structures represent one concept. |
| `DROP` | Legacy structure is obsolete after safe migration. |
| `MIGRATE_DATA` | Existing records must be transformed into target structure. |
| `VERIFY` | Actual repository inspection is required before deciding. |

One item may require multiple actions.

---

# 4. Current Architectural Strengths to Preserve

The current application already contains useful architecture that must not be unnecessarily replaced.

Known strengths:

```text
Laravel backend
Filament administration
Livewire integration
Next.js frontend
existing company/tenant scoping
existing reusable Global Scope / Trait
roles and permissions
existing property/unit domain
existing unit features
existing contract domain
existing payment implementation
existing notifications
search/reporting/PDF capabilities
AI integrations
```

The target implementation should evolve these structures rather than creating a second architecture beside them.

---

# 5. Existing Global Scope / Tenant Trait

## Current State

Known:

- The project already uses a reusable Global Scope / Trait across models.
- It is responsible for company/tenant scoping.
- The user considers this mechanism valid and does not want it replaced.

## Target State

All company-scoped domain models should consistently use the same existing mechanism.

## Action

```text
REUSE
```

## Required Verification

Inspect:

```text
app/Traits/*
app/Scopes/*
model boot methods
company_id assignment behavior
creation hooks
query scope behavior
Filament interaction with the scope
background-job behavior
```

## Implementation Rule

Do not introduce:

```text
another tenancy package
another tenant trait
manual company_id filtering everywhere
parallel tenant middleware
```

New models that are tenant-owned must integrate with the existing mechanism.

---

# 6. Users / Companies / Agency Foundation

## Current State

Known:

```text
users
companies / agency-like company structure
authentication
permissions / roles
company scoping
```

## Target State

These remain foundational entities.

## Expected Action

```text
REUSE
+
ALTER only if later domain phases require additional fields or relations
```

## Must Verify

- exact company table name,
- whether user belongs to one or multiple companies,
- current FK strategy,
- user/company pivot if any,
- role/permission implementation,
- existing indexes,
- company-scoped model trait usage.

## Avoid

Do not create a second:

```text
organizations
tenants
agencies_v2
company_users_v2
```

unless repository inspection proves the current model cannot represent the target.

---

# 7. Roles & Permissions

## Current State

Known:

- Roles and permissions exist.
- Spatie-style permissions have been used in the project.

## Target State

Permissions must cover newly introduced modules and actions.

Examples:

```text
properties
acquisitions
projects
design packages
budgets
construction
units
CRM
contracts
payments
handover
subscriptions
reports
```

## Expected Action

```text
REUSE
+
ALTER / ADD permission definitions
```

## Must Verify

- current permission naming pattern,
- policy usage,
- Filament authorization rules,
- tenant interaction,
- role seeding,
- API authorization.

---

# 8. Parties

## Current State

Unknown whether a generic `parties` table exists.

The current project may currently represent:

```text
customers
owners
tenants
suppliers
partners
```

through separate structures.

## Target State

Reusable actor concept:

```text
parties
```

used contextually through relationships such as:

```text
contract_parties
acquisition_parties
design_package_assignments
project_partners
```

## Expected Action

```text
VERIFY
```

Possible result:

```text
CREATE
```

if no generic party model exists.

Or:

```text
RESTRUCTURE / MIGRATE_DATA
```

if multiple overlapping actor tables currently exist.

## Critical Rule

Do not migrate actors blindly until duplicate identity risk is understood.

---

# 9. Properties

## Current State

Known:

- `properties` already exists.
- It is a core part of the current product.

## Target State

Property represents the underlying real-estate asset/land.

Property is separate from:

```text
acquisition
ownership history
project
listing
unit
```

## Expected Action

```text
REUSE
+
ALTER if target fields/relationships are missing
```

## Must Verify

Current fields and whether `properties` currently mixes concepts such as:

```text
purchase information
owner information
project information
listing information
sale information
```

If so, those responsibilities should be separated gradually.

## Expected New Relationships

Potentially:

```text
property_ownerships
acquisition_properties
contract_properties
project_properties
documents
```

## Do Not

Do not create `properties_v2`.

---

# 10. Property Ownership History

## Current State

Unknown whether full ownership history exists.

Current property may only have a current owner reference.

## Target State

Dedicated history:

```text
property_ownerships
```

supporting owner changes over time.

## Expected Action

```text
VERIFY
→ CREATE or ALTER
```

## Migration Concern

If `properties` currently stores only one owner:

```text
current owner
```

that value may need to seed an initial historical ownership row.

Do not remove current owner fields until all dependent code is migrated.

---

# 11. Property Acquisitions

## Current State

Likely missing as a dedicated domain.

## Target State

```text
property_acquisitions
acquisition_properties
acquisition_parties
```

Supporting:

```text
cash purchase
installment purchase
partnership
land-for-units
profit sharing
owned_existing
```

## Expected Action

```text
CREATE
```

## Related Existing Structures to Inspect

```text
properties
contracts
owners
payments
documents
```

## Important Migration Rule

Do not infer an acquisition type for existing properties without evidence.

Existing already-owned land may use:

```text
owned_existing
```

when appropriate.

---

# 12. Due Diligence

## Current State

Likely missing.

## Target State

```text
due_diligence_cases
due_diligence_items
```

## Expected Action

```text
CREATE
```

## Must Verify

Whether any existing checklist/inspection/document module currently overlaps.

---

# 13. Generic Contracts

## Current State

Known:

- `contracts` exists.
- Existing contract logic currently supports at least rental/sale-like workflows.
- Current implementation is not yet guaranteed to represent all target contract cases.

## Target State

One generic contract domain supporting relevant agreement types such as:

```text
sale
rent
acquisition-related agreements
partnership-related agreements where applicable
```

with related structures such as:

```text
contract_parties
contract_properties
unit relationship where applicable
contract_terms
documents
payment schedules
cancellation fields
```

## Expected Action

```text
REUSE
+
ALTER
+
ADD_RELATIONSHIP
+
possibly RESTRUCTURE
```

## Fields/Concepts Likely Needed

```text
type
status
cancelled_at nullable
cancellation_reason nullable
```

Exact schema belongs to the contracts phase specification.

## Must Verify

- current fields,
- whether contract directly stores tenant/customer,
- whether it directly stores unit,
- current type/status values,
- current payment-generation logic,
- existing PDFs/documents,
- current cancellation logic,
- existing relations.

## Do Not

Do not create:

```text
sales_contracts
rental_contracts
contracts_v2
```

unless later repository inspection proves the existing contract table is fundamentally unusable.

---

# 14. Contract Parties

## Current State

Unknown.

Current contract may store party/customer IDs directly.

## Target State

```text
contract_parties
```

supports multiple parties and contextual roles.

## Expected Action

```text
VERIFY
→ CREATE / MIGRATE_DATA
```

## Migration Example

Current:

```text
contracts.customer_id
contracts.tenant_id
contracts.owner_id
```

Possible target:

```text
contract_parties
- contract_id
- party_id
- role
```

Legacy columns should remain temporarily until dependent code is migrated.

---

# 15. Contract Properties / Unit Relationship

## Current State

Must verify.

The current contract likely links to a unit.

## Target State

Contracts may relate to:

```text
properties
units
```

depending on contract type.

## Expected Action

```text
VERIFY
+
ADD_RELATIONSHIP / ALTER
```

## Important Rule

Do not force all contract types through the same asset relation if the business meaning differs.

---

# 16. Contract Terms

## Current State

Likely missing as a structured child table.

## Target State

```text
contract_terms
- id
- contract_id
- title
- content
- sort_order
```

## Expected Action

```text
CREATE
```

## Important Rule

Terms are textual clauses, not installment/payment rows.

---

# 17. Current Payments — Major Gap

## Current State

Confirmed latest understanding:

The existing system currently relies on a `payments` table that mixes payment obligations and actual paid money.

The old model effectively behaves like:

```text
payments
- amount       = amount due
- paid_amount  = amount actually paid
```

This mixes two separate business concepts.

## Target State

```text
Contract
   ↓
Payment Schedule
   ↓
Installments

Payment
   ↓
Payment Allocations
   ↓
Installments
```

Tables:

```text
payment_schedules
installments
payments
payment_allocations
```

## Required Action

```text
RESTRUCTURE
+
SPLIT
+
CREATE
+
ALTER
+
MIGRATE_DATA
+
DROP legacy fields only after verification
```

This is one of the most important structural changes in the entire project.

---

# 18. Payment Migration Strategy

The coding agent must inspect the current payment schema before writing migrations.

At minimum determine:

```text
what each current row represents
whether each row is a due installment
whether multiple actual payments can exist today
how paid_amount is updated
whether amount and paid_amount share currency
whether payment method/reference currently exists
whether due_date exists
whether contract frequency generates these rows
whether historical partial payments are recoverable
```

## Target Migration Possibility

If each old row represents one due obligation:

```text
old payments row
     ↓
new installment
```

If `paid_amount > 0`, the migration may also require:

```text
new payment
+
new payment_allocation
```

But this must only happen if the old data semantics prove that `paid_amount` represents actual received cash.

## Critical Reconciliation

Before and after migration verify:

```text
total amount due
total actual amount received
per-contract totals
orphan rows
currency consistency
```

No financial restructuring is complete without reconciliation.

---

# 19. Payment Schedule

## Current State

Likely absent as a dedicated entity.

Current frequency/start/end data may exist directly on contract.

## Target State

Dedicated:

```text
payment_schedules
```

## Expected Action

```text
CREATE
+
possibly MIGRATE_DATA from contract fields
```

## Must Verify

Whether current contracts contain:

```text
frequency
start_date
end_date
payment interval
generated payment count
```

If so, determine which fields still belong to contract and which belong to schedule.

---

# 20. Installments

## Current State

No dedicated approved installment table is assumed.

Latest confirmed description indicates payment obligations are currently represented in `payments`.

## Target State

```text
installments
```

represent amounts due.

## Expected Action

```text
CREATE
+
MIGRATE_DATA
```

## Status Model

Expected domain statuses:

```text
pending
partially_paid
paid
overdue
cancelled
```

Exact transitions belong to the payment phase specification.

---

# 21. Actual Payments

## Current State

Mixed into old payment rows through `paid_amount`.

## Target State

`payments` means only actual money received.

## Expected Action

Likely:

```text
REDEFINE / ALTER existing payments
```

or, if a safe staged migration requires it:

```text
temporary migration table
→ data migration
→ final payments semantics
```

## Critical Rule

The final table named `payments` must not continue meaning both due and paid.

---

# 22. Payment Allocations

## Current State

Missing.

## Target State

```text
payment_allocations
- payment_id
- installment_id
- amount
```

## Expected Action

```text
CREATE
```

## Purpose

Supports:

```text
partial payments
multiple payments for one installment
one payment covering several installments
```

---

# 23. Projects

## Current State

A project concept may already exist, but the target project-management domain is substantially broader.

## Target State

Core project:

```text
projects
```

with lifecycle and links to properties.

## Expected Action

```text
REUSE if existing
+
ALTER
```

## Likely Fields

```text
company_id
name
project_type
status
start_date
expected_completion_date
description
```

Exact fields must be verified and defined by phase specification.

---

# 24. Project Properties

## Current State

Unknown.

## Target State

Many-to-many:

```text
project_properties
```

## Expected Action

```text
VERIFY
→ CREATE / ADD_RELATIONSHIP
```

## Important Rule

Do not place acquisition type directly on `projects`.

---

# 25. Project Members

## Current State

May currently rely on a single project manager field or may not exist.

## Target State

```text
project_members
```

for project team membership.

## Expected Action

```text
VERIFY
→ CREATE / ALTER
```

## Rule

Do not rely solely on:

```text
projects.project_manager_id
```

for full project team representation.

---

# 26. Project Buildings

## Current State

Likely missing as a dedicated planning domain.

## Target State

```text
project_buildings
```

## Expected Action

```text
CREATE
```

---

# 27. Project Floors

## Current State

Existing unit floor fields may exist, but project planning floors are a separate planning concept.

## Target State

```text
project_building_floors
```

## Expected Action

```text
CREATE
```

## Important Rule

Do not confuse actual unit floor metadata with project planning structure.

---

# 28. Planned Units

## Current State

Likely missing.

## Target State

```text
project_planned_units
```

## Expected Action

```text
CREATE
```

## Rule

Planned Unit ≠ Actual Unit.

Sales should use actual units.

---

# 29. Planned Unit Specifications

## Current State

Likely missing.

## Target State

```text
planned_unit_specifications
```

## Expected Action

```text
CREATE
```

---

# 30. Design / Engineering Packages

## Current State

Likely missing as a formal workflow.

## Target State

```text
project_design_packages
design_package_assignments
design_package_scope_items
design_package_activities
design_package_submissions
design_package_submission_documents
design_package_reviews
design_review_findings
design_package_revisions
design_revision_findings
design_package_approvals
```

## Expected Action

```text
CREATE
```

for most or all of this domain.

## Existing Structures to Reuse Where Applicable

```text
documents
document storage
users
parties
notifications
audit/activity log
```

---

# 31. Design Assignment

## Current State

Likely absent.

## Target State

Assignment is historical and points to an external party/engineering office.

## Expected Action

```text
CREATE
```

## Rule

The project currently does not need individual engineer tracking inside the engineering office.

---

# 32. Documents

## Current State

The project already has document/PDF-related functionality, but exact generic document schema must be verified.

## Target State

Reusable:

```text
documents
document_versions
```

## Expected Action

```text
VERIFY
```

Possible outcome:

```text
REUSE
+
ALTER
```

or:

```text
CREATE generic layer
+
MIGRATE existing module-specific files
```

## Critical Rule

Do not create duplicate document systems per module.

---

# 33. Document Versions

## Current State

Must verify whether versioning exists.

## Target State

Formal version history is required for design documents and other historical files where relevant.

## Expected Action

```text
VERIFY
→ CREATE / ALTER
```

---

# 34. Budgeting

## Current State

A complete project budgeting domain is not currently assumed.

## Target State

```text
project_budgets
budget_categories
budget_items
financial commitments
```

## Expected Action

```text
CREATE
```

for most of this module.

## Existing Data to Inspect

```text
expenses
payments
project costs
reports
```

if such tables exist.

## Important Rule

Budget item ≠ actual expense/payment.

---

# 35. Construction

## Current State

Likely new major domain.

## Target State

```text
project_constructions
construction_work_packages
construction_tasks
construction_progress_updates
construction_inspections
construction_issues
construction_delays
```

## Expected Action

```text
CREATE
```

## Existing Structures to Reuse

Potentially:

```text
projects
users
parties/contractors
documents
notifications
audit log
```

---

# 36. Construction Progress

## Current State

Unknown.

## Target State

Explicit progress update history.

## Expected Action

```text
CREATE
```

## Rule

Progress percentage does not equal actual cost.

---

# 37. Construction Inspections

## Current State

Unknown.

## Target State

Dedicated inspections tied to construction workflow.

## Expected Action

```text
CREATE
```

---

# 38. Units

## Current State

Confirmed:

- `units` already exists.
- It is actively used in the existing system.

## Target State

Actual operational unit.

## Expected Action

```text
REUSE
+
ALTER
+
ADD_RELATIONSHIP
```

## Important Likely Change

```text
planned_unit_id nullable
```

so actual units may optionally originate from planned units.

## Must Verify

Existing fields such as:

```text
project_id
property_id
building
floor
unit number
type
area
status
price
owner
```

Fields belonging to other domains may need to move conceptually.

Example:

```text
marketing price
```

should belong to listing/sales rather than Unit Setup if currently embedded on unit.

---

# 39. Unit Features

## Current State

Confirmed:

- Unit → UnitFeature relation already exists.

## Target State

Continue using existing feature mechanism.

## Action

```text
REUSE
```

## Rule

Do not create another:

```text
planned_features
actual_unit_feature_values_v2
new_unit_features
```

unless the later specification identifies a genuinely different concept.

---

# 40. Unit Ownership History

## Current State

Must verify.

Current unit may store current owner directly.

## Target State

```text
unit_ownerships
```

with ownership percentage and history.

## Expected Action

```text
VERIFY
→ CREATE / MIGRATE_DATA
```

## Important Rule

Current ownership and unit status are independent.

---

# 41. Unit Status History

## Current State

Likely unit has only current status.

## Target State

```text
unit_status_histories
```

## Expected Action

```text
CREATE
```

unless equivalent history already exists.

---

# 42. Listings

## Current State

Property/unit public listing features exist in some form, but exact table must be verified.

## Target State

```text
unit_listings
```

or target equivalent defined by sales specification.

## Expected Action

```text
VERIFY
```

Possible outcomes:

```text
REUSE
ALTER
RESTRUCTURE
```

## Target Separation

Listing is separate from unit.

Listing owns commercial presentation such as:

```text
listing type
sale/rent price
description
publish status
available_from
published_at
expires_at
```

---

# 43. Leads

## Current State

CRM/pipeline is known to be planned rather than fully established.

## Target State

```text
leads
```

## Expected Action

```text
CREATE
```

unless repository inspection finds a suitable existing customer/request entity.

---

# 44. Lead Interests

## Current State

Likely missing.

## Target State

```text
lead_interests
```

## Expected Action

```text
CREATE
```

## Rule

One lead may be interested in multiple listings.

---

# 45. Inquiries

## Current State

There may already be rental/property request forms.

## Target State

Formal inquiry concept related to:

```text
lead
listing
type
status
subject/message
```

## Expected Action

```text
VERIFY
```

Possible:

```text
RESTRUCTURE existing rental request
+
MIGRATE_DATA
```

rather than creating a duplicate request concept.

---

# 46. Lead Activities / Follow-Up

## Current State

Likely missing or basic notes only.

## Target State

```text
lead_activities
```

## Expected Action

```text
CREATE
```

---

# 47. Sales Offers

## Current State

Likely missing.

## Target State

```text
sales_offers
```

with optional:

```text
parent_offer_id
```

for negotiation chain.

## Expected Action

```text
CREATE
```

---

# 48. Reservations

## Current State

Must verify whether reservation logic already exists.

## Target State

```text
reservations
```

with status lifecycle:

```text
pending
active
expired
cancelled
converted
```

## Expected Action

```text
VERIFY
→ CREATE / ALTER / RESTRUCTURE
```

## Rule

Reservation ≠ contract.

Reservation amount ≠ actual payment.

---

# 49. Reservation Cancellation

## Current State

Must verify.

## Target State

Cancellation preserves history and may change unit availability according to explicit rule.

## Expected Action

```text
ALTER workflow
```

if reservations already exist.

---

# 50. Contract Cancellation

## Current State

Must verify current cancellation behavior.

## Target State

Simple irreversible cancellation:

```text
status = cancelled
cancelled_at
cancellation_reason
```

New agreement → new contract.

## Expected Action

```text
ALTER
```

## Rule

Do not delete contract or payments.

---

# 51. Default / Overdue

## Current State

Must verify whether overdue is manually stored or derived.

## Target State

Overdue installment does not automatically cancel contract.

## Expected Action

```text
ALTER business logic
```

when payment redesign is implemented.

---

# 52. Handover

## Current State

Likely missing as a complete workflow.

## Target State

```text
handovers
handover_inspections
handover_items
optional handover_checklist_items
```

## Expected Action

```text
CREATE
```

## Existing Structures to Reuse

```text
units
contracts
documents
users
notifications
audit
```

---

# 53. Handover Eligibility

## Current State

Likely not formalized.

## Target State

Eligibility checks may depend on:

```text
unit readiness
active contract
required payments
required documents
```

## Expected Action

```text
CREATE business action/service
```

Exact policy belongs to handover phase.

---

# 54. Project Closing

## Current State

Likely represented only by project status or not implemented.

## Target State

Dedicated final lifecycle phase.

## Expected Action

```text
CREATE / ALTER
```

depending on final approved specification.

## Do Not Implement Yet

Detailed closing logic must wait for `10-project-closing.md`.

---

# 55. Notifications

## Current State

Known:

- Notifications already exist.
- Previous implementation included Filament/Livewire notification interactions.

## Target State

Notifications become side effects of domain events across modules.

## Expected Action

```text
REUSE
+
ALTER / EXTEND
```

## Important Existing Concern

Do not create duplicate notification persistence/toast records that conflict with each other.

Use stable separation between:

```text
persistent database notification
visual Filament toast
```

where needed.

---

# 56. Search

## Current State

Search functionality exists.

## Target State

Extend search to new domains without introducing inefficient cross-table logic.

## Expected Action

```text
REUSE
+
EXTEND
```

## Performance Rule

Use indexes and domain-specific search strategies.

Do not fetch full datasets into PHP for filtering.

---

# 57. Reports

## Current State

Reports exist.

## Target State

Reports must incorporate:

```text
projects
budget
construction
CRM
contracts
installments
payments
handover
```

## Expected Action

```text
REUSE infrastructure
+
EXTEND queries
```

## Rule

Reports must use the new canonical financial sources after payment migration.

---

# 58. PDF / Export

## Current State

Existing PDF/report export capability exists.

## Target State

Reuse export infrastructure for:

```text
contracts
handover protocols
reports
financial documents
```

## Expected Action

```text
REUSE
+
EXTEND
```

---

# 59. Audit / Activity Log

## Current State

Must verify exact infrastructure.

## Target State

Important domain transitions should be auditable.

## Expected Action

```text
VERIFY
→ REUSE / EXTEND
```

Do not build a second audit system if an adequate one exists.

---

# 60. AI

## Current State

AI/chatbot and AI integration capabilities already exist.

## Target State

AI remains a cross-cutting integration.

## Expected Action

```text
REUSE
+
EXTEND only where needed
```

## Rule

AI must not become source of truth for:

```text
payments
ownership
contracts
critical statuses
```

---

# 61. Maintenance Module

## Current State

Maintenance functionality exists.

## Target State

Maintenance remains an operational domain but is not the central focus of the current phase redesign.

## Expected Action

```text
REUSE
```

unless future integration with:

```text
units
handover
projects
construction issues
```

requires new relationships.

## Important Rule

Do not accidentally merge:

```text
construction issue
handover punch-list item
property maintenance request
```

They may look similar but belong to different workflows.

---

# 62. SaaS Subscription System

## Current State

A trial/plans/subscription concept exists or has been partially implemented.

Stripe Checkout was previously tested, but it is not the intended long-term core provider for the target market.

## Target State

Separate SaaS billing domain:

```text
subscription_plans
subscriptions
subscription_invoices
subscription_payments
provider abstraction
```

## Expected Action

```text
VERIFY
+
RESTRUCTURE / ALTER
```

depending on existing tables.

## Rule

Do not mix SaaS subscription payments with real-estate customer payments.

---

# 63. Payment Provider Integration

## Current State

Stripe integration exists in some form.

## Target State

Provider-agnostic billing layer.

Potential providers:

```text
binance_pay
manual
bank_transfer
cash
future providers
```

## Expected Action

```text
REUSE useful integration abstractions
+
RESTRUCTURE provider coupling
```

## Important Rule

Core subscription schema must not be named around Stripe/Binance-specific concepts.

---

# 64. Filament Resources

## Current State

Filament is a major admin interface.

## Target State

All new domains should integrate with Filament using:

```text
proper Eloquent relationships
existing tenant scope
reactive actions
relation managers
efficient tables
shared business services/actions
```

## Expected Action

```text
REUSE architecture
+
CREATE resources for new modules
+
ALTER existing resources where schemas change
```

---

# 65. Filament Full-Page Reloads

## Current State

Must verify existing patterns.

## Target State

Normal CRUD/workflow operations should update reactively without full page reload.

## Expected Action

```text
ALTER UX implementation where needed
```

Avoid:

```text
window.location.reload()
location.reload()
unnecessary redirect-back
```

Use Livewire/Filament state updates.

---

# 66. Model Relationships

## Current State

Existing relationships must be audited.

## Target State

Every relevant model should expose proper relationships.

## Expected Action

```text
VERIFY
+
ALTER
```

## Review Examples

```text
Project → Buildings
Building → Floors
Floor → Planned Units
Unit → Ownerships
Unit → Listings
Lead → Interests
Contract → Parties
Contract → Payment Schedules
Payment Schedule → Installments
Payment → Allocations
Installment → Allocations
Handover → Inspections
```

---

# 67. Eager Loading / N+1

## Current State

Must be inspected in existing Filament/API code.

## Target State

Deliberate eager loading and efficient aggregate queries.

## Expected Action

```text
PERFORMANCE_AUDIT
+
ALTER queries where needed
```

Critical modules:

```text
Filament tables
dashboard widgets
unit/property lists
contracts
payments
CRM
project management
reports
```

---

# 68. Indexing

## Current State

Unknown completeness.

## Target State

Indexes based on real query patterns.

## Expected Action

```text
VERIFY
+
ALTER migrations
```

Likely candidates:

```text
company_id
project_id
property_id
unit_id
contract_id
listing_id
lead_id
status
due_date
created_at
```

Composite indexes should be justified by actual filtering patterns.

---

# 69. Status Enums / Constants

## Current State

Must verify whether statuses are strings, enums, or mixed.

## Target State

Centralized status definitions.

## Expected Action

```text
VERIFY
+
REFACTOR / ALTER
```

Avoid duplicated string literals throughout:

```text
Filament
controllers
services
frontend
```

---

# 70. Frontend API Compatibility

## Current State

Next.js frontend consumes current Laravel API.

## Target State

Backend restructuring must not silently break frontend behavior.

## Expected Action

```text
IMPACT_ANALYSIS
+
ALTER API
+
ALTER frontend consumers
```

Payment redesign is especially high risk.

## Strategy

Where required:

```text
temporary backward-compatible response
→ update frontend
→ remove legacy fields
```

---

# 71. Data Preservation

For every migration affecting existing business records, classify data as:

```text
safe automatic mapping
derived mapping
requires manual review
cannot be migrated reliably
```

Never invent missing historical facts.

---

# 72. High-Risk Migration Areas

Highest migration risk:

```text
1. payments
2. contracts
3. current ownership fields
4. listing price/location if mixed into units/properties
5. tenant relationships
6. existing documents
7. subscription billing
```

These require staged migration and verification.

---

# 73. Low-Risk / Mostly New Areas

Mostly additive domains:

```text
project buildings
project floors
planned units
planned specifications
design packages
design assignments
design scope
design submissions/reviews/revisions/approvals
project budgets
budget categories/items
construction packages/tasks/progress/inspections/issues/delays
lead activities
sales offers
handover workflow
```

These still require integration with existing models but are less likely to need destructive legacy migration.

---

# 74. Module-by-Module High-Level Classification

| Module | Current State | Target Action |
|---|---|---|
| Companies | Existing | REUSE / small ALTER |
| Users | Existing | REUSE |
| Global Scope/Trait | Existing | REUSE |
| Roles/Permissions | Existing | REUSE + EXTEND |
| Parties | Unknown | VERIFY / likely CREATE |
| Properties | Existing | REUSE + ALTER |
| Property Ownership | Unknown | VERIFY / CREATE |
| Acquisitions | Missing/unknown | CREATE |
| Acquisition Parties/Properties | Missing | CREATE |
| Due Diligence | Missing | CREATE |
| Contracts | Existing | REUSE + ALTER/RESTRUCTURE |
| Contract Parties | Unknown | VERIFY / CREATE |
| Contract Terms | Missing | CREATE |
| Payments | Existing but mixed semantics | RESTRUCTURE + SPLIT + MIGRATE_DATA |
| Payment Schedules | Missing | CREATE |
| Installments | Missing as approved concept | CREATE |
| Payment Allocations | Missing | CREATE |
| Projects | Existing/partial | REUSE + ALTER |
| Project Properties | Unknown | VERIFY / CREATE |
| Project Members | Unknown | VERIFY / CREATE |
| Buildings | New planning domain | CREATE |
| Floors | New planning domain | CREATE |
| Planned Units | New | CREATE |
| Planned Unit Specs | New | CREATE |
| Design/Engineering | New | CREATE |
| Documents | Existing/unknown structure | VERIFY / REUSE/ALTER |
| Document Versions | Unknown | VERIFY / CREATE |
| Budgeting | New | CREATE |
| Construction | New | CREATE |
| Units | Existing | REUSE + ALTER |
| Unit Features | Existing | REUSE |
| Unit Ownership | Unknown | VERIFY / CREATE |
| Unit Status History | Likely missing | CREATE |
| Listings | Existing/partial | VERIFY / ALTER |
| Leads | Planned/new | CREATE |
| Lead Interests | New | CREATE |
| Inquiries | Existing request flow may overlap | VERIFY / RESTRUCTURE |
| Lead Activities | New | CREATE |
| Offers | New | CREATE |
| Reservations | Unknown/partial | VERIFY / CREATE/ALTER |
| Handover | New | CREATE |
| Notifications | Existing | REUSE + EXTEND |
| Search | Existing | REUSE + EXTEND |
| Reports | Existing | REUSE + EXTEND |
| PDF/Export | Existing | REUSE + EXTEND |
| Audit | Unknown/partial | VERIFY / REUSE/EXTEND |
| AI | Existing | REUSE |
| Maintenance | Existing | REUSE |
| SaaS Subscription | Partial/existing | VERIFY / RESTRUCTURE |
| Payment Provider | Stripe-based partial | RESTRUCTURE toward abstraction |
| Filament | Existing | REUSE + EXTEND |
| Next.js frontend | Existing | REUSE + adapt APIs |

---

# 75. Expected Database Change Types

The implementation will not consist only of `CREATE TABLE`.

Expected real changes include:

```text
CREATE TABLE
ALTER TABLE ADD COLUMN
ALTER TABLE MODIFY COLUMN
ALTER NULLABILITY
ADD FOREIGN KEY
ADD INDEX
ADD UNIQUE CONSTRAINT
DROP FOREIGN KEY
DROP INDEX
DROP LEGACY COLUMN
RENAME COLUMN
RESTRUCTURE RELATIONSHIP
BACKFILL DATA
SPLIT LEGACY DATA
MIGRATE HISTORICAL RECORDS
```

Each migration must have a domain reason.

---

# 76. Required Repository Audit Before Phase 01

Before implementing the first domain phase, inspect:

```text
database/migrations
app/Models
app/Traits
app/Scopes
app/Enums
app/Services
app/Actions
app/Policies
app/Filament
app/Livewire
routes/api.php
API controllers/resources
tests
Next.js API usage
```

Produce a real inventory.

---

# 77. Required Database Inventory

For all existing relevant tables capture:

```text
table name
columns
types
nullable
defaults
indexes
unique constraints
foreign keys
delete behavior
record count if available
models using the table
major UI/API consumers
```

This inventory is especially required for:

```text
companies
users
properties
units
unit features
contracts
payments
projects
listings
requests/inquiries
documents
notifications
subscriptions
```

---

# 78. Required Model Inventory

For each relevant model capture:

```text
table
fillable/guarded
casts
traits
global scopes
relationships
local scopes
observers
events
accessors/mutators
soft deletes
default eager loads
```

This is necessary before altering schema.

---

# 79. Required Dependency Search Before Drop

Before dropping any field/table, search the entire repository for usage.

Check:

```text
PHP code
Blade
Livewire
Filament
JS/TS
Next.js
tests
seeders
factories
exports
reports
jobs
notifications
```

No legacy field is dropped based only on migration inspection.

---

# 80. Required Payment-Specific Audit

Before the payment redesign, explicitly document:

```text
current payments migration
Payment model
contract-payment relationships
payment creation flow
payment edit flow
partial payment behavior
due date generation
frequency generation
overdue behavior
Filament resource
API endpoints
frontend pages
reports
PDF/export usage
notification usage
tests
```

Then define exact old → new data mapping.

---

# 81. Required Contract-Specific Audit

Before contract redesign, document:

```text
current contract columns
contract types
status values
unit/property relation
customer/tenant relation
payment relation
PDF generation
start/end dates
frequency fields
cancellation behavior
Filament forms
API response
frontend usage
```

---

# 82. Required Unit-Specific Audit

Before Unit Setup changes, document:

```text
unit table
unit model relationships
feature relation
property/project relation
current owner fields
status
price fields
building/floor fields
listing coupling
maintenance coupling
contract coupling
```

This decides which fields remain on unit and which become relationships to other modules.

---

# 83. Required Property-Specific Audit

Document:

```text
property fields
ownership representation
seller/owner fields
acquisition/purchase fields
project coupling
listing coupling
documents
financial fields
```

---

# 84. Required Project-Specific Audit

Document whether current `projects` already contains:

```text
project manager
property/land reference
budget fields
status
start/end dates
unit relationships
cost fields
documents
```

Then decide what stays on project vs moves to child modules.

---

# 85. Required Filament Audit

For each affected Filament resource inspect:

```text
table query
columns using relationships
filters
actions
bulk actions
forms
relation managers
widgets
full-page reload patterns
redirects
notifications
N+1 behavior
tenant scope behavior
```

---

# 86. Required Performance Audit

For critical pages, inspect:

```text
query count
duplicate queries
N+1
large collection hydration
missing indexes
expensive counts
unpaginated tables
unnecessary eager loading
```

A schema migration is not complete if it significantly worsens runtime behavior.

---

# 87. Existing Global Scope Verification Checklist

Confirm:

```text
trait/scoped models
automatic company_id assignment
behavior during queue jobs
behavior for super-admin access
behavior during seeders/migrations
behavior during relation creation
behavior in Filament
behavior in API
```

Do not replace the mechanism; verify how to correctly reuse it.

---

# 88. Data Migration Safety Levels

Classify every destructive change:

## Level A — Additive

```text
new independent table
new nullable relation
new index
```

Low risk.

## Level B — Compatible Alteration

```text
new column with safe backfill
new FK after cleanup
new relationship
```

Moderate risk.

## Level C — Structural Migration

```text
payments split
ownership history conversion
contract relationship redesign
```

High risk.

## Level D — Destructive Cleanup

```text
drop old payment fields
drop old relationship columns
drop obsolete tables
```

Only after verification.

---

# 89. Migration Ordering Principle

For high-risk modules:

```text
Phase 1: introduce new schema
Phase 2: copy/backfill old data
Phase 3: update code to read/write new schema
Phase 4: verify
Phase 5: stop writing legacy schema
Phase 6: remove old schema
```

Do not perform all steps destructively in one migration unless data is disposable.

---

# 90. Rollback Consideration

Structural migrations should have a realistic rollback strategy where practical.

For financial data, prefer forward-fix migrations over dangerous reverse transforms once production data has been written to the new schema.

---

# 91. Seeders / Factories

New domain modules should include factories/seeders when useful for development/testing.

However, seed data must not hide missing required migration logic for real production records.

---

# 92. API Contract Changes

Every altered API response must be documented.

Example payment migration:

Old:

```json
{
  "amount": 1000,
  "paid_amount": 600
}
```

Target conceptually:

```json
{
  "installment": {
    "amount": 1000,
    "paid": 600,
    "remaining": 400,
    "status": "partially_paid"
  }
}
```

The API may expose derived values even though the database source is now normalized.

---

# 93. UI Migration

Old UI may use legacy fields directly.

The coding agent must update:

```text
Filament forms
Filament tables
widgets
Livewire components
Next.js pages
Next.js components
API hooks/services
reports
exports
```

when schema meaning changes.

---

# 94. Do Not Stop at Migration Files

A phase is not complete after creating tables.

Completion requires:

```text
schema
models
relationships
domain logic
authorization
Filament
API
frontend dependencies where applicable
notifications/events where applicable
tests
performance review
data verification
```

---

# 95. Preliminary Implementation Risk Ranking

## Very High Risk

```text
Payments redesign
Contract redesign
Financial data migration
Tenant isolation changes
```

## High Risk

```text
Ownership history migration
Listings/units separation if current fields are mixed
Subscription billing redesign
```

## Medium Risk

```text
Properties alterations
Project core alterations
Documents/versioning
CRM conversion from existing requests
```

## Lower Migration Risk / Mostly Additive

```text
Project planning hierarchy
Design/engineering
Budgeting
Construction
Lead activities
Offers
Handover
```

"Lower migration risk" does not mean low business complexity.

---

# 96. First Implementation Priority After Audit

Recommended order after repository verification:

```text
1. Foundation / parties / document compatibility
2. Property + acquisition + ownership
3. Project planning
4. Design/engineering
5. Budgeting
6. Construction
7. Unit Setup
8. CRM / marketing / sales
9. Contracts
10. Payments migration
11. Handover
12. Closing
13. Platform/cross-cutting
14. SaaS billing
```

Payment migration may be implemented with contracts together depending on dependency discovery.

---

# 97. Important Existing-vs-New Principle

The existing project is not disposable.

The target design is not optional.

Therefore:

```text
Do not rebuild everything.
Do not preserve incorrect legacy modeling.
```

Instead:

```text
inspect
map
migrate
evolve
verify
```

---

# 98. Required Gap Record Format Per Module

For implementation, each module should eventually have a concrete entry like:

```text
MODULE:
Current:
Target:
Gap:
Action:
Tables affected:
Models affected:
API affected:
Filament affected:
Frontend affected:
Data migration:
Risk:
Performance considerations:
Open questions:
```

This should be filled from real repository inspection before coding that module.

---

# 99. Example — Payments Gap Record

```text
MODULE:
Payments

Current:
Single payments table mixes due amount and actual paid amount.

Target:
payment_schedules
installments
payments
payment_allocations

Gap:
Current model cannot represent one payment across several installments,
or several payments toward one installment cleanly.

Action:
RESTRUCTURE
SPLIT
CREATE
MIGRATE_DATA
DROP legacy fields later

Tables affected:
contracts
payments
new payment_schedules
new installments
new payment_allocations

Models affected:
Contract
Payment
new PaymentSchedule
new Installment
new PaymentAllocation

API affected:
payment endpoints
contract payment summaries

Filament affected:
payment forms
contract payment tables
status badges
overdue views

Frontend affected:
payment/contract UI if currently exposed

Data migration:
old due rows → installments
old paid values → actual payments + allocations if semantics verify

Risk:
VERY HIGH

Performance:
eager-load schedules/installments only when required
index contract_id, payment_schedule_id, installment_id, due_date, status
```

---

# 100. Example — Units Gap Record

```text
MODULE:
Units

Current:
Existing units and UnitFeature relation.

Target:
Actual unit remains current core entity.
Optional link to planned unit.
Ownership history.
Status history.
Listing separated from unit.

Gap:
Must verify whether current unit mixes price, ownership,
listing, and planning responsibilities.

Action:
REUSE
ALTER
ADD_RELATIONSHIP
possibly MIGRATE_DATA

Risk:
MEDIUM/HIGH depending on current schema
```

---

# 101. Example — Project Planning Gap Record

```text
MODULE:
Project Planning

Current:
Project entity exists/partially exists.
Full hierarchy is absent.

Target:
Project
→ Buildings
→ Floors
→ Planned Units
→ Planned Specifications

Gap:
Planning hierarchy missing.

Action:
REUSE project
CREATE child planning tables
ADD relationships

Risk:
LOWER migration risk / HIGH domain importance
```

---

# 102. Definition of Done for Gap Analysis

The gap analysis is fully complete only when:

```text
all relevant existing tables are inventoried
all relevant models are inventoried
global scopes/traits are understood
current relationships are documented
current payment semantics are proven
current contract semantics are proven
current unit/property coupling is documented
all affected APIs are identified
all affected Filament resources are identified
all affected frontend consumers are identified
migration risks are assigned
old → new data mapping is defined
performance implications are known
```

Until then, this file is the approved working map, not the final repository-specific inventory.

---

# 103. Immediate Next Step

After this file, implementation specifications proceed phase-by-phase:

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

Before coding each phase:

```text
Read global rules
→ Read this gap analysis
→ Read phase specification
→ Inspect actual current implementation
→ Produce exact current-to-target map
→ Then implement
```

---

# 104. Final Rule

The coding agent must never answer the question:

> "Should I create this table?"

by looking only at the specification.

It must also ask:

> "Does the current project already model this concept, and if so, how?"

Likewise, it must never answer:

> "Should I keep this table?"

only because it already exists.

It must also ask:

> "Does this table still represent the approved business domain correctly?"

The target is a controlled evolution of the existing application into the approved domain model with:

```text
clean schema
clean code
correct relationships
safe data migration
preserved history
strong tenant isolation
high query performance
reactive Filament UX
no unnecessary duplication
```


# 105. Filament Shield / Policy Audit

The repository audit must explicitly inspect the existing Filament Shield and Policy architecture.

For every existing and newly introduced model, document:

```text
Model
Policy
Shield permission prefix/name
Filament Resource
Relation Managers
custom workflow permissions
roles currently receiving permissions
tenant checks
state-based authorization
```

New models must not be considered implemented without Policy + Shield coverage.

Expected action:

```text
REUSE existing authorization architecture
+
EXTEND for every new model
```

---

# 106. Notification Gap Audit

Before adding notifications to a new phase, inspect:

```text
existing notification classes
database notification setup
Filament notification usage
broadcast/toast behavior
queued channels
recipient selection patterns
existing notification preferences if any
```

For each phase, define a notification matrix.

Expected implementation rule:

```text
important domain event
→ one canonical notification dispatch path
→ tenant-safe recipients
→ persistent in-app notification when useful
→ immediate Filament toast where appropriate
→ optional queued external channels
```

Do not duplicate the same event notification in:

```text
Filament action
service/action
model observer
listener
```

simultaneously.

---

# 107. Repository Audit Must Include Authorization and Notifications

The per-phase repository audit is incomplete unless it includes:

```text
Policies
Filament Shield permissions
resource authorization
custom action permissions
notification classes
notification listeners
Filament toasts
database notifications
```

This requirement applies alongside schema, models, relationships, API, frontend, and performance inspection.


# 108. Existing Test-Suite Audit

Before implementing the first phase, inspect the repository test architecture:

```text
tests/Feature
tests/Unit
Pest or PHPUnit configuration
RefreshDatabase / database strategy
factories
seeders used by tests
Filament / Livewire tests
policy tests
notification tests
tenant/company test helpers
```

The implementation agent must follow the existing project testing convention where it is valid.

For every phase, document:

```text
existing relevant tests
new tests added
regression tests added
targeted test results
full php artisan test result
pre-existing unrelated failures, if any
```

Do not consider a phase complete until implementation-caused failures are resolved.

