# IMPLEMENTATION REPORT — Phase 06: Unit Setup

## 1. Phase Result

```text
Status: Completed
```

## 2. Repository Inspection Findings

### Reused

- Existing `units`, `unit_features`, images, leases, documents and document-version infrastructure.
- Existing `HasCompany` scope, Shield permissions, Project/Property structure, notifications and Filament resources.

### Altered

- Actual Units now have Project/Planned Unit traceability, measured-area fields, physical lifecycle metadata and history.
- `units.status` is now the sole physical lifecycle field; tenancy remains derived from active Leases.
- Lease termination no longer changes physical Unit state.

### Newly Created

- `UnitSetupService`, Unit ownership/status-history models and policies, Unit detail relation managers, Project → Actual Units workspace, and Phase 06 workflow tests.

## 3. Database Changes

### Migrations Added

- `2026_10_08_000000_restructure_units_for_actual_unit_setup`
- `2026_10_08_030000_make_units_status_the_physical_status`

### Tables / Columns Added or Altered

- `units`: Project and planned-unit links, actual area/unit, physical location, readiness/inactive metadata, and the single `status` lifecycle field.
- `unit_features.feature_key` for canonical reusable features.
- `unit_status_histories` and `unit_ownerships` for auditable history.

### Data Migration / Backfill

- Legacy values are discarded/mapped in the same column: maintenance remains `maintenance`; all other legacy values become `ready`.
- Existing `sqft` is copied only when present, as `actual_area` with `sqft` unit.

### Final status design

- The final schema contains only `units.status`; there is no `operational_status` column or compatibility field.
- The migration removes the legacy PostgreSQL `units_status_check` constraint where it exists, then changes `status` to a normal portable `VARCHAR(32)` with default `draft`.
- `App\Enums\UnitStatus` owns the four allowed values (`draft`, `ready`, `maintenance`, `inactive`), and `UnitSetupService` owns the allowed transition map.
- The final migration also removes an interim column/index only if a local pre-final Phase 06 build had already created it; a clean installation never creates that column.

## 4. Models & Relationships

- Unit belongs to optional Project and Planned Unit; Planned Unit has at most one actual Unit.
- Unit has status histories, ownership history/current owners and polymorphic Documents.
- Unit tenancy state is derived from `currentLease`, not stored as a Unit status.
- Company isolation is validated by `UnitSetupService` for Property, Project, Planned Unit, Party and Document contexts.

## 5. Policies & Filament Shield

- Unit policy now enforces Company ownership for record operations and protects deletion of historical Units.
- Added Unit Ownership and Unit Status History policies.
- Added seeded custom permissions: create from plan, change/reactivate status, change/view ownership, view status history and manage Unit documents.
- Existing Unit/UnitFeature generated Shield permissions remain the top-level permission source.

## 6. Filament / Livewire

- Units now show physical lifecycle fields and a controlled Change Status action.
- Unit Details includes Features, Ownership, Documents and Status History relation workspaces.
- Project includes an Actual Units tab with a Create from approved plan action.
- Property → Units displays physical states only.

## 7. Notifications

| Event | Recipient Strategy | Channel | Implementation |
|---|---|---|---|
| Actual Unit created from plan | active Project members and actor | database | after commit |
| Unit becomes ready/inactive | active Project members and actor, or actor for manual Unit | database | after commit |

## 8. Performance

- Added Company/Project status indexes and nullable unique planned-unit link.
- Public Unit queries and occupancy reporting use `currentLease` rather than stored commercial status.
- Unit resource retains targeted existing eager loading; histories remain relation-loaded only when viewed.

## 9. Automated Tests

### Tests Added

```text
Feature: UnitSetupWorkflowTest
Unit: UnitStatusTest updated for the single physical status
Filament: UnitSetupFilamentWorkflowTest
Policy/Auth: create, cross-company, transition and ownership checks
Regression: lease-derived tenancy preserves physical state
```

### Important Scenarios Covered

- manual and planned-origin actual Unit creation;
- one-time planned conversion and cross-company rejection;
- physical status transition audit;
- ownership total/history;
- active Lease derives occupied tenancy without changing the Unit;
- PostgreSQL legacy enum/check removal and the final portable status column.
- browser-like Filament create form, locked status field, controlled table
  action transition, status-history output, and edit-form persistence.

### Results

```text
MySQL realState_test
  migrate:fresh: PASS
  UnitSetupWorkflowTest: PASS (3 tests, 11 assertions)
  UnitSetupFilamentWorkflowTest: PASS (2 tests, 37 assertions)
  full PHPUnit suite: PASS

PostgreSQL realstate_pg_test (port 6000)
  migrate:fresh: PASS
  UnitSetupWorkflowTest: PASS (3 tests, 11 assertions)
  UnitSetupFilamentWorkflowTest: PASS (2 tests, 37 assertions)
  full PHPUnit suite: PASS
```

## 10. Manual Testing Still Required

- Create manual actual Unit from Units and Property workspaces.
- Create from an approved Planned Unit in Project → Actual Units.
- Move draft → ready/maintenance/inactive and inspect history.
- Replace ownership allocation with one or multiple same-company Parties totalling 100%.
- Add Unit document/version and inspect it from Unit Details.
- Verify an authorized role sees the custom actions and an unauthorized role does not.

## 11. Existing Behavior Changed

- `available`, `reserved` and `occupied` are no longer physical Unit statuses.
- Lease termination no longer makes a Unit physically ready automatically.
- Occupancy dashboard/report counts active Leases; public Unit API exposes `status` and `tenancy_state`.

## 12. Deferred Intentionally

- Listings, reservations, commercial availability, customer contracts, schedules and payments remain future phases.
- No compatibility status field or legacy status layer remains.

## 13. Release Commands

```bash
php artisan migrate --force
php artisan shield:generate --all --option=permissions --panel=admin --no-interaction
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan app:backfill-company-roles
php artisan optimize:clear
php artisan filament:cache-components
```
