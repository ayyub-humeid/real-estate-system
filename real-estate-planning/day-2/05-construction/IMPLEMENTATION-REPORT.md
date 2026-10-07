# Phase 05 — Construction: Implementation Report

## Delivered scope

Phase 05 adds the physical site-execution lifecycle without creating a second
financial domain:

```text
Project → Project Construction → Work Package → Task
                                      ├── Progress history / corrections
                                      ├── Inspection history
                                      ├── Issues
                                      └── Delay history
```

Construction remains deliberately separate from Phase 04. A work package may
optionally reference the stable `budget_lines` identity for reporting, but no
construction action creates or changes commitments, actual costs, payments, or
allocations.

## Database and relationships

Migration `2026_10_07_000000_create_construction_domain_tables.php` adds:

- `project_constructions` — versioned construction executions per Project;
- `construction_work_packages` — many packages per lifecycle, optionally
  linked to a Phase 04 stable Budget Line;
- `construction_work_package_tasks` — many tasks per package;
- `construction_progress_updates` — append-only task progress and correction
  lineage;
- `construction_inspections` — append-only inspection results;
- `construction_issues` — package or task issues with severity and workflow;
- `construction_delays` — append-only schedule-impact records.

All children carry `company_id`, have foreign keys and reporting indexes, and
use explicit short foreign-key names where needed so MySQL's 64-character
identifier limit and PostgreSQL both work from a clean schema.

## Workflow and rules

- Construction is planned → in progress → completed/cancelled. It can start
  only after its Project is in progress.
- Cancelled and completed executions remain history. A new execution receives
  the next immutable `execution_number`; only one planned/in-progress execution
  may exist per Project. Creation locks the Project parent row transactionally
  so concurrent requests cannot create two active executions.
- Work packages and tasks use controlled planned → in progress → completed or
  cancelled transitions. Parent completion requires active children to be
  complete.
- Task progress is append-only. Corrections create a new record pointing to
  the corrected report; they never overwrite history. Parent package and
  construction percentages are recalculated from current task state.
- A task requiring inspection moves to `awaiting_inspection` at 100%; it may
  complete only after a passed or passed-with-notes inspection.
- Critical open/in-progress issues block construction completion. Resolution
  notes are mandatory when resolving an issue.
- Delay history records the previous forecast and requires a revised end date
  later than that forecast. Future report dates are rejected.
- Service validation enforces same-company Project, Party, Project Member,
  Budget Line, Work Package, Task, Issue, and Progress Update relationships.

## Authorization, tenancy, and notifications

- Reused `HasCompany`, global scope, Company/Super Admin context, and the
  existing company-owned policy helpers.
- Added one hidden Shield top-level resource and policy for
  `ProjectConstruction`, using Shield names such as
  `view_project::construction`.
- Added policies and seeded underscore-style custom permissions for relation
  children and lifecycle actions.
- All workflow methods authorize server-side in `ConstructionService`; UI
  visibility is only a convenience.
- Workflow notifications reuse `ProjectWorkflowNotification` and are queued
  with `DB::afterCommit`, to the actor and current same-project active members.

## Filament UX

Project View now has a **Construction** tab. It has:

- a contextual Create Construction action;
- a Details modal with Overview, Work Packages, Issues, and Delays tabs;
- tooltipped grouped actions for package/task lifecycle, immutable progress,
  inspection, issues, and delays;
- constrained draft metadata edit/delete actions for Construction, Work
  Packages, and Tasks; deletion is refused once child or historical records
  exist, after which cancellation is the correct business action;
- explicit success and actionable validation/authorization notifications;
- server-side parent checks for every selected child record, preventing forged
  IDs from another construction.

No raw update/delete action is exposed for progress, inspections, delays, or
issues after their historical business event has been recorded. Corrections and
workflow transitions are used instead.

## Tests and verification

`ConstructionWorkflowTest` covers:

- normal construction/package/task completion with a required inspection;
- failed inspection and a critical issue, then controlled resolution;
- immutable progress history and correction;
- cross-company Budget Line injection rejection;
- unauthorized construction creation;
- confirmation that construction does not create financial records.
- cancelled execution history and replacement execution numbering;
- the single-active-execution rule and Super Admin parent-company inheritance.

### MySQL — `realState_test`

- clean `migrate:fresh --env=testing`: passed;
- `ConstructionWorkflowTest`: **6 passed, 23 assertions**;
- complete suite: **76 passed, 352 assertions**.

### PostgreSQL — `realstate_pg_test` (port 6000)

- clean `migrate:fresh --database=pgsql`: passed;
- `ConstructionWorkflowTest`: **6 passed, 23 assertions**;
- complete suite through `phpunit.pgsql.xml`: **76 passed, 352 assertions**.

## Deployment reminder

Before deploying Phase 05, run the existing permission/bootstrap sequence in
the target environment after migrations:

```bash
php artisan shield:generate --all --option=permissions --panel=admin --no-interaction
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan app:backfill-company-roles
php artisan optimize:clear
php artisan filament:cache-components
```

## Phase status

**READY TO CLOSE** — Phase 05 is complete. No Phase 06 work was started.
