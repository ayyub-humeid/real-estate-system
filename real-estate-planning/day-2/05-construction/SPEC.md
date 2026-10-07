# 05 — Construction Execution, Progress & Site Control

> **Phase:** Construction  
> **Depends on:** Phase 02 Project Planning, Phase 03 Design / Engineering, and Phase 04 Budgeting & Financial Control  
> **Goal:** Manage what is physically executed on a project site—packages, tasks, progress, inspections, issues, delays, and controlled completion—without creating a second financial system.

---

## 1. Purpose and boundaries

This phase answers:

```text
What site work is being executed?
Which party is responsible for that work?
What has physically been completed?
Which required inspections passed or failed?
Which critical issues or schedule delays remain?
May the package or overall construction be completed?
```

The aggregate is deliberately separate from Phase 04:

```text
Construction = physical execution and site control.
Financial Control = planned, committed, actual, and paid money.
```

Therefore this phase MUST NOT add construction payments, construction expenses,
vendor accounting, procurement, a BOQ engine, inventory, equipment, labour
management, subcontractor accounting, or automatic financial transactions.

```text
Task progress = 70%
does NOT imply Actual Cost = 70%
does NOT imply Paid = 70%
```

Phase 05 is an additive domain. Repository inspection confirmed that no current
Construction aggregate, work package, task, inspection, issue, progress, or
delay tables exist.

---

## 2. Existing-system mapping

| Approved concept | Existing implementation | Phase 05 action |
|---|---|---|
| Project | `projects`, Project Planning service/lifecycle | REUSE + ADD RELATIONSHIP |
| Project members | `project_members` | REUSE for safe internal notifications/assignees |
| Parties | Phase 01 `parties` | REUSE for contractors and external inspectors |
| Budget Line | Phase 04 `budget_lines` | REUSE exactly; do not create a second budget reference |
| Budget Item | version-specific `budget_items` | REUSE only for displaying the current line label; do not FK a package to it |
| Financial commitments / actual costs / payments | Phase 04 | REUSE as separate financial records; no automatic writes from Construction |
| Design packages / approvals | Phase 03 | REUSE as upstream context only; no mandatory FK in this phase |
| Company scope / Super Admin context | `HasCompany`, `CompanyScope`, policy helpers | REUSE |
| Notifications | `ProjectWorkflowNotification` + `DB::afterCommit` | REUSE + EXTEND |
| Policies / Shield / Roles UI | existing Shield + Spatie architecture | REUSE + EXTEND |

### Verified Phase 04 `BudgetLine` contract

`budget_lines` is already the stable project-level logical financial identity:

```text
BudgetLine
  ├── Budget Item in V1
  ├── cloned Budget Item in V2
  └── cloned Budget Item in V3
```

When a budget is revised, `BudgetingService::createRevision()` creates new
`BudgetItem` snapshots while retaining the same `budget_line_id`. Existing
Financial Commitments and Actual Costs retain their original `budget_item_id`
snapshot and their stable `budget_line_id`; they are never re-pointed.

Construction Work Packages MUST use nullable `budget_line_id`, never
`budget_item_id`. The current Budget Item is a reporting/UI lookup through the
line, not Construction's stored truth.

For display, resolve the line's Budget Item from the current approved budget.
If a newer version deliberately omits that line, retain the Work Package link
and display its last-known historical item label together with a clear
`Retired from current budget` indicator. Never clear, remap, or fabricate a
new Budget Item merely to make the label appear.

---

## 3. Core hierarchy

```text
Project
  └── Project Construction executions (history-preserving; one active execution at most)
       └── Construction Work Package (many)
            ├── Work Package Task (many)
            │    ├── Progress Update history (many)
            │    └── Inspection history (many)
            ├── Construction Issue (many; task optional)
            └── Construction Delay history (many; task optional)
```

Important separations:

```text
Project Construction ≠ Project lifecycle
Work Package ≠ Financial Commitment
Work Package ≠ Budget Category
Budget Line ≠ Budget Item version
Task completion ≠ progress reaching 100%
Inspection ≠ Issue
Issue ≠ Delay
Progress correction ≠ overwriting progress history
```

---

## 4. Naming decisions

The existing project uses fully descriptive table/model names such as
`project_design_packages`, `project_building_floors`, and
`design_package_scope_items`. Phase 05 will therefore use:

```text
ProjectConstruction                 / project_constructions
ConstructionWorkPackage             / construction_work_packages
ConstructionWorkPackageTask         / construction_work_package_tasks
ConstructionProgressUpdate          / construction_progress_updates
ConstructionInspection              / construction_inspections
ConstructionIssue                   / construction_issues
ConstructionDelay                   / construction_delays
```

`ConstructionWorkPackageTask` is intentionally more explicit than the generic
`ConstructionTask`: every task belongs to a Work Package and the database name
should make that ownership obvious.

---

## 5. Entity: Project Construction

### Table

```text
project_constructions
```

### Relationship and cardinality

```text
Project hasOne ProjectConstruction
ProjectConstruction belongsTo Project
```

Construction is an execution cycle. A Project may retain many historical
cycles, but may have only one active cycle (`planned` or `in_progress`) at a
time. Terminal `cancelled` and `completed` cycles remain immutable history and
allow a later execution to be created.

The database enforces unique `(project_id, execution_number)`. Because MySQL
does not offer a portable partial unique index for active statuses, the service
MUST lock the Project parent row and existing cycles in one transaction before
checking and creating an active cycle; this concurrency rule is mandatory.

### Fields

| Field | Type | Requirement | Notes |
|---|---|---|---|
| id | PK | required | |
| company_id | FK | required | inherited from Project |
| project_id | FK | required | parent Project |
| execution_number | unsigned integer | required, sequential per Project | immutable execution-cycle number |
| status | string | required | workflow-controlled |
| planned_start_date | date | nullable | future/past planning date permitted |
| expected_completion_date | date | nullable | must not precede planned start |
| actual_start_date | date | nullable | system-set on Start; never future-dated |
| actual_completion_date | date | nullable | system-set on Complete; never future-dated |
| manager_party_id | FK parties | nullable | external/project-management party when applicable |
| progress_percentage | decimal(5,2) | required, derived | cache only; never user-editable |
| notes | text | nullable | |
| started_by | FK users | nullable | audit |
| completed_by | FK users | nullable | audit |
| timestamps | timestamps | required | |

`progress_percentage` is a derived cache maintained only by the Construction
service after task/package changes. It MUST be excluded from ordinary form
input and mass-assignment.

### Lifecycle

```text
planned → in_progress → completed
       └──────────────→ cancelled
in_progress ──────────→ cancelled
```

- A construction plan may be created only for an `approved` or `in_progress`
  Project. This permits preparation without pretending site work has started.
- **Start Construction** requires the Project itself to be `in_progress`.
  It sets `actual_start_date` if absent.
- **Complete Construction** is explicit and does **not** automatically change
  the Project to `completed` or `closed`. Project lifecycle remains governed by
  Phase 02.
- **Cancel Construction** is an explicit, authorized terminal action requiring
  a reason; it preserves all history.

---

## 6. Entity: Construction Work Package

### Table

```text
construction_work_packages
```

### Purpose

Major execution grouping, for example:

```text
Structural Works
Electrical Works
Plumbing Works
Finishing Works
```

### Fields

| Field | Type | Requirement | Notes |
|---|---|---|---|
| id | PK | required | |
| company_id | FK | required | inherited from Construction |
| project_construction_id | FK | required | parent Construction |
| budget_line_id | FK budget_lines | nullable | stable logical Phase 04 line only |
| responsible_party_id | FK parties | nullable | contractor/responsible external Party |
| name | string | required | |
| code | string | nullable | readable reference, not a version identity |
| description | text | nullable | |
| status | string | required | workflow-controlled |
| planned_start_date / planned_end_date | date | nullable | end cannot precede start |
| actual_start_date / actual_end_date | date | nullable | system-set lifecycle dates; no future dates |
| progress_percentage | decimal(5,2) | required, derived | cache from active tasks only |
| notes | text | nullable | |
| timestamps | timestamps | required | |

### Package lifecycle

```text
planned → in_progress → completed
       └──────────────→ cancelled
in_progress ──────────→ cancelled
```

Rules:

- Start requires parent Construction `in_progress`.
- A Package can be completed only when it has at least one non-cancelled task,
  every non-cancelled task is `completed`, and it has no open/in-progress
  **critical** Issue.
- Package completion forces the derived Package progress to `100.00` only after
  those conditions are met.
- Cancelled Packages are retained and excluded from Construction progress and
  completion denominators. They cannot be restarted through generic edit.

### Budget integration rule

`budget_line_id` is optional because internal work can have no direct line and
one package may span several commercial obligations. When supplied, the service
must prove that the Budget Line has the same `company_id` and `project_id` as
the parent Construction's Project.

No `financial_commitment_id` belongs on this table. One package can have zero,
one, or many eventual obligations. If a future workflow needs traceability,
Phase 04's allow-listed `FinancialCommitment.source_type/source_id` can be
extended to include `ConstructionWorkPackage`; do not add a bridge table or
competing `contract_id` path in Phase 05 without a real use case.

---

## 7. Entity: Construction Work Package Task

### Table

```text
construction_work_package_tasks
```

### Fields

| Field | Type | Requirement | Notes |
|---|---|---|---|
| id | PK | required | |
| company_id | FK | required | inherited from Package |
| construction_work_package_id | FK | required | parent Package |
| assigned_party_id | FK parties | nullable | contractor/team responsible for execution |
| name | string | required | |
| description | text | nullable | |
| status | string | required | workflow-controlled |
| planned_start_date / planned_end_date | date | nullable | end cannot precede start |
| actual_start_date / actual_end_date | date | nullable | lifecycle-set, no future dates |
| progress_percentage | decimal(5,2) | required, derived | current effective Progress Update |
| requires_inspection | boolean | required, default false | |
| notes | text | nullable | |
| timestamps | timestamps | required | |

### Task lifecycle

```text
planned → in_progress → completed
                    └→ awaiting_inspection → completed
planned / in_progress / awaiting_inspection → cancelled
```

Rules:

1. **Start Task** requires an in-progress Package and sets
   `actual_start_date` if absent.
2. Progress is recorded through immutable Progress Updates, never by editing
   the Task percentage directly.
3. A non-inspected Task at effective `100%` remains `in_progress` until an
   explicit **Complete Task** action.
4. When an inspection-required Task reaches effective `100%`, the service moves
   it to `awaiting_inspection`; it is not complete yet.
5. Complete Task requires effective `100%` and, where required, a qualifying
   inspection result. It sets `actual_end_date` and fixes derived progress at
   `100.00`.
6. Completed and cancelled Tasks cannot be edited, restarted, or given normal
   Progress Updates.

---

## 8. Entity: Construction Progress Update

### Table

```text
construction_progress_updates
```

### Fields

| Field | Type | Requirement | Notes |
|---|---|---|---|
| id | PK | required | immutable sequence identity |
| company_id | FK | required | inherited from Task |
| construction_work_package_task_id | FK | required | parent Task |
| progress_percentage | decimal(5,2) | required | inclusive range 0.00–100.00 |
| reported_at | date | required | today or past only |
| notes | text | nullable | |
| reported_by | FK users | nullable | acting/audit user |
| corrects_progress_update_id | FK self | nullable | only for controlled correction |
| correction_reason | text | nullable | required when correcting |
| timestamps | timestamps | required | |

### Immutable history and controlled correction

No generic update/delete action is permitted for a submitted Progress Update.
If the current effective update is wrong, **Correct Progress Update** creates a
new immutable row linked by `corrects_progress_update_id`; the incorrect row
remains visible as superseded in the timeline.

To keep the meaning deterministic and prevent retroactive ambiguity:

- a correction may target only the current effective update;
- it may replace it with a lower, equal, or higher absolute percentage;
- normal new updates may only maintain/increase current progress;
- a correction below `100%` moves an inspection-required task from
  `awaiting_inspection` back to `in_progress`;
- corrections and normal updates are rejected for completed/cancelled tasks.

The Task's cached percentage is the latest effective immutable update in this
sequence—not a sum of entries.

---

## 9. Entity: Construction Inspection

### Table

```text
construction_inspections
```

### Fields

| Field | Type | Requirement | Notes |
|---|---|---|---|
| id | PK | required | history identity |
| company_id | FK | required | inherited from Task |
| construction_work_package_task_id | FK | required | inspected Task |
| inspector_party_id | FK parties | nullable | external inspecting organization/person |
| inspection_date | date | required | today or past only |
| result | string | required | workflow result |
| notes | text | nullable | |
| recorded_by | FK users | nullable | authenticated internal audit actor |
| timestamps | timestamps | required | |

Allowed results:

```text
passed
failed
passed_with_notes
```

`passed_with_notes` is explicitly **non-blocking**. If a note needs correction
before task completion, the inspector must use `failed`, and optionally create
an explicit Issue. This removes ambiguous “conditionally passed” behavior.

Rules:

- Inspection creation requires Task `awaiting_inspection` and effective
  progress `100.00`.
- Multiple inspections are valid and preserved; failed Inspection #1 is never
  overwritten by passed Inspection #2.
- A failed inspection leaves the Task incomplete and awaiting inspection. The
  user may record a progress correction/rework, add an Issue explicitly, or
  record another inspection after remediation.
- A qualifying result for Complete Task is the latest inspection being
  `passed` or `passed_with_notes`.
- Inspection records are historical and are not generic-editable/deletable.

The external Inspector Party, when selected, must belong to the same company;
the `recorded_by` User records who operated the system. This handles both an
internal inspector and an external inspection organization without creating a
duplicate inspector domain.

---

## 10. Entity: Construction Issue

### Table

```text
construction_issues
```

### Relationship

```text
Work Package = required
Task = nullable
```

Examples:

```text
Structural Works → contractor manpower shortage
Structural Works → Foundation Concrete → crack detected
```

### Fields

| Field | Type | Requirement | Notes |
|---|---|---|---|
| id | PK | required | |
| company_id | FK | required | inherited from Package |
| construction_work_package_id | FK | required | affected Package |
| construction_work_package_task_id | FK | nullable | must belong to selected Package |
| assigned_to_user_id | FK users | nullable | internal follow-up owner; active same-project member only |
| title | string | required | |
| description | text | required | |
| severity | string | required | low, medium, high, critical |
| status | string | required | workflow-controlled |
| opened_at | date | required | today or past only |
| resolved_at | date | nullable | system-set, no future date |
| resolved_by | FK users | nullable | audit |
| resolution_notes | text | nullable | required on resolve |
| timestamps | timestamps | required | |

### Issue lifecycle

```text
open → in_progress → resolved → closed
```

- Start work, resolve, and close are explicit service actions; status is never
  a generic form field.
- Resolve requires resolution notes and records actor/date.
- Close requires `resolved` first.
- Reopening a closed Issue is out of scope; create a new Issue if a new problem
  emerges, preserving the original record.
- `critical` + `open`/`in_progress` on a non-cancelled Package blocks Package
  completion and Construction completion. Low, medium, and high Issues remain
  visible but do not automatically block those transitions.
- Failed Inspections do **not** auto-create Issues. Creating one is an explicit
  user action so the product never manufactures a misleading defect record.

---

## 11. Entity: Construction Delay

### Table

```text
construction_delays
```

### Relationship

```text
Work Package = required
Task = nullable
```

### Fields

| Field | Type | Requirement | Notes |
|---|---|---|---|
| id | PK | required | immutable schedule-impact event |
| company_id | FK | required | inherited from Package |
| construction_work_package_id | FK | required | affected Package |
| construction_work_package_task_id | FK | nullable | must belong to selected Package |
| baseline_end_date | date | required | server-snapshotted prior forecast |
| revised_end_date | date | required | must be later than baseline |
| reason_code | string | required | classification below |
| description | text | nullable | |
| reported_at | date | required | today or past only |
| reported_by | FK users | nullable | audit |
| timestamps | timestamps | required | |

Allowed `reason_code` values:

```text
material
contractor
weather
design_change
inspection
authority
other
```

### Delay calculation and history rule

`delay_days` is **derived**, never writable or stored independently:

```text
revised_end_date − baseline_end_date
```

When recording a Delay, the service determines `baseline_end_date` server-side:

```text
latest recorded revised_end_date for that Task/Package
or, if none, that Task/Package planned_end_date
```

If no baseline schedule exists, the Delay action rejects the request and tells
the user to set the planned end date first. `revised_end_date` must be later
than the server-derived baseline.

Delay rows are immutable historical events. A later schedule slip creates a new
Delay using the prior revised date as its baseline; it never rewrites an older
Delay or the original planned dates.

---

## 12. Exact progress calculation

Phase 05 uses understandable equal weighting. It does **not** introduce task
or package weights yet.

```text
Effective Progress Update
  → Task progress_percentage
  → Work Package progress_percentage
  → Project Construction progress_percentage
```

### Task

```text
Task progress = progress of latest effective immutable Progress Update
No effective update = 0.00
Completed Task = 100.00
Cancelled Task = excluded from parent denominator
```

### Work Package

```text
Work Package progress = average(Task progress)
for non-cancelled Tasks only.
```

- No non-cancelled Tasks: `0.00`.
- Completed Package: `100.00` after completion rules succeed.
- Cancelled Package: excluded from Construction denominator.

### Project Construction

```text
Construction progress = average(Work Package progress)
for non-cancelled Work Packages only.
```

- No non-cancelled Packages: `0.00`.
- Completed Construction: `100.00` after completion rules succeed.
- Values are recalculated in the same transaction as the relevant task/package
  mutation and saved as decimal caches for query-efficient UI/reporting.

Weights are deliberately deferred. They require business-approved weighting
rules, maintenance UI, and consistency rules; equal-weight execution groups
are correct and explainable for the approved Phase 05 scope.

---

## 13. Completion rules

### Complete Task

```text
effective progress = 100%
AND task is not cancelled/completed
AND if requires_inspection: latest inspection is passed/passed_with_notes
→ complete task
```

### Complete Work Package

```text
at least one active task
AND every active task is completed
AND no active critical issue is open/in_progress
→ complete package
```

### Complete Construction

```text
at least one active work package
AND every active work package is completed
AND no active critical issue is open/in_progress anywhere in Construction
→ complete construction
```

All three transitions are explicit service methods, protected by a dedicated
policy ability, a transaction, and after-commit notification. No generic
status edit, direct update endpoint, relation-manager payload, or mass
assignment may bypass these conditions.

---

## 14. Company isolation and Super Admin rules

All seven Phase 05 models are company-owned and reuse the existing `HasCompany`
trait / `CompanyScope`. Do not introduce another tenancy mechanism.

### Creation context

- A top-level `ProjectConstruction` created outside a Project relation context
  follows the global Super Admin rule: select a Project, then the service
  derives `company_id` from that Project. No child Company selector appears.
- Project View/Construction workspace creates Construction from its Project;
  company is inherited server-side.
- Work Package inherits from Construction; Task inherits from Package;
  Progress/Inspection inherits from Task; Issue/Delay inherits from Package.
- Every standard relation-manager CreateAction must set company in both
  `mutateFormDataUsing()` and `mutateFormDataBeforeCreate()`. Custom actions
  must derive it in `ConstructionService`; never use user-supplied company IDs.

### Server-side association checks

The service must reject:

```text
cross-company Project / Construction / Package / Task combinations
Task attached to another Package
Issue/Delay Task that does not belong to selected Package
BudgetLine from another Project or Company
Party from another Company
external inspector from another Company
assigned internal User who is not an active Project member of this Project
normal-user attempts to inject another company_id
```

Super Admin may access company records through the existing privileged path,
but every child still takes its company from the selected parent aggregate.
Missing context must return a clear domain validation error, never a database
`company_id has no default value` error.

---

## 15. Authorization and Shield

### Top-level resource

Create a hidden `ProjectConstructionResource` so Shield owns its standard
resource permissions while normal work remains in **Project → Construction**:

```text
view_any_project::construction
view_project::construction
create_project::construction
update_project::construction
delete_project::construction
... standard generated Shield abilities
```

The resource must not provide a generic status control or a raw delete path for
started/completed/cancelled construction history. Standard CRUD policy methods
must remain state-aware.

### Child and workflow permissions

These are custom underscore permissions, seeded and visible in the existing
Roles UI—not guessed Shield resource names:

```text
view_any_construction_work_package
view_construction_work_package
create_construction_work_package
update_construction_work_package
delete_construction_work_package

view_any_construction_work_package_task
view_construction_work_package_task
create_construction_work_package_task
update_construction_work_package_task
delete_construction_work_package_task

view_any_construction_progress_update
view_construction_progress_update
create_construction_progress_update
correct_construction_progress_update

view_any_construction_inspection
view_construction_inspection
create_construction_inspection

view_any_construction_issue
view_construction_issue
create_construction_issue
update_construction_issue
start_construction_issue
resolve_construction_issue
close_construction_issue

view_any_construction_delay
view_construction_delay
create_construction_delay

start_project_construction
complete_project_construction
cancel_project_construction
start_construction_work_package
complete_construction_work_package
cancel_construction_work_package
start_construction_work_package_task
complete_construction_work_package_task
cancel_construction_work_package_task
```

Exact policies are required for every model. Workflow permissions are separate
from generic update permissions. Policy checks combine permission + company +
record context + valid state; Filament visibility is never the security rule.

Before closing implementation: run Shield generation for the top-level resource,
seed custom permissions, clear caches, and verify actual database permission
names and Roles UI visibility as required by `AGENTS.md`.

---

## 16. Filament UX

The primary experience is contextual, not a sidebar full of children:

```text
Project View
  └── Construction
       ├── Overall lifecycle / progress summary
       ├── Work Packages
       │    └── Tasks
       │         ├── Progress timeline
       │         └── Inspection history
       ├── Issues
       └── Delays
```

### Required interaction design

- Reuse the organized Project **Planning Structure**, **Design Packages**, and
  Phase 04 finance relation-manager patterns: grouped icon actions, meaningful
  tooltips, responsive modals/slide-overs, Details infolists, and live table
  refreshes without full-page reloads.
- The Construction workspace must show an empty-state explanation and the next
  valid user action, not an empty table with no guidance.
- Package Details shows task status/progress, responsible Party, stable Budget
  Line's current readable Budget Item label (when any), open critical issues,
  delay count, and completion conditions.
- Task Details uses tabs/sections for information, immutable Progress timeline,
  Inspection history, related Issues, and related Delays. Do not display raw
  foreign keys such as `budget_line_id` or `task_id`; use labels and readable
  related values.
- Details must surface history generated by workflow actions; an action working
  in the database but not visible to users is incomplete UX.
- Relation selectors display only candidates valid for the parent Company,
  Project, and current state. The service repeats validation.
- Every expected `ValidationException` or `AuthorizationException` from a
  modal action becomes a clear Filament danger notification; every successful
  mutation gets a concise success notification. Never silently fail.
- Use the shared semantic status colors:

```text
planned/draft/cancelled             → gray (cancelled may use muted danger where prominent)
in_progress/active                 → info / blue
awaiting_inspection                 → warning / amber
completed/passed/resolved/closed    → success / green
failed/critical/blocked             → danger / red
```

Use a single reusable mapping/helper where practical; do not invent different
colors in every relation manager.

---

## 17. Notifications

Reuse `ProjectWorkflowNotification` and dispatch only after successful
transaction commit. Recipients are the active same-company Project members plus
the actor where appropriate; never all tenant users by default.

| Trigger | Recipient intent | Notes |
|---|---|---|
| Construction started | project members | major stage event |
| Work Package started/completed | project members | meaningful execution milestone |
| Critical Issue created | project members + assigned internal owner | high priority |
| Inspection failed | project members + task/package responsible users | high priority; no automatic duplicate issue notification |
| Delay recorded | project members | schedule-impact event |
| Construction completed | project members | major lifecycle event |

Do not send persistent notifications for every routine Progress Update.
Filament success toasts for the acting user remain separate from persistent
domain notifications.

---

## 18. Database and migration rules

Use portable Laravel Schema Builder/Eloquent only. Both PostgreSQL and MySQL
are mandatory targets.

### Required constraints/indexes

```text
project_constructions: unique(project_id), index(company_id, status)
construction_work_packages: index(project_construction_id, status), index(budget_line_id), index(responsible_party_id)
construction_work_package_tasks: index(construction_work_package_id, status), index(assigned_party_id)
construction_progress_updates: index(task_id, id), index(task_id, reported_at)
construction_inspections: index(task_id, inspection_date), index(task_id, result)
construction_issues: index(package_id, status), index(task_id, status), index(severity, status), index(assigned_to_user_id)
construction_delays: index(package_id, reported_at), index(task_id, reported_at)
```

Use explicitly short FK/index names where generated MySQL identifiers might
exceed its 64-character limit. Use portable strings for statuses/reason codes;
enforce allowed values in the service/model validation rather than introducing
engine-specific enum/CHECK behavior.

Dates representing completed historical events must be today/past only:

```text
actual start/end
progress reported_at
inspection_date
issue opened_at/resolved_at
delay reported_at
```

Planning/forecast dates may be future. All date-order rules must be enforced in
both Filament form UX and server-side service validation.

---

## 19. Service and transaction architecture

Create one focused `ConstructionService`, analogous to
`DesignEngineeringService` and `BudgetingService`. It is the only path for
workflow transitions, progress recalculation, relationship validation, and
after-commit notifications.

Required service action groups:

```text
create/update construction metadata
create/update work package metadata
create/update task metadata
start/cancel/complete construction
start/cancel/complete package
start/cancel/complete task
record/correct progress
record inspection
create/start/resolve/close issue
record delay
recalculate package/construction progress
```

Generic metadata update methods must whitelist structural fields and exclude
workflow statuses, derived percentages, actual lifecycle dates, and history
fields. Actions that change a child plus Package/Construction cache must use a
single DB transaction; lock the affected parent/child rows when concurrent
updates could produce lost progress or an invalid completion decision.

Notifications use `DB::afterCommit`. If a transaction fails, no workflow state,
derived cache, or persistent domain notification may leak.

---

## 20. Tests

Add a dedicated Phase 05 workflow test suite following Phase 03/04 service
tests. At minimum cover:

### Hierarchy, tenancy, and Super Admin

```text
many historical construction executions per Project; one active execution at a time
normal Company user scope
normal user cannot inject a different company_id
Super Admin parent-context creation inherits parent company
missing Super Admin top-level context fails clearly
cross-company Project/Party/BudgetLine/User rejection
Task belongs to selected Package
Issue/Delay Task belongs to selected Package
```

### Workflow and history

```text
valid construction/package/task happy path
invalid transition rejection
unauthorized transition rejection
generic status/update bypass rejection
progress history remains immutable
controlled correction preserves prior row and recalculates current progress
progress cannot exceed 100 or decrease except controlled correction
inspection-required task cannot complete without qualifying inspection
failed inspection remains historical; later pass permits completion
passed_with_notes is explicitly non-blocking
```

### Completion, issues, and delays

```text
package completion rejects no-task / incomplete-task / critical-open-issue cases
construction completion rejects incomplete package / critical-open-issue cases
cancelled Task/Package exclusion from progress denominator
package/task issue creation and lifecycle
delay baseline/revised-date calculation and immutable chained delay history
date validation for actual events versus future planning dates
```

### Financial boundary and performance

```text
same-project BudgetLine accepted; foreign line rejected
construction mutations create no commitment, Actual Cost, Payment, or allocation
current BudgetLine survives Budget revision without re-pointing Package
list/detail queries use selected eager loads/counts; no avoidable N+1 on workspace
```

### Verification gate

Run Phase 05 migrations/tests on both isolated test databases:

```text
MySQL:      realState_test
PostgreSQL: realstate_pg_test (port 6000)
```

Run the full suite after targeted tests. Report any genuinely unrelated
pre-existing failure rather than weakening it.

---

## 21. Real-world scenario

```text
Al Noor Residences Project
  Project status: in_progress

Construction is started.

Work Package: Structural Works
  Budget Line: Concrete Works (stable line, regardless of Budget V1/V2/V3)
  Contractor: BuildCo Party

Task: Foundation Concrete
  requires_inspection: yes
  05 Nov: Progress 20%
  08 Nov: Progress 50%
  11 Nov: Progress 100% → awaiting_inspection
  Inspection #1: failed (honeycombing found)
  Explicit Issue: critical, open
  Contractor corrects work; the issue is resolved
  Inspection #2: passed
  Complete Task → completed, 100%

Every Structural Task completes; no critical issue remains.
→ Complete Structural Works.

Electrical, Plumbing, and Finishing complete in the same controlled way.
→ Complete Construction.

The Project does not auto-close. Its Phase 02 lifecycle can later move from
in_progress to completed/closed through its own authorized workflow.
```

The scenario records physical truth only. A Phase 04 Financial Commitment,
Actual Cost, or Payment is entered independently when the commercial/invoice/
cash event actually occurs.

---

## 22. Definition of done

```text
company-owned, versioned Construction execution history per Project with one active execution at a time
work packages/tasks model physical execution cleanly
progress is history-preserving and derived, never independently editable
inspection-required work cannot be completed without a qualifying inspection
failed inspections, issues, delays, and corrections preserve history
completion checks are server-side and state-aware
BudgetLine integration is stable across budget revisions
Construction creates no duplicate financial domain
tenant isolation, Super Admin inheritance, Policy/Shield, Roles UI, and notifications follow existing conventions
Filament workspace is contextual, clear, reactive, and displays history
MySQL and PostgreSQL migration/tests pass
```

## 23. Handoff

Phase 05 produces construction execution history only. It becomes compatible
input for later Unit Setup, Handover, Project Closing, Contracts, and reporting
without implementing those phases now.
