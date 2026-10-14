# Phase 07.4 — Activities & Follow-ups Engine

## 1. Purpose

Phase 07.4 adds the operational follow-up layer on top of the CRM built in Phase 07.3.

The goal is to let sales teams answer two different questions clearly:

1. **What should an employee do next?** → Follow-up Task
2. **What did the employee actually do, and what was the result?** → Activity

The system must stay easy to use. The core UX principle is:

> Minimum clicks and minimum duplicate data entry, while preserving accurate structured CRM data.

The key interaction is **Complete & Log Activity**: when a user completes a task, the system creates the matching Activity in the same operation.

---

## 2. Mandatory Reading Before Implementation

Before writing code, the coding agent must inspect and understand the existing project.

Read:

- `real-estate-planning/README.md`
- all global architecture and convention documents
- all multi-tenancy and authorization documentation
- all Arabic and English documentation under `docs/`
- Phase 07 main documentation
- Phase 07.2 Listings Management
- Phase 07.3 Leads, Opportunities & CRM Kanban
- existing CRM models, services, policies, enums, Filament resources, translations, tests, and migration conventions

The agent must specifically inspect how the existing CRM domain handles:

- translations
- enums
- status labels
- validation messages
- Filament labels/actions/modals
- Arabic RTL
- general domain translation helpers or enum translation contracts

Do not create a second translation convention if the project already has one.

Laravel Herd and the current local development environment are already working. Do not reinstall or reconfigure the environment unnecessarily.

---

## 3. Scope

Implement only Phase 07.4.

Included:

- CRM follow-up tasks
- CRM activities
- assignment and ownership rules
- task completion workflow
- Complete & Log Activity UX
- manual activity logging where allowed
- task reminders and overdue presentation
- My Tasks / Team Tasks
- Lead and Opportunity activity/task timeline integration
- Stage Requirement integration with Phase 07.3 using Activity records
- permissions and policies
- bilingual Arabic/English UI and translation coverage
- RTL support
- MySQL and PostgreSQL compatibility
- tests

Not included:

- formal Offers / Negotiations engine (Phase 07.5)
- Reservations (Phase 07.6)
- WhatsApp / Facebook / Instagram integrations (Phase 07.7)
- AI automation/classification (Phase 07.8)
- physical unit reservation or inventory locking

---

## 4. Core Domain Model

### 4.1 Follow-up Task

A Follow-up Task represents **work that must be done**.

Examples:

- call the customer
- schedule or perform a viewing
- follow up after a meeting
- send a message
- review a customer request

The task does not prove that the real-world action happened. It only represents the required work.

### 4.2 Activity

An Activity represents **what actually happened**.

Examples:

- a phone call was made
- a meeting occurred
- a viewing was completed
- a message was sent
- a note was recorded

Activities are the source of truth for operational CRM outcomes used by Stage Requirements.

---

## 5. Main UX Principle — Complete & Log Activity

Completing a task should not require the employee to create a second record manually.

The user opens a task and clicks:

**Complete & Log Activity**

The modal should show only the information required to finish the task cleanly.

The activity type is inherited from the task automatically.

The user should not re-select the same type.

Example:

Task:

- Type: `call`
- Assigned to: Mohammad
- Due: 2026-10-15 10:00

When Mohammad clicks **Complete & Log Activity**, the modal may ask for:

- Outcome
- Notes (optional)
- Optional next follow-up creation

The backend must create the Activity and complete the Task in one database transaction.

---

## 6. Task Ownership and Assignment

The task must preserve who created it, who is responsible for it, and who completed it.

Recommended fields:

- `company_id`
- `lead_id` nullable when appropriate
- `opportunity_id` nullable when appropriate
- `created_by`
- `assigned_to`
- `completed_by` nullable
- `type`
- `status`
- `title`
- `description` nullable
- `due_at` nullable
- `completed_at` nullable
- `cancelled_at` nullable
- timestamps

### Rules

`created_by`:

- always comes from the authenticated server-side actor
- must never be trusted from request/form input
- must not be user-editable

`assigned_to`:

- selectable only from eligible users in the same company
- subject to explicit permission checks
- must not be accepted only because the UI showed that employee

`completed_by`:

- written by the backend when the task is completed
- normally the authenticated actor who performed the completion action

---

## 7. Task Status

Keep task status simple.

Recommended enum:

```php
pending
in_progress
completed
cancelled
```

`overdue` should normally be calculated, not persisted.

Example:

```text
status != completed
status != cancelled
due_at < now()
=> overdue for display/filtering
```

Do not create duplicate state when the value can be safely derived.

---

## 8. Activity Structure

Recommended fields:

- `company_id`
- `lead_id` nullable
- `opportunity_id` nullable
- `task_id` nullable
- `type`
- `outcome` nullable when the activity type does not require an outcome
- `performed_by`
- `notes` nullable
- `occurred_at`
- timestamps

### Activity actor

`performed_by` records who actually performed/logged the activity.

When an activity is generated through task completion:

```text
performed_by = authenticated user completing the task
```

---

## 9. Activity Type

Use the existing project enum/domain convention if already present. Do not duplicate an existing type system.

Recommended baseline types:

```php
call
meeting
viewing
message
note
```

If the codebase already has equivalent fixed types, reuse or extend them rather than creating conflicting enums.

### Important

When completing a Task:

```php
$activity->type = $task->type;
```

The employee should not select the type again.

Manual Activities that are not created from a Task may allow the user to select the activity type, subject to permissions and validation.

---

## 10. Activity Outcome

Use structured values for business logic. Never inspect free-text notes to determine CRM state.

Recommended baseline enum:

```php
enum ActivityOutcome: string
{
    case Interested = 'interested';
    case NoAnswer = 'no_answer';
    case NotInterested = 'not_interested';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
```

These values are intentionally small and generic.

Examples:

- `call + interested`
- `call + no_answer`
- `call + not_interested`
- `viewing + completed`
- `viewing + cancelled`
- `meeting + completed`

Not every outcome is valid for every activity type.

The system must validate the allowed combinations centrally, not through scattered UI-only rules.

Example concept:

```php
ActivityType::Call => [
    Interested,
    NoAnswer,
    NotInterested,
    Completed,
]

ActivityType::Viewing => [
    Completed,
    Cancelled,
]
```

The exact mapping should follow the final project conventions and real UX needs discovered during implementation.

---

## 11. Free Text vs Structured Data

Notes remain free text.

Example:

```text
أحمد طلب معاينة الشقة يوم الخميس.
```

But business rules must never rely on text searches such as:

```php
str_contains($notes, 'مهتم')
```

Use structured fields:

```text
type = call
outcome = interested
```

The notes exist only for human context.

---

## 12. Complete & Log Activity Transaction

Use a dedicated service/domain action, for example:

```php
TaskCompletionService::complete(...)
```

The exact class name may follow existing service naming conventions.

The service must:

1. authorize the actor
2. load the task safely in company context
3. lock or otherwise protect against duplicate concurrent completion
4. ensure the task is completable
5. validate the outcome against the task/activity type
6. create the Activity
7. update the Task to completed
8. set `completed_by`
9. set `completed_at`
10. optionally create the next follow-up task if requested
11. commit all related changes atomically

Use `DB::transaction()` when multiple records are affected.

### Duplicate completion protection

A completed or cancelled task must not be completed again.

Concurrent requests should not create duplicate Activities for the same completion event.

Use the project’s preferred locking/idempotency approach.

---

## 13. No Answer Workflow

If the employee calls and the customer does not answer:

- log an Activity
- `type = call`
- `outcome = no_answer`
- preserve notes if entered

This Activity proves that an attempt occurred.

However, it must **not** satisfy a Stage Requirement that requires successful contact unless that Requirement explicitly allows `no_answer`.

The UX should make it easy to:

- reschedule the same follow-up intent, or
- create a new retry task

Do not force the user through multiple separate screens.

---

## 14. Optional Next Follow-up

The Complete & Log Activity flow may include:

**Create Next Follow-up**

Example:

Current Task:

```text
Call Ahmad
```

Activity outcome:

```text
Interested
```

Next Task:

```text
Viewing
Thursday 11:00
Assigned to Mohammad
```

This should remain optional and easy.

---

## 15. Authorization and Permissions

Authorization is a critical part of this phase.

Use the project’s existing Spatie Permission / Shield / Policy conventions.

Do not authorize based only on role names such as `manager` or `admin`.

Suggested permission capabilities, adapted to existing project naming conventions:

- create CRM task
- view own CRM tasks
- view team CRM tasks
- assign CRM task
- reassign CRM task
- update CRM task
- complete CRM task
- complete any CRM task
- cancel CRM task
- create CRM activity
- view CRM activity
- view team CRM activity where relevant

The exact permission names should follow the project’s current naming conventions and existing CRM permission structure.

### Assignment authorization

When assigning a task, validate:

- actor has permission to create/assign
- assignee belongs to the same Company
- assignee is active/eligible
- actor may assign to that user
- Lead and Opportunity belong to the same Company

### Completion authorization

Normally:

```text
assigned_to == actor.id
AND actor has completion permission
```

If completing another employee’s task:

```text
actor requires explicit elevated permission
```

Do not rely on hidden Filament buttons as security.

All checks must exist server-side in policies/services.

---

## 16. Company Isolation

Every new CRM record must be company-scoped.

Reject cross-company relationships such as:

- Task in Company A assigned to user from Company B
- Activity in Company A linked to Opportunity from Company B
- Task linked to Lead from a different Company
- manual foreign IDs submitted through requests/actions

Reuse the existing global scope / tenancy conventions already implemented in the project.

For sensitive workflow actions, explicitly verify company ownership even if global scopes exist.

---

## 17. Integration with Phase 07.3 Stage Requirements

Phase 07.3 already provides configurable Stage Requirements.

Phase 07.4 should integrate with that engine instead of creating a second requirement system.

Add or extend a supported requirement type such as:

```text
activity_exists
```

Follow the existing enum/type architecture.

Example configuration:

```json
{
  "activity_type": "call",
  "allowed_outcomes": ["interested"]
}
```

Then `StageRequirementEvaluator` can verify:

```php
$opportunity->activities()
    ->where('type', 'call')
    ->whereIn('outcome', ['interested'])
    ->exists();
```

The actual implementation must use enums/config validation rather than unsafe arbitrary input.

### Important distinction

`Task.status = completed` answers:

> Did the employee finish the assigned task?

`Activity.type + Activity.outcome` answers:

> What actually happened and what was the result?

Stage Requirement business rules should generally inspect Activities, not task completion alone.

---

## 18. CRM Kanban Behavior

Activities and task completion may satisfy requirements, but they must not silently move the Opportunity to another stage.

The existing Phase 07.3 workflow remains authoritative.

Example:

```text
Call activity = interested
        ↓
Contact requirement satisfied
        ↓
Employee attempts Kanban move
        ↓
OpportunityWorkflowService validates requirements
        ↓
Move allowed or rejected
```

No automatic stage transition in Phase 07.4 unless the existing approved 07.3 design explicitly supports it.

---

## 19. Filament UX

The implementation should prioritize simple operational screens.

### 19.1 My Tasks

Employees should quickly see:

- Today
- Upcoming
- Overdue
- In Progress
- Completed where useful

Useful filters:

- task type
- due date
- status
- Opportunity
- Lead

Primary action:

**Complete & Log Activity**

### 19.2 Team Tasks

Visible only to users with appropriate permission.

Useful filters:

- assigned employee
- status
- overdue
- date range
- task type

Managers should be able to assign/reassign tasks only when authorized.

### 19.3 Opportunity

Show:

- follow-up tasks
- activities
- quick Add Follow-up action
- quick Log Activity action where permitted
- unified timeline view

### 19.4 Lead

Show customer-wide follow-up/activity history where appropriate.

### 19.5 Timeline

Do not create a dedicated timeline table unless there is a proven need.

Build the display by combining relevant records such as:

- CRM Activities
- CRM Follow-up Tasks
- existing Opportunity Stage Histories

Sort by their relevant timestamps.

---

## 20. Reminders

Use existing Laravel Notifications / Queue / Scheduler architecture.

The project should support reminders for assigned tasks without duplicating notifications.

Possible company-level reminder settings may include default lead time such as:

- 15 minutes before
- 30 minutes before
- 1 hour before
- custom supported setting

Before adding new settings, inspect existing Company Settings architecture and reuse it.

Do not send reminders more than once unintentionally.

If a dedicated reminder log/state is needed for idempotency, implement it using the project’s preferred pattern.

---

## 21. Translation and Localization — Mandatory

All user-facing fixed values introduced in Phase 07.4 must support **Arabic and English**.

This includes, but is not limited to:

- task statuses
- activity types
- activity outcomes
- buttons
- actions
- page titles
- navigation labels
- table columns
- filters
- validation messages where project-specific
- empty states
- modal headings
- modal descriptions
- confirmation messages
- permission labels if exposed
- reminder labels
- Kanban/Stage Requirement labels introduced or modified by this phase

### Mandatory implementation rule

Before adding translation code, inspect how the existing CRM and general domain already translate:

- enums
- statuses
- Filament actions
- labels
- generic reusable terms

If a key or reusable phrase already exists in the shared/global domain translation files, reuse it.

Do not duplicate translations under a new Phase 07.4-only key when the project already has the same semantic key.

If the project uses methods such as:

```php
label()
getLabel()
trans(...)
__()
```

or an enum translation contract/trait, follow the existing pattern consistently.

Arabic must render correctly in RTL.

English must render in LTR.

Avoid hard-coded English in Filament resources and services.

Database enum values/codes remain stable language-neutral identifiers such as:

```text
interested
no_answer
completed
```

Only the displayed label is translated.

---

## 22. Suggested Tables

Inspect the repository first to avoid duplicates.

Expected new tables are likely:

### `crm_follow_up_tasks`

Suggested columns:

```text
id
company_id
lead_id nullable
opportunity_id nullable
created_by
assigned_to
completed_by nullable
type
status
title
description nullable
due_at nullable
completed_at nullable
cancelled_at nullable
created_at
updated_at
```

### `crm_activities`

Suggested columns:

```text
id
company_id
lead_id nullable
opportunity_id nullable
task_id nullable
type
outcome nullable
performed_by
notes nullable
occurred_at
created_at
updated_at
```

Add indexes and foreign keys appropriate for:

- tenant/company filtering
- assignee task queries
- overdue queries
- Opportunity timelines
- Lead timelines
- Stage Requirement Activity lookup

Do not blindly use the exact names above if equivalent structures already exist.

---

## 23. Recommended Services / Domain Actions

Follow existing project architecture.

Possible services:

- `CrmTaskService`
- `TaskCompletionService`
- `CrmActivityService`
- `CrmTaskAssignmentService`
- extension of existing `StageRequirementEvaluator`

Do not create many tiny services without value.

Prefer cohesive domain operations with strong authorization and validation boundaries.

---

## 24. Example End-to-End Scenario

Company: Al-Nokhba Real Estate

Existing records:

```text
Lead #15: Ahmad Mohammad
Opportunity #101: Apartment A-501
Stage: Qualified
```

Manager creates:

```text
Task #50
Type: call
Created By: Manager
Assigned To: Mohammad
Due At: Tomorrow 10:00
Status: pending
```

System validates:

- Manager may create/assign
- Mohammad belongs to same Company
- Opportunity and Lead belong to same Company

Mohammad opens:

```text
CRM → My Tasks
```

He calls Ahmad.

Ahmad answers and is interested.

Mohammad clicks:

```text
Complete & Log Activity
```

He enters:

```text
Outcome: interested
Notes: none
```

Backend transaction creates:

```text
Activity
  task_id = 50
  type = call          // inherited from Task
  outcome = interested
  performed_by = Mohammad
```

Then updates:

```text
Task #50
  status = completed
  completed_by = Mohammad
  completed_at = now()
```

If a Stage Requirement requires:

```text
activity_type = call
allowed_outcomes = [interested]
```

then that requirement becomes satisfied.

The Opportunity does not move automatically.

When Mohammad later attempts a Kanban move, the existing Opportunity workflow validates the requirement and allows/rejects the transition.

---

## 25. Testing Requirements

Add comprehensive automated coverage.

At minimum test:

1. authorized user can create task
2. unauthorized user cannot create task
3. task `created_by` comes from authenticated actor
4. user cannot spoof `created_by`
5. assignee must belong to same company
6. unauthorized cross-company assignment rejected
7. ordinary employee can view own tasks
8. ordinary employee cannot view team tasks without permission
9. manager/team permission allows team task view
10. task can be reassigned only with permission
11. assigned employee can complete own task with permission
12. unassigned employee cannot complete another task without elevated permission
13. elevated permission can complete another employee task if business rules allow
14. Complete & Log Activity creates exactly one Activity
15. Activity type inherits from Task type
16. valid outcome accepted
17. invalid type/outcome combination rejected
18. Task becomes completed atomically with Activity creation
19. completed task cannot be completed twice
20. concurrent completion cannot create duplicate completion Activities
21. `no_answer` logs Activity correctly
22. `no_answer` does not satisfy successful-contact requirement unless configured
23. `interested` can satisfy configured `activity_exists` requirement
24. Activity from another Opportunity does not satisfy requirement
25. Activity from another Company does not satisfy requirement
26. Stage movement still goes through Phase 07.3 workflow
27. completing task does not auto-move Opportunity
28. overdue is calculated correctly
29. cancelled/completed tasks are not treated as overdue
30. Arabic labels resolve correctly
31. English labels resolve correctly
32. enum labels follow existing translation convention
33. no user-facing fixed string remains hard-coded where translation is required
34. RTL screens/actions remain usable
35. MySQL tests pass
36. PostgreSQL tests pass
37. relevant Phase 07.3 regression tests pass
38. relevant Phase 07.2 regression tests pass

---

## 26. Definition of Done

Phase 07.4 is complete only when:

- Follow-up Tasks work end-to-end
- Activities work end-to-end
- Complete & Log Activity is implemented as a single convenient operation
- task type is inherited automatically by completion Activity
- `created_by`, `assigned_to`, `completed_by`, and `performed_by` are handled correctly
- assignment and completion permissions are enforced server-side
- company isolation is verified
- Stage Requirements can inspect Activities safely
- no Stage logic depends on free-text notes
- My Tasks works
- Team Tasks respects permissions
- Opportunity/Lead timeline integration works
- reminders do not duplicate unexpectedly
- all new fixed UI/domain values are translated Arabic + English
- RTL is correct
- existing CRM/general translation conventions are reused
- MySQL and PostgreSQL tests pass
- relevant regression tests pass
- no Phase 07.5+ functionality was accidentally implemented

