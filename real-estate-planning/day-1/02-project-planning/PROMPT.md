# EXECUTION PROMPT — Phase 02: Project Creation & Planning

## Mission

Implement **Phase 02 only**.

Do not begin Design, Budgeting, Construction, Actual Unit Setup, Marketing/Sales, Contracts/Payments, or Handover.

## Mandatory Reading

Read:

```text
real-estate-planning/README.md
real-estate-planning/00-domain-rules-and-global-conventions.md
real-estate-planning/00-existing-system-gap-analysis.md
real-estate-planning/day-1/01-property-acquisition/IMPLEMENTATION-REPORT.md
real-estate-planning/day-1/02-project-planning/SPEC.md
```

If the Phase 01 report does not exist, stop.

Then inspect current code/schema.

## Scope

Implement/adapt only:

```text
Projects
Project ↔ Properties
Project Members
Project Buildings
Building Floors
Planned Units
Planned Unit Specifications
project/planning lifecycle
Filament
Policies + Shield
notifications
tests
```

Preserve:

```text
Planned Unit ≠ Actual Unit
```

Do not create actual sellable/rentable units.

## Inspection

Inspect Project migrations/models/resources, project manager/member structures, Phase 01 Property relations, existing Unit/UnitFeature architecture, Global Scope Trait, Policies/Shield, API consumers, and tests.

Search dependencies before removing legacy project fields.

## Compatibility

Reuse existing `projects`.

If `project_manager_id` exists and is used, do not remove it abruptly.

Introduce richer project-member relationships safely.

Never duplicate UnitFeature.

## Authorization

Every new model requires Policy + Shield.

Explicitly protect:

```text
approve project
cancel project
manage members
attach/detach property
approve/cancel planned unit
```

## Notifications

Implement important lifecycle events from the SPEC.

Avoid noise for routine draft building/floor/unit creation.

## Filament

Keep Project workflow coherent with relation managers/tabs/pages.

Use reactive updates for counts, badges, tables, and actions.

## Performance

Do not eager-load the whole hierarchy on project lists.

Use targeted eager loading, `withCount()`, pagination, and indexes.

# Automated Tests

At minimum cover:

### Projects
```text
tenant isolation
creation
valid transitions
invalid transitions
approval permission
cancellation permission
closed/cancelled restrictions
```

### Project Properties
```text
attach valid property
cross-tenant property denied
duplicate active association prevented where applicable
detach dependency/state rules
```

### Members
```text
same-tenant assignment
cross-tenant assignment denied
contextual role
end/removal behavior
authorization
```

### Buildings/Floors
```text
correct parent relations
floor uniqueness
tenant protection through parent context
```

### Planned Units
```text
correct floor relation
status rules
no automatic actual Unit creation
specification relation
dependency-aware destructive restrictions
```

### Policies / Notifications
```text
CRUD + custom workflow permissions
tenant/state restrictions
correct important notifications
no notification on rollback
```

For current-phase bugs, add regression tests before fixing.

## Test Execution

Run targeted tests, then:

```bash
php artisan test
```

Resolve Phase 02-caused failures.

# Completion

Create:

```text
real-estate-planning/day-1/02-project-planning/IMPLEMENTATION-REPORT.md
```

Then STOP.

Do not start Phase 03 and do not commit unless asked.
