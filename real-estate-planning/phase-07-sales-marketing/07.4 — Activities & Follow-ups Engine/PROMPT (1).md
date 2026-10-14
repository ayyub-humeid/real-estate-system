# Coding Agent Prompt — Phase 07.4 Activities & Follow-ups Engine

Start implementing **Phase 07.4 — Activities & Follow-ups Engine** for **Madar | مدار**.

This phase must be implemented completely and integrated with the existing Phase 07.3 CRM.

## 1. Read first — mandatory

Before writing code, read and understand:

1. `real-estate-planning/README.md`
2. all global architecture/convention documents
3. all tenancy, permissions, workflow, and domain convention documentation
4. all relevant files under `docs/` in **Arabic and English**
5. Phase 07 main SPEC/PROMPT
6. Phase 07.2 Listings Management
7. Phase 07.3 Leads, Opportunities & CRM Kanban
8. this Phase 07.4 `SPEC.md`
9. existing Laravel models, migrations, enums, services, policies, Filament resources, translations, tests, and company settings

Do not code before understanding the existing project conventions.

The Laravel Herd environment is already configured and working. **Do not reinstall or reconfigure Laravel Herd, PHP, Composer, Laravel, databases, or the local environment unless there is an actual project error that requires it.**

---

## 2. Translation system — inspect before adding anything

This is mandatory.

Before introducing new labels or translation helpers, inspect how the existing CRM and the general domain already implement:

- enum labels
- statuses
- Filament labels
- actions
- table headings
- filters
- validation messages
- shared/general translation keys
- Arabic RTL handling

Reuse the existing translation architecture.

Do not duplicate translation keys when a shared/general key already exists.

Every fixed user-facing value introduced in this phase must support **Arabic and English**.

This includes all statuses, activity types, activity outcomes, buttons, actions, modal labels, page titles, navigation labels, filters, empty states, confirmation messages, and any fixed domain label visible to users.

Database values must remain stable language-neutral codes such as:

```text
call
interested
no_answer
completed
```

Only labels are translated.

Arabic must work correctly in RTL.

English must work correctly in LTR.

---

## 3. Core business model

Implement two distinct concepts:

### Follow-up Task

Represents **what the employee needs to do**.

Examples:

- call customer
- viewing follow-up
- meeting
- message
- general follow-up

### Activity

Represents **what actually happened**.

Examples:

- call performed
- viewing completed
- meeting completed
- message sent
- note logged

Do not collapse Task and Activity into one model.

---

## 4. Main UX — Complete & Log Activity

This is the main interaction for task completion.

When an employee completes a Task, they should not manually create a separate Activity afterward.

Implement a single action:

```text
Complete & Log Activity
```

The Activity type must be inherited automatically from the Task type.

The user should normally choose only:

- outcome
- optional notes
- optional next follow-up details

Do not ask the user to select the activity type again.

The operation must run atomically inside a database transaction.

---

## 5. Task fields and ownership

Inspect the current database before creating migrations.

If no equivalent structure exists, implement a company-scoped task model/table with fields conceptually equivalent to:

```text
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
timestamps
```

Rules:

- `created_by` must come from the authenticated actor on the server
- never trust `created_by` from form/request input
- `assigned_to` must be an eligible same-company employee
- `completed_by` is set by the backend when completion succeeds

---

## 6. Activity fields

If no equivalent structure exists, implement a company-scoped Activity model/table conceptually equivalent to:

```text
company_id
lead_id nullable
opportunity_id nullable
task_id nullable
type
outcome nullable
performed_by
notes nullable
occurred_at
timestamps
```

When created from a Task completion:

```php
activity.type = task.type
activity.performed_by = authenticated actor
```

---

## 7. Enums and structured business values

Reuse existing enums if they already exist.

Otherwise use the project’s existing enum conventions.

Recommended Task statuses:

```text
pending
in_progress
completed
cancelled
```

Do not persist `overdue` if it can be derived safely from `due_at` + status.

Recommended Activity types:

```text
call
meeting
viewing
message
note
```

Recommended Activity outcomes:

```text
interested
no_answer
not_interested
completed
cancelled
```

Do not use free-text notes for business logic.

Validate allowed type/outcome combinations centrally.

Example:

```text
call -> interested | no_answer | not_interested | completed
viewing -> completed | cancelled
meeting -> completed | cancelled
```

Adapt the exact mapping if the existing domain model requires it, but keep the logic structured and explicit.

---

## 8. Authorization — critical

Use existing Spatie/Shield/Policy conventions.

Do not authorize by hard-coded role names.

Support permissions equivalent to the project naming style for:

- create task
- view own tasks
- view team tasks
- assign task
- reassign task
- update task
- complete own/assigned task
- complete another employee’s task with explicit elevated permission
- cancel task
- create/log activity
- view activities

Important rules:

### Assignment

Before assignment/reassignment verify:

- actor has permission
- assignee belongs to the current Company
- assignee is active/eligible
- Lead belongs to Company
- Opportunity belongs to Company
- no cross-company relation is accepted

### Completion

Default behavior:

```text
assigned_to == authenticated actor
AND actor has completion permission
```

Completing another user’s Task requires explicit elevated permission.

Filament button visibility is not sufficient security. Repeat the checks in the backend policy/service/action.

---

## 9. Complete & Log Activity domain service

Implement completion through a cohesive service/domain action following project conventions.

It must:

1. authorize the actor
2. validate company scope
3. safely load/lock the Task
4. reject completed/cancelled tasks
5. validate outcome for the Task type
6. create one Activity
7. inherit Activity type from Task type
8. set Activity `performed_by`
9. mark Task completed
10. set `completed_by`
11. set `completed_at`
12. optionally create a next Follow-up Task
13. commit atomically

Prevent duplicate completion and duplicate Activity creation under repeated/concurrent requests.

Use row locking or the project’s existing concurrency-safe pattern.

---

## 10. No Answer behavior

If a call result is `no_answer`:

- log the Activity
- preserve the attempt in history
- do not treat it as successful contact unless a configured Stage Requirement explicitly allows `no_answer`
- offer a quick retry/reschedule/new follow-up UX

Do not silently count `no_answer` as contacted.

---

## 11. Stage Requirement integration with Phase 07.3

Do not build a second workflow/requirements engine.

Extend the existing Phase 07.3 Stage Requirement system with a safe supported requirement type conceptually equivalent to:

```text
activity_exists
```

Example configuration:

```json
{
  "activity_type": "call",
  "allowed_outcomes": ["interested"]
}
```

Validation must ensure only supported Activity types/outcomes are accepted.

The Stage Requirement evaluator should query real Activity records associated with the same Company and Opportunity.

Example logic:

```php
$opportunity->activities()
    ->where('type', ActivityType::Call->value)
    ->whereIn('outcome', [ActivityOutcome::Interested->value])
    ->exists();
```

The exact implementation must follow existing relationships/scopes.

Do not rely on task `status = completed` alone to prove a business event happened.

Do not inspect free-text notes.

---

## 12. No automatic Kanban movement

Completing a Task or logging an Activity may satisfy Stage Requirements, but it must not automatically move the Opportunity.

The existing Phase 07.3 `OpportunityWorkflowService` remains authoritative.

The user moves the Opportunity through Kanban; the workflow then validates requirements.

---

## 13. Filament screens

Implement the UI according to existing project structure and styling.

### My Tasks

For employees:

- Today
- Upcoming
- Overdue
- In Progress
- Completed where useful

Include appropriate filters and search.

Primary action:

```text
Complete & Log Activity
```

### Team Tasks

Only users with permission can view/manage team tasks.

Support filters such as:

- employee
- status
- overdue
- type
- date range

### Opportunity integration

Add appropriate sections/actions for:

- follow-up tasks
- activities
- quick Add Follow-up
- quick Log Activity where permitted
- timeline

### Lead integration

Show customer-wide history and related tasks/activities where appropriate.

### Timeline

Do not create a dedicated timeline table unless technically necessary.

Build the timeline from existing/new domain tables, such as:

- activities
- follow-up tasks
- opportunity stage histories

---

## 14. Reminders

Use existing Laravel Notifications / Queue / Scheduler patterns.

Support task reminders without duplicate sends.

Inspect Company Settings before creating any new configuration.

If reminder configuration already exists, reuse it.

If additional settings are needed now, add them following current Company Settings conventions.

---

## 15. Database and performance

Use proper foreign keys and indexes for both MySQL and PostgreSQL.

Optimize common queries for:

- `assigned_to`
- `company_id`
- `status`
- `due_at`
- `opportunity_id`
- `lead_id`
- `task_id`
- `type`
- `outcome`

Avoid N+1 queries in Filament tables, timelines, and Opportunity/Lead pages.

Use eager loading where appropriate.

---

## 16. Localization requirements

This phase is not complete unless all new user-facing fixed values are translated into Arabic and English.

Before adding keys, inspect the existing translation organization in both the CRM and shared/general domain.

Reuse existing generic keys where semantically identical.

Examples that must be localized:

- Pending
- In Progress
- Completed
- Cancelled
- Call
- Meeting
- Viewing
- Message
- Note
- Interested
- No Answer
- Not Interested
- Complete & Log Activity
- Create Next Follow-up
- My Tasks
- Team Tasks
- Overdue
- Assigned To
- Created By
- Completed By

Do not hard-code English in Filament code.

Check Arabic RTL visually where possible.

---

## 17. Testing

Add thorough automated tests for:

- task creation authorization
- server-side `created_by`
- assignment authorization
- same-company assignee validation
- cross-company rejection
- own vs team task visibility
- own task completion
- completing another user’s task permission
- task completion atomicity
- Activity creation
- inherited Activity type
- valid/invalid outcomes
- duplicate completion protection
- no-answer behavior
- optional next follow-up
- Stage Requirement `activity_exists`
- same Opportunity/company constraint
- no automatic Kanban movement
- overdue calculation
- Arabic translations
- English translations
- enum translation behavior
- RTL integration where practical
- MySQL
- PostgreSQL
- relevant Phase 07.3 regressions
- relevant Phase 07.2 regressions

Run the full relevant test set before declaring the phase complete.

---

## 18. Final implementation report

After implementation, provide a clear report containing:

1. files created/changed
2. migrations/schema changes
3. models/enums added or extended
4. permissions/policies added or changed
5. services/domain actions added or changed
6. Stage Requirement integration changes
7. Filament pages/resources/actions added or changed
8. translation files/keys added or reused
9. reminder/scheduler behavior
10. tests run on MySQL
11. tests run on PostgreSQL
12. relevant regression results
13. any manual UI verification still required
14. any intentionally deferred work for Phase 07.5+

Do not claim successful visual verification unless it was actually performed.

---

## 19. Scope boundaries

Do not implement:

- formal offers
- negotiation records
- reservation locking
- contracts
- WhatsApp/Facebook/Instagram integration
- AI follow-up generation

Those belong to later phases.

However, keep services and domain structures clean enough for future integrations to reuse them.

---

## 20. Begin

Begin now.

First read the required documentation in **Arabic and English**, inspect the existing CRM translation/domain conventions and the existing Phase 07.3 workflow, then implement Phase 07.4 end-to-end according to the approved SPEC.
