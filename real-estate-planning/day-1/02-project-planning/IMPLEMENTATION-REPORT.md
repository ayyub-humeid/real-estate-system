# IMPLEMENTATION REPORT — Phase 02: Project Planning

## Status

```text
READY TO CLOSE — Phase 02 targeted verification passes on MySQL and PostgreSQL.
```

## Delivered

- Added the company-owned Project aggregate and planning hierarchy: Project Properties, Project Members, Buildings, Floors, Planned Units, and Planned Unit Specifications.
- Kept `ProjectPlannedUnit` separate from the existing operational `Unit` model. Phase 02 never creates actual Units.
- Added the Project Filament resource. Its Project view has complete, contextual Project Properties and Project Members detail/edit/history actions.
- The Planning Structure tab is organized around Buildings. Each building has a readable structure slide-over and grouped Floor, Planned Unit, and Specification actions, including create, view, edit, approval/cancellation where relevant, and safe deletion.
- Reused existing `HasCompany` / `CompanyScope`, Filament, Shield/Spatie permission architecture, database notifications, and dual-database test protection.

## Business Rules

- Project lifecycle: `planning → approved → in_progress → completed → closed`; cancellation is allowed from planning, approved, or in-progress.
- Approval requires at least one active Project Property.
- Closed and cancelled Projects reject structural planning changes server-side.
- Planned Unit lifecycle is `planned → approved → cancelled`; no actual Unit conversion is implemented in this phase.
- Project Properties and Project Members must belong to the Project company. Duplicate property attachment and duplicate active member assignment are rejected.
- Floors are unique per Building and planned-unit codes are unique per Floor.
- Planning child records explicitly inherit the parent company. The relation-manager action payload is also checked against the selected Building so an injected Floor, Planned Unit, or Specification from another Building is rejected.
- Properties are detached and members are ended rather than deleted, preserving project history. A Building, Floor, or Planned Unit may be deleted only after its descendants are removed. Approved/cancelled planned-unit records cannot be edited or deleted.

## Authorization

- Company-owned default roles are synchronized during the release backfill
  when new non-platform permissions are introduced. Permission classification
  uses exact tokens, so `create_project_planned_unit` is tenant-assignable and
  is never misclassified as the platform `plan` permission.

- `Project` is the Phase 02 top-level Shield resource (`view_project`, `create_project`, etc.).
- Custom relation/workflow permissions were added to the existing role seeder: project property/member/building/floor/planned-unit/specification access plus approve/cancel/close, attach/detach, member management, and planned-unit approval/cancellation.
- Service methods authorize every mutation and enforce company context even if a request bypasses Filament.

## Database Compatibility

| Engine | Test database | Clean migration | Phase 02 targeted tests |
| --- | --- | --- | --- |
| MySQL | `realState_test` | Passed | 7 tests, 24 assertions — passed |
| PostgreSQL (port 6000) | `realstate_pg_test` | Passed | 7 tests, 24 assertions — passed |

## Test Coverage

- normal company planning hierarchy and explicit no-actual-Unit assertion
- unauthorized creation
- Super Admin target-company requirement
- cross-company Property and User rejection
- inherited child company IDs
- project approval prerequisite and lifecycle restrictions
- planned-unit lifecycle
- per-Building floor uniqueness
- safe edit operations for every planning level
- dependency-aware deletion order: Specification → Planned Unit → Floor → Building

## Full Regression Note

The complete suite was run on both engines. Phase 02 passes, but the suite is not globally green because of two pre-existing `LeaseBalanceTest` failures unrelated to Project Planning:

- its generated schedule does not match the test's due-date/outstanding-balance assumption;
- `Payment::recordPayment(600, ...)` correctly rejects an amount greater than the generated due amount (`83.34`).

Those lease/payment failures were not changed in this phase.

## Deferred Intentionally

- Design, engineering, budgeting, construction, actual Unit conversion, sales, contracts, and payments remain out of scope.
- Project closing uses the approved status transition only; its detailed closing workflow belongs to the later Project Closing phase.
