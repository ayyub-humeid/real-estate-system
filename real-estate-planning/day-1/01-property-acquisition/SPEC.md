# 01 — Pre-Project: Property, Acquisition & Ownership

> **Phase:** Pre-Project / Acquisition  
> **Depends on:** `00-domain-rules-and-global-conventions.md`, `00-existing-system-gap-analysis.md`  
> **Goal:** Model the real-estate asset before project planning begins, including parties, acquisition context, ownership history, due diligence, documents, and the transition into a future project.
>
> This phase must evolve the existing project. Do not duplicate existing `properties`, existing party/customer structures, document infrastructure, tenant scope, or authorization architecture without first inspecting them.

---

## 1. Purpose

This phase answers:

```text
What real-estate asset exists?
Who is involved?
How did the company obtain or enter the asset?
Who owns it over time?
Was legal/business due diligence completed?
Which documents prove the transaction?
Is the asset ready to be used in a project?
```

The phase intentionally separates:

```text
Property
Acquisition
Ownership
Contract
Document
Due Diligence
Project
```

They are related, but they are not the same business concept.

---

## 2. Business Context

A real-estate company may begin with land/property through different scenarios:

```text
1. Property already owned before the system.
2. Cash purchase from one or multiple sellers.
3. Purchase paid over installments.
4. Partnership with the land owner.
5. Land-for-units agreement.
6. Profit-sharing agreement.
7. One acquisition involving multiple properties.
8. One property involving multiple owners.
9. Acquisition passes due diligence but legal transfer occurs later.
10. Acquisition is cancelled before completion.
```

The schema must support these without inventing a different property table for each case.

---

## 3. Core Separation of Concepts

### Property

The underlying real-estate asset.

Examples:

```text
land parcel
building
existing real-estate asset
```

### Property Acquisition

The business event/agreement describing how the company obtained or entered rights related to the property.

### Property Ownership

The legal/economic ownership history of the property.

### Contract

The formal agreement between parties.

### Due Diligence

The verification process before accepting or completing the acquisition.

### Project

A later development/business project that may use one or more properties.

---

## 4. High-Level Workflow

```text
Create / Identify Parties
        ↓
Create / Reuse Property
        ↓
Create Acquisition
        ↓
Attach Property / Properties
        ↓
Attach Involved Parties
        ↓
Collect Documents
        ↓
Open Due Diligence
        ↓
Review Due Diligence Items
        ↓
Approve / Reject Acquisition
        ↓
Complete Acquisition
        ↓
Create / Update Ownership History when legally confirmed
        ↓
Property becomes available for Project Creation
```

Do not automatically create ownership merely because acquisition status becomes `completed` unless legal ownership is actually confirmed.

---

# 5. Existing-System Mapping

Before implementation inspect:

```text
properties
owners / customers / tenants / contacts
contracts
documents / attachments
projects
payments / expenses
company scope trait
policies
Filament Shield
existing Filament resources
```

Expected classification:

| Concept | Expected action |
|---|---|
| Existing `properties` | REUSE + ALTER only where required |
| Existing tenant Global Scope / Trait | REUSE |
| Existing Roles / Shield | REUSE + EXTEND |
| Generic `parties` | VERIFY; CREATE only if missing |
| Property ownership history | VERIFY; likely CREATE |
| Property acquisitions | likely CREATE |
| Acquisition properties | CREATE |
| Acquisition parties | CREATE |
| Due diligence | likely CREATE |
| Documents | VERIFY / REUSE |
| Contracts | REUSE; detailed redesign later |

Do not create `properties_v2`.

---

# 6. Entity: Party

## Purpose

Represents a reusable real-world actor.

Potential examples:

```text
individual land owner
seller
partner
engineering office
supplier
company
customer
representative
```

The role is contextual and belongs to the relationship.

## Target Table

```text
parties
```

Create only if a suitable reusable structure does not already exist.

## Recommended Fields

| Field | Type | Nullability | Notes |
|---|---|---:|---|
| id | project standard PK | required | Follow existing ID convention |
| company_id | FK | architecture-dependent | Required if existing tenant trait requires direct company ownership |
| type | string | required | `individual`, `company`, `organization` or approved equivalent |
| name | string | required | Display/common name |
| legal_name | string | nullable | Registered/legal name |
| national_id | string | nullable | Individual identity if applicable |
| registration_number | string | nullable | Company registration |
| tax_number | string | nullable | If applicable |
| phone | string | nullable | |
| email | string | nullable | |
| address | text/string | nullable | |
| notes | text | nullable | |
| is_active | boolean | required | default true |
| created_at / updated_at | timestamps | required | |

Do not force identity/tax information when it is not available.

## Relationships

```text
Party
├── acquisitionParties
├── contractParties
├── ownership records
├── design assignments
└── project partnerships (later phase)
```

---

# 7. Entity: Property

## Current State

`properties` already exists.

## Target Meaning

Property is the underlying real-estate asset.

It must not become a container for:

```text
acquisition workflow
ownership history
project workflow
listing workflow
payment schedule
```

## Implementation Rule

Inspect the existing table and keep all fields that still represent the property itself.

Potential target property data may include:

```text
name/title
property type
parcel/reference number
area
location/address
geographic/legal identifiers
description
status
```

Do not blindly add fields already represented by the existing schema.

## Relationships

```text
Property
├── ownerships
├── acquisitions through acquisition_properties
├── contracts through contract-property relation
├── projects through project_properties
└── documents where applicable
```

---

# 8. Entity: Property Ownership

## Target Table

```text
property_ownerships
```

## Purpose

Preserve who owns the property and for what period.

## Recommended Fields

| Field | Type | Nullability | Notes |
|---|---|---:|---|
| id | PK | required | |
| property_id | FK | required | |
| party_id | FK | required | Owner |
| ownership_percentage | decimal | required | Must be > 0 and <= 100 |
| start_date | date | required | Effective ownership start |
| end_date | date | nullable | Null = currently active |
| acquisition_id | FK | nullable | Acquisition that caused this ownership state if known |
| notes | text | nullable | |
| created_at / updated_at | timestamps | required | |

If the tenant Global Scope requires `company_id` directly on the model, include it according to existing architecture.

## Rules

```text
- Ownership history is never overwritten casually.
- Current ownership = rows with end_date = null.
- Current ownership percentages should total 100% when full ownership is represented.
- Historical rows remain queryable.
- Selling/reserving/handing over property does not automatically rewrite ownership.
```

For partial ownership transfers, close or adjust affected active rows transactionally and create new historical rows.

---

# 9. Entity: Property Acquisition

## Target Table

```text
property_acquisitions
```

## Purpose

Represents how the company obtained/entered rights involving one or more properties.

## Recommended Fields

| Field | Type | Nullability | Notes |
|---|---|---:|---|
| id | PK | required | |
| company_id | FK | according to existing tenant architecture | |
| reference_number | string | nullable | Human/business reference |
| type | string/enum | required | See types below |
| status | string/enum | required | See lifecycle |
| acquisition_date | date | nullable | Date agreement/effective event occurred |
| agreed_value | decimal | nullable | Commercial value when meaningful |
| currency | string(3/10) | nullable | Required when agreed_value exists |
| description | text | nullable | |
| notes | text | nullable | |
| approved_by | FK users | nullable | |
| approved_at | timestamp | nullable | |
| completed_at | timestamp | nullable | |
| cancelled_at | timestamp | nullable | |
| cancellation_reason | text | nullable | |
| created_at / updated_at | timestamps | required | |

## Acquisition Types

Initial approved values:

```text
cash_purchase
installment_purchase
partnership
land_for_units
profit_sharing
owned_existing
```

Keep implementation extensible through application enum/configuration.

Do not use DB-native enum if it harms MySQL/PostgreSQL portability.

---

# 10. Acquisition Status Lifecycle

Recommended lifecycle:

```text
draft
  ↓
under_due_diligence
  ↓
approved
  ↓
completed
```

Alternative terminal path:

```text
draft / under_due_diligence / approved
        ↓
cancelled
```

Rules:

```text
draft:
  editable.

under_due_diligence:
  parties/properties established; due diligence active.

approved:
  commercial/legal review accepted for proceeding.

completed:
  acquisition process is completed.

cancelled:
  preserved as historical record.
```

Completion does **not** automatically mean legal ownership changed.

Ownership history changes only through an explicit ownership operation.

---

# 11. Entity: Acquisition Property

## Target Table

```text
acquisition_properties
```

## Purpose

Many-to-many relation because:

```text
one acquisition may cover multiple properties
one property may have multiple acquisition events over time
```

## Recommended Fields

| Field | Type | Nullability | Notes |
|---|---|---:|---|
| id | PK | required | Treat as real relationship model |
| property_acquisition_id | FK | required | |
| property_id | FK | required | |
| share_percentage | decimal | nullable | Portion involved if partial |
| allocated_value | decimal | nullable | Portion of acquisition value assigned to this property |
| notes | text | nullable | |
| timestamps | timestamps | required | |

## Constraints

```text
unique(property_acquisition_id, property_id)
```

unless the business later proves multiple rows for the same property/acquisition are necessary.

---

# 12. Entity: Acquisition Party

## Target Table

```text
acquisition_parties
```

## Purpose

Represents parties involved in the acquisition and their contextual role.

## Recommended Fields

| Field | Type | Nullability | Notes |
|---|---|---:|---|
| id | PK | required | |
| property_acquisition_id | FK | required | |
| party_id | FK | required | |
| role | string | required | Seller, land_owner, partner, representative, etc. |
| share_percentage | decimal | nullable | When role requires it |
| notes | text | nullable | |
| timestamps | timestamps | required | |

Do not encode every role into separate actor tables.

---

# 13. Due Diligence Case

## Target Table

```text
due_diligence_cases
```

## Purpose

Tracks formal review before acquisition approval/completion.

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| property_acquisition_id | FK | required |
| property_id | FK | nullable |
| status | string | required |
| opened_at | timestamp | required |
| completed_at | timestamp | nullable |
| opened_by | FK users | nullable |
| completed_by | FK users | nullable |
| summary | text | nullable |
| timestamps | timestamps | required |

A property-specific case may set `property_id`.

A case covering the whole acquisition may leave it null.

## Statuses

```text
open
in_review
cleared
blocked
closed
```

`blocked` means unresolved findings prevent approval/completion.

---

# 14. Due Diligence Item

## Target Table

```text
due_diligence_items
```

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| due_diligence_case_id | FK | required |
| title | string | required |
| category | string | nullable |
| description | text | nullable |
| is_required | boolean | required |
| status | string | required |
| checked_by | FK users | nullable |
| checked_at | timestamp | nullable |
| notes | text | nullable |
| sort_order | integer | required/default |
| timestamps | timestamps | required |

## Item Statuses

```text
pending
passed
failed
waived
```

`waived` must require authorization and should be auditable.

---

# 15. Due Diligence Completion Rules

A due-diligence case may become `cleared` when:

```text
all required items are either passed
or explicitly waived by an authorized user
```

A required failed item prevents clearance unless resolved/waived according to business policy.

Do not hard-code legal rules that have not been approved.

---

# 16. Documents

Reuse the generic document infrastructure.

Typical documents may include:

```text
title deed
land registration
survey
seller identity
company registration
tax/legal certificates
purchase agreement
partnership agreement
land-for-units agreement
due diligence evidence
```

Use:

```text
documents
document_versions
```

Do not create:

```text
property_documents_v2
acquisition_files
due_diligence_files
```

if the generic document system already supports ownership/context.

---

# 17. Contracts Integration

Acquisition agreements must use the generic contract domain.

Do not create:

```text
purchase_contracts
land_contracts
partnership_contracts
```

as separate contract engines.

During this phase:

```text
- reuse the existing contract model,
- attach the relevant parties/properties through the approved generic relations,
- avoid deep payment redesign until the contracts/payments phase.
```

If an explicit Acquisition ↔ Contract relation is missing, inspect the existing contract architecture before deciding whether a direct FK or relationship table is needed.

Do not infer it automatically.

---

# 18. Financial Effect

This phase may record:

```text
agreed acquisition value
currency
property-level allocated value
partner contribution context
```

But:

```text
Land valuation ≠ cash payment
Land valuation ≠ automatic payable
Acquisition completed ≠ money fully paid
```

Do not reuse the old mixed `payments` table to model acquisition obligations.

Detailed receivable/payment redesign belongs to `08-contracts-payments.md`.

Project budget integration occurs later.

---

# 19. Existing Property Migration

For existing properties:

```text
1. Keep property rows.
2. Identify current owner data if present.
3. Create initial ownership history only when data is trustworthy.
4. Use owned_existing acquisition only when historically appropriate.
5. Do not invent acquisition date/value/party.
6. Preserve legacy columns until dependent code is migrated.
```

If historical acquisition information is unknown:

```text
do not fabricate it.
```

---

# 20. Ownership Transaction Safety

Ownership updates should run inside a DB transaction.

Before writing new active ownership:

```text
validate property
validate parties belong to correct tenant context
validate percentages
close/adjust previous active rows
insert new ownership rows
verify active total when applicable
write audit entry
commit
then notify
```

---

# 21. Important Notifications

Notifications are required for important events, not trivial edits.

## Notification Matrix

| Trigger | Recipients | Channel | Notes |
|---|---|---|---|
| Acquisition moved to `under_due_diligence` | users with acquisition/due-diligence responsibility | persistent in-app + Filament toast for actor | after commit |
| Required due-diligence item failed | responsible reviewers + authorized acquisition approvers | in-app | high importance |
| Due-diligence case becomes `blocked` | acquisition responsible users/admin | in-app | |
| Due-diligence case becomes `cleared` | acquisition approvers | in-app | |
| Acquisition approved | creator/responsible users + relevant admin/legal/finance permission holders | in-app | |
| Acquisition cancelled | involved responsible internal users | in-app | include reason |
| Acquisition completed | relevant admin/project-development users | in-app | property can proceed to project workflow |
| Property ownership changed | authorized admin/legal users + affected responsible users | in-app | critical historical event |
| Ownership percentage inconsistency detected | actor + admin | immediate validation/toast, no commit | error rather than business notification |

External email/WhatsApp remains optional/configurable.

Do not notify all users in the company.

---

# 22. Authorization / Filament Shield

Every new Eloquent model must have a Policy and Shield coverage.

## Required Models / Policies

| Model | Policy |
|---|---|
| Party | `PartyPolicy` |
| PropertyOwnership | `PropertyOwnershipPolicy` |
| PropertyAcquisition | `PropertyAcquisitionPolicy` |
| AcquisitionProperty | `AcquisitionPropertyPolicy` |
| AcquisitionParty | `AcquisitionPartyPolicy` |
| DueDiligenceCase | `DueDiligenceCasePolicy` |
| DueDiligenceItem | `DueDiligenceItemPolicy` |

Existing `Property` policy must be inspected and extended if required.

## Standard Permissions

Follow existing Shield naming/convention for:

```text
view_any
view
create
update
delete
delete_any
restore/force-delete if model supports them
```

## Custom Workflow Permissions

Add explicit permissions where needed:

```text
approve_property_acquisition
cancel_property_acquisition
complete_property_acquisition
waive_due_diligence_item
clear_due_diligence_case
change_property_ownership
```

Do not allow generic `update` to imply these sensitive actions.

---

# 23. Filament Resources

Expected admin surfaces:

```text
Property Resource          → extend existing
Party Resource             → if generic parties are introduced
Property Acquisition Resource
Due Diligence relation/page
Ownership history relation manager
Documents relation manager
```

Prefer relation managers for relationship entities where a standalone resource adds little UX value.

However, the Eloquent model still requires authorization.

---

# 24. Filament UX

Normal actions should be reactive.

Examples:

```text
attach acquisition party
add due-diligence item
mark item passed/failed
approve acquisition
cancel acquisition
change ownership
```

should update the relevant table/status/badges without full page reload.

Use:

```text
Filament Actions
modal forms
Livewire state refresh
notifications/toasts
```

Avoid `window.location.reload()`.

---

# 25. Eager Loading

Typical Property Acquisition pages may need:

```text
properties
parties
dueDiligenceCases.items
documents
approver
```

Do not globally eager-load all of them on every acquisition query.

For list pages, prefer:

```text
withCount()
limited relationships
latest/current status
```

For detail pages, load full context deliberately.

---

# 26. Indexes

Likely indexes:

```text
property_acquisitions(company_id, status)
property_acquisitions(type)
property_acquisitions(acquisition_date)

property_ownerships(property_id, end_date)
property_ownerships(party_id)

acquisition_properties(property_acquisition_id, property_id)
acquisition_parties(property_acquisition_id, party_id)

due_diligence_cases(property_acquisition_id, status)
due_diligence_items(due_diligence_case_id, status)
```

Follow actual query patterns and existing tenant architecture.

---

# 27. Delete Rules

Prefer restriction/history preservation.

Examples:

```text
Property with ownership/acquisition history:
  do not hard delete casually.

Completed acquisition:
  do not hard delete.

Ownership row:
  historical record; no casual deletion.

Due diligence after acquisition approval/completion:
  preserve history.
```

Draft data with no downstream dependency may be deletable if policy allows.

---

# 28. Real-World Scenario A — Existing Owned Land

```text
Property exists
→ acquisition = owned_existing
→ known current owners recorded
→ documents attached
→ no fake purchase payment
→ property can later attach to project
```

---

# 29. Real-World Scenario B — Cash Purchase

```text
Seller Party
→ Property
→ cash_purchase Acquisition
→ Due Diligence
→ Approved
→ Contract/documentation
→ Completed
→ Explicit ownership update when legally effective
```

---

# 30. Real-World Scenario C — Land-for-Units

```text
Land Owner Party
→ Property
→ land_for_units Acquisition
→ Agreement/Contract
→ Acquisition completed
→ Property used by Project
→ Project Partner created later
→ Allocated units recorded later through project partner/unit allocation domain
```

Do not create future unit allocations in this pre-project phase.

---

# 31. Real-World Scenario D — Multiple Owners

```text
Property
├── Owner A 60%
└── Owner B 40%

Acquisition
├── AcquisitionParty A
└── AcquisitionParty B
```

Ownership percentages and acquisition-party commercial roles remain separate.

---

# 32. Tests

Minimum tests:

```text
tenant isolation
property reuse
acquisition creation
multiple properties per acquisition
multiple parties per acquisition
due-diligence clearance rules
waiver authorization
acquisition transition validation
ownership percentage validation
historical ownership preservation
cancelled acquisition cannot complete
policy/Shield authorization
important notification dispatch
notification occurs after successful transaction
```

---

# 33. Definition of Done

This phase is complete only when:

```text
existing properties are reused
new schema is migration-safe
party strategy is verified
ownership history works
acquisition workflow works
due diligence works
generic documents are reused
generic contracts remain compatible
every new model has Policy + Shield permissions
important operations generate correct notifications
Filament UX is reactive
queries avoid N+1
indexes are added based on real usage
tests pass
existing data is preserved
```

---

# 34. Handoff to Next Phase

Output of this phase:

```text
verified property
+
acquisition context
+
ownership history
+
due diligence
+
documents
```

becomes input for:

```text
02-project-planning.md
```
