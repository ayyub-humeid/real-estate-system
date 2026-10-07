# 06 — Unit Setup: Actual Units, Readiness & Asset Truth

> **Phase:** Unit Setup  
> **Depends on:** Phase 01 Property / Acquisition, Phase 02 Project Planning,
> Phase 03 Design, Phase 05 Construction  
> **Goal:** Evolve the existing `units` domain into the authoritative record of
> a real, physical unit without duplicating planning, features, documents, or
> financial systems.

---

## 1. Purpose and strict boundaries

This phase answers:

```text
Which actual units physically exist?
Where are they located and which Project/Property do they belong to?
Which planned unit, if any, did each actual unit originate from?
What is the actual area, type, configuration, features, legal ownership,
documents, and current operational status?
```

It implements **Unit Setup only**. It must not implement:

```text
Marketing listings, public availability feeds, price lists, leads,
reservations, customer sales, sales/rental contracts, payment schedules,
installments, handover, keys, defects, or customer portals.
```

Phase 08 will own commercial Contract/Payment workflow. Existing rental/API
behaviour must be refactored safely during this phase so it no longer treats a
physical Unit status as a reservation, tenancy, or marketing decision.

---

## 2. Repository mapping and approved actions

| Existing concept | Current state confirmed in repository | Phase 06 action |
|---|---|---|
| `units` / `Unit` | Existing company-scoped actual unit table; property, number, rent, legacy mixed-purpose status, type, bedrooms, bathrooms, `sqft`, description, images, leases | **REUSE + ALTER + RESTRUCTURE**; never create `actual_units` or `units_v2` |
| `unit_features` / `UnitFeature` | Existing `unit_id`, free-text `name`, optional `value` | **REUSE + ALTER** for canonical feature keys; retain custom free-text features |
| `images` | Existing Unit polymorphic gallery and primary image | **REUSE**, outside Unit-document scope |
| `documents` / `document_versions` | Existing logical/versioned document infrastructure | **REUSE + ADD_RELATIONSHIP**; no unit attachment table or file-path column |
| `project_planned_units` | Planning-only unit, with approved / converted states | **REUSE + ADD_RELATIONSHIP**; remains historical planning truth |
| `planned_unit_specifications` | Planning-only name/value/unit notes | **REUSE AS HISTORY**; do not turn it into actual unit features |
| `property_ownerships` | Property-level historical ownership | **REUSE PATTERN**, not a substitute for per-unit legal ownership |
| `properties` / `projects` | Existing tenant-scoped parent aggregates | **REUSE + ADD_RELATIONSHIP** |

The current `Unit` resource is rental-oriented and has generic status editing,
free-text feature entry, and no project/planned-unit traceability or historical
unit status/ownership records. Its `status` currently mixes physical readiness
with commercial concepts: `occupied` is duplicated and inconsistently written
alongside active Leases, while `reserved` has no Reservation record or workflow.
Phase 06 corrects that model without deleting existing units or their leases,
images, ratings, maintenance requests, or API relationships.

---

## 3. Core domain model

```text
Property
  └── Actual Unit (existing `units` table)
       ├── optional Project
       ├── optional Planned Unit source (one actual Unit per Planned Unit)
       ├── Unit Features (existing reusable `unit_features`)
       ├── Unit Ownership history
       ├── Unit Status history
       ├── Documents → Document Versions
       ├── Images (existing gallery)
       ├── existing Leases / Maintenance Requests / Ratings
       └── optional Project context through `project_id`

Project
  ├── Project Properties
  ├── Planned Unit (planning history)
  └── Actual Units (physical inventory)
```

Important distinctions:

```text
Planned Unit ≠ Actual Unit
Planned Unit Specification ≠ Actual Unit Feature
Property Ownership ≠ Unit Ownership
Unit status ≠ marketing listing status
Unit status ≠ contract/payment status
Unit document ≠ image/gallery file
```

---

## 4. Actual Unit schema evolution

Only ALTER migrations may modify `units`; do not recreate or rename the table.

### 4.1 `units` additions

| Field | Type | Rule / purpose |
|---|---|---|
| `project_id` | nullable FK → projects | Actual Project context. Nullable for existing/non-project units. |
| `planned_unit_id` | nullable FK → project_planned_units | Traceable source. Nullable for legacy/manual units. Unique when present. |
| `actual_area` | decimal(12,2), nullable | The measured/executed area. |
| `area_unit` | string, nullable/default by migration | `m2`, `sqft`, or another approved controlled unit. |
| `location_label` | nullable string | Actual human-readable physical location only when Project planning structure cannot describe it. |
| `ready_at` | nullable timestamp | System-recorded date when Unit becomes physically ready. |
| `inactive_at` | nullable timestamp | System-recorded terminal/inactive timestamp. |
| `inactive_reason` | nullable text | Required when moved to inactive. |

The existing `units.status` column is the single authoritative physical Unit
status. The Phase 06 migration changes it to a normal portable
`VARCHAR(32)`/Laravel `string`, with no database enum or check constraint.
There is no second operational-status column and no compatibility status
layer. Other existing columns remain intact:

```text
property_id, unit_number, rent_price, status, type, bedrooms, bathrooms,
sqft, description, is_featured, agency_id, timestamps
```

`sqft` remains for legacy/API compatibility. `rent_price` also remains only as
a nullable legacy/commercial reference because existing Lease, API, and Stripe
code still reads it; Actual Unit setup must never require or invent it. New
physical-setup forms use `actual_area` plus `area_unit`. Migration must backfill a trustworthy `sqft > 0` value into
`actual_area` with `area_unit = sqft`; it must not invent an area where none is
known. The later commercial phase may replace `rent_price` with a dedicated
pricing domain; until then it remains nullable and is not a Phase 06 workflow
field.

### 4.2 Physical status only — cutover from the legacy mixed status

`units.status` is the authoritative **physical/operational** state. It stores
only a `UnitStatus` backed-PHP-enum value; the database deliberately enforces
only its portable string shape. `UnitSetupService` owns all allowed
transitions, so direct generic CRUD cannot change it.

```text
draft        physical record is being prepared or verified
ready        physically complete and usable; it says nothing about sale/rental availability
maintenance  temporarily not usable because of maintenance
inactive     retired, withdrawn, or not usable; reason required
```

The following concepts do **not** belong in a physical Unit status:

```text
available    commercial/listing eligibility; Phase 07/08 owns it
reserved     reservation workflow; a future Reservation record owns it
occupied     tenancy outcome; derived from an active Lease/Contract, never stored on Unit
```

#### Mandatory legacy cutover and impact handling

Before migration, implementation must inventory every legacy status row and
every active Lease. The migration then maps the current Unit row without
destroying Lease or historical data:

```text
available  → ready
occupied   → ready       (tenancy is derived from the active Lease, if any)
reserved   → ready       (there is no Reservation relation to preserve)
maintenance → maintenance
```

Legacy status data may be discarded cleanly during the cutover: map every
non-maintenance legacy value to `ready`, map `maintenance` to `maintenance`,
and do not retain synthetic compatibility history. No row is changed to `draft`
merely to satisfy the new model. A pre-release report must flag legacy
`occupied` Units with no active Lease and active Leases whose Unit is not legacy
`occupied`; both are data quality findings, not reasons to fabricate contracts
or reservations.

The old `Unit::isAvailable`, `available()` and `occupied()` scopes must be
removed/replaced; no compatibility method may make a commercial decision from
`units.status`. Use explicit physical helpers/scopes against
`status` such as
`isOperationallyReady()` / `operationallyReady()` and a separate derived
`tenancyState` / `currentLease` query. `ready` is not a promise that the Unit
is marketable.

Existing public API consumers must be changed in the same release to expose
`status` and derived `tenancy_state`; retain a documented,
time-limited response alias only if a verified external consumer requires it.
That alias must be marked deprecated and must never become the new commercial
source of truth. Phase 07/08 will replace legacy public availability filtering
with Listings/Reservations/Contracts.

### 4.3 `unit_features` evolution

Add nullable `feature_key` to the existing table and a portable unique index
on `(unit_id, feature_key)`. A `NULL` key remains valid for user-defined custom
features and preserves legacy rows.

Controlled catalogue examples:

```text
furnished (yes/no)              balcony (yes/no)
parking_spaces (number)         storage_room (yes/no)
maid_room (yes/no)              central_ac (yes/no)
smart_home (yes/no)             sea_view (yes/no)
garden (yes/no)                 orientation (select)
kitchen_type (select)           parking_type (select)
```

The canonical feature key, display label, input type, and select options belong
in a small application catalogue/configuration—not in a second database feature
system. Values continue to live in existing `unit_features.value`. Custom rows
continue using `name` + optional `value`, with `feature_key = null`.

### 4.4 New historical records

#### `unit_status_histories`

```text
id
company_id
unit_id
from_status nullable
to_status
reason nullable / required for inactive
notes nullable
changed_by nullable FK users
changed_at timestamp
timestamps
```

Indexes:

```text
index(unit_id, changed_at)
index(company_id, to_status)
```

The Phase 06 status cutover writes one explicit baseline history row for every
legacy Unit (see 4.2). Thereafter, only the controlled transition service may
append history.

#### `unit_ownerships`

```text
id
company_id
unit_id
party_id
ownership_percentage decimal(5,2)
start_date
end_date nullable
notes nullable
changed_by nullable FK users
timestamps
```

Indexes/constraints:

```text
index(unit_id, end_date)
index(company_id, party_id)
```

Active ownership is defined by `end_date IS NULL`. The database cannot portably
enforce a percentage sum, so the dedicated service must lock active ownership
rows and enforce exactly `100.00%` for an ownership replacement. Partial draft
ownership must not masquerade as current legal ownership.

---

## 5. Planned Unit → Actual Unit conversion

### Preconditions

The conversion action is available from Project Planning and Unit Setup only
when:

```text
planned unit is approved
planned unit has no linked actual unit
parent Project belongs to the selected Company
Project is in_progress, completed, or closed (never planning/cancelled)
actor has create Unit + create_actual_unit_from_planned_unit permission
```

Construction completion is not an automatic creator. A human confirms that a
physical unit exists; the action must never create units merely because a
Construction execution reaches completed.

### Conversion wizard

1. User selects an approved Planned Unit.
2. System derives Company and Project from its floor/building.
3. User selects a Property from that Project’s active `project_properties`.
   This is required because a Project may contain multiple Properties.
4. Wizard pre-fills, but does not lock:

```text
unit_number      ← planned code
type             ← planned unit_type (controlled mapping)
actual_area      ← planned_area (clearly marked “copied from plan; verify”)
area_unit        ← m2 unless the plan explicitly supplied another unit
description      ← planned description
project_id       ← parent Project
planned_unit_id  ← selected planned unit
```

`App\Enums\UnitType` is the one shared catalogue for both `project_planned_units.unit_type`
and physical `units.type`. The conversion pre-fills the actual type from the
plan, while the user may choose another valid catalogue value if execution
differs. Free-text types and per-screen type lists are prohibited. Legacy
labels are normalised by an explicit data migration (`shop` becomes `retail`;
unrecognised non-null labels become `other`).

5. The user verifies/edits actual physical values and saves a Unit in `draft`.
6. The service atomically links the unit and changes only the Planned Unit
   status to `converted`; its code, area, description, and specifications are
   never overwritten.

### Specifications and features

Planned specifications are displayed in a read-only **Planning comparison**
section. They are not automatically treated as true features.

The wizard may offer an explicit opt-in **Copy recognized planning values as
initial actual features** preview. It may create only catalogue-mapped values;
unmapped specifications remain planning history and are never silently copied.
The user can correct every actual feature afterward.

### Manual actual unit creation

A user may create an actual Unit without a Planned Unit. In that case:

```text
property_id is required
planned_unit_id is null
project_id is optional
if project_id is present, property must be actively attached to that Project
company must be derived from the Property and match Project/Planned Unit
```

This preserves all existing/legacy units.

---

## 6. Actual state, status history, and ownership

### 6.1 Status workflow

Generic Unit Create/Edit forms must not mass-assign `status`, `ready_at`,
`inactive_at`, or history fields. A `UnitSetupService` owns transitions and
writes `unit_status_histories` in the same transaction.

Allowed Phase 06 transitions:

```text
draft       → ready, maintenance, inactive
ready       → maintenance, inactive
maintenance → ready, inactive
inactive    → draft only with an explicit reactivation reason and permission
```

`ready` requires a valid property, unit number, type, and actual area with unit.
It sets `ready_at` once. `inactive` requires a reason. No Phase 06 action
creates, modifies, cancels, reserves, or infers a Lease/Contract/Reservation.
Tenancy is displayed from `currentLease`; commercial availability is deliberately
not calculated or persisted here. Phase 07/08 owns that commercial workflow
while preserving this physical history table.

### 6.2 Unit ownership

Property ownership remains land/property ownership. It must not be copied to a
Unit automatically because individual title ownership can differ.

Unit ownership is optional during physical setup. When legal unit ownership is
recorded, a controlled **Replace ownership allocation** action creates the new
active set, validates same Company Parties and exactly 100%, closes prior active
rows with an end date, and preserves all history. No raw edit/delete action is
allowed for active or historical ownership rows.

The Company’s canonical self Party can be an owner, and external Party records
can be co-owners. Property ownership is displayed as context only.

---

## 7. Unit documents and images

Use the existing polymorphic relationship:

```text
Unit → documents → document_versions
```

Examples: title deed, completion certificate, unit plan, maintenance manual,
meter handover document, warranty.

Requirements:

- a Unit document is a logical `Document` whose `documentable` is the Unit;
- each uploaded file is an immutable `DocumentVersion` with `company_id`
  inherited from the Unit;
- version creation validates Unit/document/version Company context server-side;
- Document Policy/Resource queries must be extended safely so Unit documents
  are tenant-isolated, rather than keeping the current Design-Package-only
  assumption;
- a document/version pinned by another future workflow remains protected by
  the existing delete guard;
- images remain in existing `images` gallery and are not duplicated as
  documents.

---

## 8. Tenancy, validation, concurrency, and deletion

Reuse `HasCompany`, its global scope, existing Super Admin context rules, and
the Company-owned policy helpers.

Server-side validation must reject:

```text
Property, Project, Planned Unit, Party, Document, or DocumentVersion from another Company
Planned Unit whose parent Project differs from submitted project_id
Property not actively attached to submitted Project
second actual Unit for the same Planned Unit
Unit number duplicate within a Property
invalid controlled feature value / duplicate canonical feature key
invalid Unit status transition
ownership total other than 100% at replacement
manual user-supplied company_id that differs from the derived parent Company
```

For Super Admin, top-level Unit creation must require/select a valid Property
first and derive its Company. A Project/Planned Unit selection is scoped only
after Company/Property context is valid. Missing context produces a domain
validation notification, never a SQL default-value error.

Concurrency:

- conversion locks the Planned Unit and checks the nullable unique link inside
  one transaction;
- ownership replacement locks the Unit and current ownership rows;
- status transition locks the Unit and writes its history atomically;
- unique constraints remain the final protection against races.

Deletion rules:

```text
draft Unit with no documents, ownership, status history, lease, maintenance,
or rating history may be deleted;
otherwise use inactive status, never raw deletion.
```

Existing legacy relationships are preserved and must be checked before any
destructive action.

### 8.1 Required legacy integration refactor

The following existing usages are in scope for the Phase 06 cutover and must be
changed together, not left as hidden references to removed legacy values:

| Surface | Current legacy behavior | Required safe replacement |
|---|---|---|
| `Unit` model | `isAvailable`, `available`, `occupied`, `maintenance` query scopes inspect legacy `status` | Keep only physical scopes/helpers against `status`; derive tenancy from `currentLease`. |
| Unit Resource + Property → Units manager | Generic create/edit select, badge and filter offer available/occupied/reserved | Show physical status only; use an explicit authorized transition action, physical badge/filter, and a separate read-only tenancy indicator. |
| `Lease` model + Lease Resource | Unit selector filters `available`; termination writes `available` into Unit | Select a `ready` Unit with no active Lease inside transaction; termination changes only the Lease. Never mutate physical status for tenancy. |
| Rental checkout / RentalRequest API | `isAvailable()` decides lease checkout eligibility | Do not use physical status as commercial availability. Until Phase 07/08 replaces this legacy endpoint, guard only operational readiness plus active-lease/rental-request rules, lock the Unit where a race matters, and expose the limitation clearly. |
| Public Unit API / `UnitResource` / Agency API | List and featured endpoints filter `available`; API emits the mixed status and legacy color | Return `status` plus derived `tenancy_state`; update in-repository clients and contract tests. Do not present `ready` as a reservation/listing guarantee. |
| Occupancy widget + Financial Report | Counts stored `status = occupied` | Calculate occupancy from active Leases; calculate physically ready/vacant operational inventory separately. Queries must remain MySQL/PostgreSQL portable and scoped. |
| Seeders, fixtures and tests | Seed/status assertions use available/occupied | Seed physical `ready`/`maintenance` and active Lease relationships explicitly; replace assertions with physical and derived-tenancy expectations. |
| Config/status colors | Colors map commercial values | Map only `draft`, `ready`, `maintenance`, `inactive`; tenancy badges use their own presentation map. |

The repository currently contains no separate SPA source tree; implementation
must still inspect every shipped Blade/JavaScript/API consumer and any deployed
external API client before removing a response field. If an external consumer
cannot be updated in the release, stop before a breaking API change and agree a
short, documented deprecation adapter and sunset date with the developer.

---

## 9. Authorization and permissions

`UnitResource` and `UnitFeatureResource` already exist as top-level Shield
resources. Reuse their generated permissions; do not invent a second Unit
resource or permission naming convention. Phase implementation must regenerate
Shield permissions and verify the exact DB names/policies after the resource
changes.

Add seeded custom permissions for workflow-only/child records:

```text
create_actual_unit_from_planned_unit
change_unit_status
reactivate_unit
change_unit_ownership
view_unit_ownership
view_any_unit_ownership
view_unit_status_history
view_any_unit_status_history
manage_unit_documents
```

If existing generated Document/DocumentVersion permissions cover a concrete
action, reuse them rather than duplicating them. Policies combine permission,
Company, parent record, and mutable state. Filament visibility is never the
security boundary.

---

## 10. Filament UX

### 10.1 Primary workspaces

1. **Units** top-level Resource: fast inventory list, filters, Create Actual
   Unit, view/edit setup metadata.
2. **Project → Actual Units** relation workspace: convert an approved Planned
   Unit or add a manual actual Unit in Project context.
3. **Property → Units** relation workspace: create/manual manage actual units
   for that Property, preserving the existing entry point.

### 10.2 Fast setup form

Use clear sections and reactive, scoped selectors:

```text
Identity & location     Property, optional Project/Planned Unit, unit number, type
Physical reality        actual area + unit, bedrooms, bathrooms, location label
Common features         Yes/No toggles, number inputs, select controls
Custom features         existing UnitFeature name/value rows
Description             optional actual description
```

When a Planned Unit is selected, pre-fill values with a clear planning-source
helper message and show the planned values beside the actual editable values.
The user must never repeatedly type ordinary boolean/numeric characteristics.

### 10.3 Unit details

Use a Details/View page or modal with tabs:

```text
Overview             actual identity, location, status, physical attributes
Planning comparison  source planned unit and its read-only specifications
Features             common features + custom entries
Ownership            current allocation and historical allocations
Documents            logical documents and immutable versions
Status history       chronological status transitions and reasons
Gallery              existing images
Existing operations  leases / maintenance requests only as existing context
```

Use readable related labels, never raw foreign-key IDs. Statuses use the shared
semantic badge colors. Actions have tooltips. Details must surface history,
not merely the current Unit row.

All mutation actions use service methods, update only the affected Livewire
state, send a success toast after mutation, and convert validation/
authorization failures to clear danger notifications. No full-page reload.

### 10.4 Action availability

```text
Create from plan       approved, unconverted Planned Unit
Edit setup metadata    draft/ready/maintenance as state permits
Change status          authorized explicit transition only
Replace ownership      authorized Unit with Parties in same Company
Add document version   authorized Unit document context
Delete                 only an empty draft Unit; otherwise inactive action
```

---

## 11. Notifications

Use notifications only for meaningful operations, always after commit:

| Event | Recipients | Notes |
|---|---|---|
| Actual Unit created from plan | active Project members + actor | Includes planned/actual mapping |
| Unit becomes physically ready | responsible Project members + actor | It is not a commercial/listing notification; do not send for generic metadata edits |
| Unit becomes inactive | responsible Project members + actor | Include reason |
| Unit ownership replaced | actor + relevant same-company owners/project members | Legal-significance event |
| Document version added | actor only by default | Escalate only in later workflow if required |

Reuse existing notification architecture and `DB::afterCommit`; do not notify
all Company users and do not create a second notification channel.

---

## 12. Performance and database compatibility

Use targeted eager loading/counts in Unit list/detail pages:

```text
property, project, plannedUnit.floor.building,
latest status history, active ownerships, document count, primary image
```

Do not globally eager-load deep history. Paginate Unit inventory and use query
filters for Company, Property, Project, physical status, derived tenancy, type,
source/planned state.

Required indexes:

```text
units: index(company_id, status), index(project_id, status), unique(planned_unit_id)
unit_features: unique(unit_id, feature_key)
unit_status_histories: index(unit_id, changed_at), index(company_id, to_status)
unit_ownerships: index(unit_id, end_date), index(company_id, party_id)
```

Use Laravel Schema Builder/Eloquent only. Migrations, unique-null behaviour,
altered status column, FK/index names, locks, decimal values, and nullable
unique `planned_unit_id` must be verified against both MySQL and PostgreSQL.

---

## 13. Automated tests

Add a dedicated `UnitSetupWorkflowTest` at minimum.

```text
Existing legacy Unit remains usable with planned_unit_id null
manual actual Unit creation inherits/validates Property Company
Super Admin requires valid parent Company context and cannot inject another Company
cross-company Property/Project/Planned Unit/Party/Document rejection
planned-to-actual conversion copies only approved initial data
planned specifications remain unchanged and visible as planning history
one Planned Unit converts to at most one actual Unit, including concurrent attempt protection
Project/Property/Planned Unit consistency validation
actual area/type/features remain independently editable after conversion
canonical yes/no/number/select features and custom UnitFeature coexist
legacy status cutover maps every old value to the final physical status without retaining synthetic compatibility history
physical transition happy path, invalid transition, inactive reason, status history, and unauthorized action
active Lease derives tenancy without mutating physical Unit status; lease termination does the same
legacy checkout/listing/API paths no longer read `available`/`occupied` from Unit status and have contract coverage
occupancy/financial reporting derives occupied count from active Leases on both engines
ownership replacement requires 100%, preserves prior history, rejects cross-company Party
Unit document/version inherits Company and rejects cross-company injection
historical Unit cannot be raw-deleted; empty draft can be deleted
policy / Shield permission visibility and server-side authorization
notifications fire only after commit where applicable
```

Run on both isolated databases:

```text
MySQL:      realState_test
PostgreSQL: realstate_pg_test (port 6000)
```

Run targeted tests and full suites on both before declaring Phase 06 complete.

### 13.1 Required UI and real-workflow coverage

Service and policy tests are necessary but do not prove that staff can finish
the workflow in Filament. Every phase with a Filament workflow must add both
of the following beside its domain tests:

1. **Browser-like Filament/Livewire test** — mount the actual Create/Edit/List
   page or relation manager; assert critical fields exist, workflow fields that
   must not be mass-edited are disabled/hidden, submit the real form/action,
   and assert the persisted record and Filament validation result.
2. **End-to-end business-workflow test** — perform the realistic staff sequence
   through those UI actions, then assert the final domain state, history,
   authorization boundary, and important side effects. Do not merely call a
   service method and call this UI coverage.

For Unit Setup the minimum browser-like sequence is:

```text
Create Unit form → required physical fields visible → status locked as draft
→ save actual Unit → Units list Change status action → ready
→ Status History record exists → Edit form updates physical data
while status remains locked and ready.
```

Add a regression test whenever a manual UI test uncovers a broken field,
action, reactive selector, relation-manager tab, or notification. The manual
test guide remains valuable for visual polish, but it does not replace these
automated page/action tests.

---

## 14. Definition of done

```text
Existing units evolved; no second Unit/Feature/Document system exists
Manual and planned-origin actual Units work with nullable traceability
Planning data remains historical and actual Unit data is independently editable
Feature UX is fast, controlled, and still supports custom values
Physical status and ownership histories are explicit, protected, and tenant-isolated
Documents use existing logical Document + immutable Version infrastructure
Top-level/Project/Property Unit workflows are clear, reactive, and permission-safe
Existing leases, images, maintenance, ratings, API consumers, and legacy units are preserved while no longer making commercial decisions from a physical Unit status
MySQL and PostgreSQL migrations plus Phase tests/full suites pass
Implementation report documents the actual cutover and release commands
```

## 15. Handoff

Phase 06 produces actual, traceable physical inventory. It is the foundation
for later listings, reservations, contracts, payment schedules, handover, and
reporting, but it does not implement them.
