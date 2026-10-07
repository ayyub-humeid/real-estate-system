# EXECUTION PROMPT — Phase 05: Construction Execution, Progress & Site Control

## Mission

Implement **Phase 05 only** according to the approved specification.

Do not start Phase 06, Unit Setup, procurement, contracts, sales, handover, or
project closing.

## Mandatory reading

Read, in order:

```text
real-estate-planning/README.md
real-estate-planning/00-domain-rules-and-global-conventions.md
real-estate-planning/00-existing-system-gap-analysis.md
real-estate-planning/day-1/01-property-acquisition/IMPLEMENTATION-REPORT.md
real-estate-planning/day-1/02-project-planning/IMPLEMENTATION-REPORT.md
real-estate-planning/day-1/03-design-engineering/IMPLEMENTATION-REPORT.md
real-estate-planning/day-1/04-budgeting/IMPLEMENTATION-REPORT.md
real-estate-planning/day-2/05-construction/SPEC.md
```

If a prerequisite report is missing, stop and report it. Inspect the actual
repository after reading; planning files are the approved target, not a
substitute for repository inspection.

## Scope

Implement/adapt only:

```text
Project Construction
Construction Work Packages
Construction Work Package Tasks
immutable Progress Updates + controlled correction
Inspection history
Issues
Delays
derived progress
completion workflows
Project-context Filament workspace
Policies + Shield + seeded custom permissions
after-commit notifications
tests and implementation report
```

## Non-negotiable boundaries

```text
Construction progress is not Actual Cost and is not Payment.
Do not create construction payments, expenses, commitments, vendors, contracts,
procurement, labour, inventory, equipment, or BOQ systems.
```

Reuse Phase 04 `BudgetLine` exactly. A Work Package may have nullable
`budget_line_id`; it must never be pinned to a version-specific `BudgetItem`.
Never create a one-to-one `financial_commitment_id` on Work Package. Do not
re-point packages when budget versions change.

## Required repository inspection

Before implementation inspect:

```text
projects and Phase 02 lifecycle/service
Project members, buildings, and planned units
Parties and company isolation
BudgetLine, BudgetItem revision cloning, FinancialCommitment, ActualCost, Payment
DesignEngineeringService and BudgetingService workflow patterns
HasCompany, CompanyScope, policy helpers, Super Admin context
Filament Project relation managers and Details/tooltip/error-notification patterns
Shield resource naming, RolesAndPermissionsSeeder, company role backfill
ProjectWorkflowNotification and DB::afterCommit conventions
Phase 03/04 workflow tests and dual-database configuration
```

Confirm the Phase 04 BudgetLine behaviour in actual source before adding any
migration.

## Domain and workflow rules

Implement the exact hierarchy and rules in the SPEC:

```text
Project → one ProjectConstruction → Work Packages → Tasks
Task → Progress Update history / Inspection history
Work Package → Issues / Delays
```

Do not make workflow statuses user-editable in normal forms or mass-assignable
inputs. Use a dedicated `ConstructionService`, explicit policy abilities,
transactional workflow actions, row locking when recalculating progress or
completion conditions, and `DB::afterCommit` for persistent notifications.

Implement the specified lifecycle rules for Construction, Work Package, Task,
Inspection, Issue, and Delay. Progress must be an immutable timeline with
controlled correction; completion must validate all conditions server-side.

## Tenancy / Super Admin

Reuse `HasCompany` and the existing global scope. Every child must inherit
company from its parent in the service and in both required relation-manager
mutation hooks. Never rely on `HasCompany` auto-injection for Super Admin.

Validate all company/project/package/task/party/BudgetLine/user associations
server-side. A missing context must result in a clear validation message—not a
SQL `company_id` default error.

## Authorization / permissions

Create a hidden `ProjectConstructionResource` so Shield generates standard
top-level resource permissions. Use the exact generated names in its policy.

Create policies for every Phase 05 child model. Seed the custom underscore
relation/workflow permissions listed in the SPEC and verify they appear in the
existing Roles UI. Do not use guessed `::` names for child records.

Run Shield generation for the new top-level resource, seed permissions, and
clear Laravel/Filament caches before manual verification.

## Filament

Make **Project → Construction** the normal workspace. Reuse the established
Planning Structure / Design Packages / Finance patterns:

```text
grouped icon actions with tooltips
Details modals/slide-overs with tabs or sections
readable labels instead of raw IDs
clear empty states and next actions
status/severity badge colors following global semantic mapping
reactive state/table refresh—no unnecessary full-page reload
clear success and error notifications for every user mutation
```

Do not expose generic edit/delete actions for immutable history (progress,
inspection, recorded delay) or completed/cancelled records. Use corrective or
state-specific actions where the SPEC requires them.

## Tests

Add Phase 05 workflow tests at least for every case listed in the SPEC:

```text
happy path
valid/invalid/unauthorized transitions
direct status bypass rejection
company/project/cross-company validation
Super Admin parent inheritance and missing context
BudgetLine stability across revision
progress history/correction/calculation
inspection gates and failed-history preservation
issues and delays at package/task levels
completion blockers
no financial auto-write
notifications after commit
```

Run targeted and full tests on both safe databases:

```text
MySQL      realState_test
PostgreSQL realstate_pg_test (port 6000)
```

Do not weaken a valid existing test to obtain green output. Report unrelated
pre-existing failures clearly.

## Completion

Create:

```text
real-estate-planning/day-2/05-construction/IMPLEMENTATION-REPORT.md
```

Use the planning template and record actual schema, workflow, permission,
Filament, notification, MySQL, PostgreSQL, and full-suite outcomes.

Then STOP. Do not begin Phase 06 without developer approval.
