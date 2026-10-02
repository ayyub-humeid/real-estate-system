# 02 — Project Creation & Planning

> **Phase:** Project Creation + Planning  
> **Depends on:** Phase 01 property/acquisition readiness  
> **Goal:** Create the project and model its planned physical structure without prematurely creating construction, actual units, sales, contracts, or final financial records.

---

# 1. Purpose

This phase answers:

```text
What project is the company developing?
Which properties/land belong to the project?
Who is on the project team?
What buildings are planned?
What floors exist in each building?
What units are planned on those floors?
What are the planned specifications of those units?
```

It intentionally stops before:

```text
detailed design approval
final budget
construction execution
actual operational units
marketing/sales
contracts
handover
```

---

# 2. Core Planning Hierarchy

```text
Project
  ↓
Project Properties
  ↓
Buildings
  ↓
Floors
  ↓
Planned Units
  ↓
Planned Specifications
```

Separate concept:

```text
Project Members
```

---

# 3. Important Domain Separation

```text
Project ≠ Property
Planned Unit ≠ Actual Unit
Planning ≠ Construction
Planning ≠ Budget
Project Member ≠ permanent User role
```

A project can use multiple properties.

A property can participate through `project_properties`.

Do not place acquisition type on the project.

---

# 4. Existing-System Mapping

Inspect existing:

```text
projects
properties
units
users
company scope
project managers
project statuses
Filament resources
reports
permissions
documents
```

Expected actions:

| Concept | Expected action |
|---|---|
| Projects | REUSE + ALTER |
| Project properties | VERIFY / CREATE |
| Project members | VERIFY / CREATE |
| Buildings | CREATE |
| Building floors | CREATE |
| Planned units | CREATE |
| Planned unit specifications | CREATE |
| Existing actual units | REUSE later; do not convert yet |
| Global Scope | REUSE |
| Policies / Shield | EXTEND |

---

# 5. Entity: Project

## Target Table

Reuse:

```text
projects
```

## Minimum Target Meaning

Represents one real-estate development/business project.

## Target Fields to Verify

| Field | Type | Nullability | Notes |
|---|---|---:|---|
| id | PK | required | existing convention |
| company_id | FK | existing tenant architecture | |
| name | string | required | |
| project_type | string | required/nullable according to actual business need | |
| status | string | required | |
| start_date | date | nullable | |
| expected_completion_date | date | nullable | |
| description | text | nullable | |
| approved_at | timestamp | nullable | if approval action retained |
| completed_at | timestamp | nullable | |
| closed_at | timestamp | nullable | |
| cancelled_at | timestamp | nullable | |
| cancellation_reason | text | nullable | |
| timestamps | timestamps | required | |

Do not duplicate existing valid fields.

---

# 6. Project Status Lifecycle

Approved baseline:

```text
planning
   ↓
approved
   ↓
in_progress
   ↓
completed
   ↓
closed
```

Alternative terminal state:

```text
planning / approved / in_progress
        ↓
cancelled
```

Definitions:

```text
planning:
  project is being structured.

approved:
  project is approved to proceed into operational phases.

in_progress:
  project execution is active.

completed:
  operational work is substantially complete.

closed:
  formal project closing has been completed.

cancelled:
  project stopped; history preserved.
```

Do not auto-close a project when construction reaches 100%.

Closing has its own phase.

---

# 7. Project Creation Rules

At project creation:

```text
create project identity
attach property/properties
assign initial members if available
set planning status
```

Do not automatically create:

```text
final budget
construction work packages
contractors
actual units
sales listings
customer contracts
payment schedules
```

---

# 8. Entity: Project Property

## Target Table

```text
project_properties
```

## Purpose

Many-to-many relation between projects and properties.

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_id | FK | required |
| property_id | FK | required |
| role/purpose | string | nullable |
| notes | text | nullable |
| attached_at | timestamp | required/default |
| detached_at | timestamp | nullable |
| timestamps | timestamps | required |

Use active relationship rows where `detached_at` is null if history is required.

Do not delete historical project/property association casually after execution starts.

---

# 9. Entity: Project Member

## Target Table

```text
project_members
```

## Purpose

Project-specific team assignment.

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_id | FK | required |
| user_id | FK | required |
| role | string | required |
| started_at | date/timestamp | required |
| ended_at | date/timestamp | nullable |
| notes | text | nullable |
| timestamps | timestamps | required |

Project role is contextual.

Do not permanently modify the User's global role just because they are project manager/member on one project.

---

# 10. Project Manager

Do not rely solely on:

```text
projects.project_manager_id
```

for the entire project team.

If the existing field already exists and is heavily used, it may temporarily remain for compatibility, but `project_members` becomes the richer team model.

A project manager can be represented by:

```text
project_members.role = project_manager
```

The agent should decide whether the legacy FK remains as a cached/convenience field only after dependency analysis.

---

# 11. Entity: Project Building

## Target Table

```text
project_buildings
```

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_id | FK | required |
| name | string | required |
| code | string | nullable |
| building_type | string | nullable |
| description | text | nullable |
| sort_order | integer | required/default |
| status | string | required/default `planned` |
| timestamps | timestamps | required |

Avoid storing `floors_count` as writable source of truth when it can be derived from floor rows.

---

# 12. Entity: Project Building Floor

## Target Table

```text
project_building_floors
```

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_building_id | FK | required |
| floor_number | integer | required |
| label | string | nullable |
| description | text | nullable |
| sort_order | integer | required/default |
| timestamps | timestamps | required |

Recommended convention:

```text
basement floors → negative numbers
ground floor → 0
upper floors → positive numbers
```

Use:

```text
unique(project_building_id, floor_number)
```

unless the existing business uses another floor identity.

---

# 13. Entity: Project Planned Unit

## Target Table

```text
project_planned_units
```

## Purpose

Represents the unit in planning/design before an actual operational unit exists.

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_building_floor_id | FK | required |
| code | string | required |
| unit_type | string | required |
| planned_area | decimal | nullable |
| status | string | required |
| description | text | nullable |
| sort_order | integer | required/default |
| timestamps | timestamps | required |

Optional domain fields may be added only if actually used by the project.

Do not copy every future actual-unit field into planned units.

---

# 14. Planned Unit Status

Recommended:

```text
planned
approved
converted
cancelled
```

Definitions:

```text
planned:
  editable planning record.

approved:
  accepted planning definition.

converted:
  an actual unit has been created from this planned unit later.

cancelled:
  removed from future execution but retained historically.
```

Actual conversion happens in Unit Setup, not here.

---

# 15. Entity: Planned Unit Specification

## Target Table

```text
planned_unit_specifications
```

## Purpose

Flexible specification values without hard-coding every possible design attribute as a column.

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_planned_unit_id | FK | required |
| name | string | required |
| value | string/text | required |
| unit | string | nullable |
| notes | text | nullable |
| sort_order | integer | required/default |
| timestamps | timestamps | required |

Examples:

```text
Bedrooms = 3
Balcony = Yes
Finish Level = Premium
Ceiling Height = 3.2 / m
```

Do not use this table to replace the existing actual `UnitFeature` system.

Planned specifications and actual unit features are related concepts but different lifecycle contexts.

---

# 16. Planned Unit vs Actual Unit

```text
Planned Unit
   ↓ later
Actual Unit
```

The link will later be:

```text
units.planned_unit_id nullable
```

Rules:

```text
- sales/listings use actual units.
- contracts use actual units.
- ownership uses actual units.
- handover uses actual units.
- a pre-existing actual unit may have planned_unit_id = null.
```

---

# 17. Planning Changes

While project status is `planning`, structural edits are normally allowed.

After approval:

```text
major structural changes should be explicit and auditable.
```

Do not silently delete approved buildings/floors/planned units if downstream design/construction records exist.

Later phases should restrict destructive edits through dependency checks.

---

# 18. Financial Effect

Project creation/planning has little or no direct financial effect.

Do not create actual expense/payment rows merely because:

```text
building exists
floor exists
planned unit exists
```

Budgeting begins in Phase 04.

---

# 19. Documents

Project-level planning documents may use the generic document system.

Do not build a special project-file engine.

Detailed design submissions/versioning begin in Phase 03.

---

# 20. Important Notifications

## Notification Matrix

| Trigger | Recipients | Channel | Notes |
|---|---|---|---|
| Project approved | project members + users responsible for next phases | in-app | major lifecycle event |
| Project cancelled | active project members + authorized management | in-app | include reason |
| Project member assigned | assigned user | in-app | |
| Project member removed/ended | affected user where appropriate | in-app | |
| Property attached to approved/in-progress project | project manager/responsible users | in-app | important structural change |
| Property detached from approved/in-progress project | project manager + authorized management | in-app | only after dependency validation |
| Major planning structure modified after project approval | project manager + design responsibility | in-app | avoid noise for minor text edits |
| Planned unit cancelled after approval | project/design responsible users | in-app | |

Do not notify for every floor/unit creation during ordinary draft planning.

---

# 21. Authorization / Filament Shield

Every new model requires Policy + Shield coverage.

## Models / Policies

| Model | Policy |
|---|---|
| Project | existing `ProjectPolicy` → verify/extend |
| ProjectProperty | `ProjectPropertyPolicy` |
| ProjectMember | `ProjectMemberPolicy` |
| ProjectBuilding | `ProjectBuildingPolicy` |
| ProjectBuildingFloor | `ProjectBuildingFloorPolicy` |
| ProjectPlannedUnit | `ProjectPlannedUnitPolicy` |
| PlannedUnitSpecification | `PlannedUnitSpecificationPolicy` |

## Custom Workflow Permissions

At minimum consider:

```text
approve_project
cancel_project
close_project
manage_project_members
attach_project_property
detach_project_property
approve_planned_unit
cancel_planned_unit
```

Follow current Shield naming convention.

Generic update must not automatically grant approval/cancellation.

---

# 22. Policy State Rules

Examples:

```text
closed project:
  structural edits denied.

cancelled project:
  cannot reactivate through generic update.

approved planned unit:
  destructive delete denied if downstream dependencies exist.

project member:
  cannot attach a user from another tenant.
```

---

# 23. Filament UX

Recommended navigation:

```text
Projects
  ├── Overview
  ├── Properties
  ├── Members
  ├── Buildings
  │    └── Floors
  │         └── Planned Units
  └── Planning Documents
```

Use relation managers/nested actions where practical.

Avoid requiring users to navigate through excessive standalone CRUD screens for simple child entities.

---

# 24. Reactive Filament Behavior

Examples:

```text
add building
add floor
add planned unit
assign member
attach property
approve project
```

should update:

```text
counts
badges
tables
status
available actions
```

without full page reload.

---

# 25. Eager Loading / Query Performance

Project index page should not load the entire hierarchy.

Use:

```text
withCount('buildings')
withCount('members')
withCount('properties')
```

where needed.

Project detail may deliberately load:

```text
buildings.floors
```

but only load `plannedUnits.specifications` when the UI needs them.

Avoid:

```text
Project::with('buildings.floors.plannedUnits.specifications', ...)
```

for every list request.

---

# 26. Indexes

Likely:

```text
projects(company_id, status)
project_properties(project_id, property_id)
project_members(project_id, user_id)
project_buildings(project_id, status)
project_building_floors(project_building_id, floor_number)
project_planned_units(project_building_floor_id, status)
project_planned_units(project_building_floor_id, code)
planned_unit_specifications(project_planned_unit_id)
```

Add uniqueness where business identity requires it.

---

# 27. Delete / Archive Rules

Draft planning records with no dependencies may be deleted.

After downstream dependencies exist:

```text
prefer cancel/deactivate/history
over destructive delete.
```

A project with:

```text
contracts
construction
actual units
financial data
handover
```

must never be hard-deleted casually.

---

# 28. Real-World Scenario

```text
Project: Aga Heights

Properties:
- Parcel A
- Parcel B

Buildings:
- Tower A
- Tower B

Tower A Floors:
- Basement (-1)
- Ground (0)
- Floors 1–6

Floor 2 Planned Units:
- A-201 apartment 120m²
- A-202 apartment 145m²
```

No actual `units` row needs to exist yet.

---

# 29. Tests

Minimum tests:

```text
tenant isolation
project lifecycle transitions
property attach/detach authorization
project member assignment
building/floor uniqueness
planned unit hierarchy
planned-unit status transitions
closed/cancelled project restrictions
Policy/Shield checks
important notifications
notification after commit
N+1-sensitive Filament table queries
```

---

# 30. Definition of Done

```text
existing Project model reused safely
project-property relationship works
project members work
building/floor/planned unit hierarchy works
planned specifications work
no actual units created prematurely
every new model has Policy + Shield
important lifecycle notifications exist
Filament interactions are reactive
N+1 risks are addressed
indexes are appropriate
tests pass
```

---

# 31. Handoff

Project planning becomes input for:

```text
03-design-engineering-approvals.md
```
