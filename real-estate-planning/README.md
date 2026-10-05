# Real Estate Planning — Master Agent Instructions

## 1. Purpose

This directory controls the staged evolution of an **existing Laravel + Filament real-estate application**.

The repository is the implementation baseline.

The specifications in this directory define the approved target domain.

The coding agent must evolve the existing application toward that target without blindly rebuilding concepts that already exist.

---

## 2. Mandatory Reading Order for Every Phase

Before modifying code, read:

```text
1. real-estate-planning/README.md
2. real-estate-planning/00-domain-rules-and-global-conventions.md
3. real-estate-planning/00-existing-system-gap-analysis.md
4. Current phase SPEC.md
5. All previous phase IMPLEMENTATION-REPORT.md files that already exist
6. Current phase PROMPT.md
```

The gap-analysis file is a working implementation bridge, not a substitute for inspecting the actual repository.

Repository inspection determines what currently exists.

The approved domain specifications determine what the system should become.

---

## 3. Repository-First Rule

Never create a table/model/resource simply because a specification mentions it.

For every concept:

```text
Inspect current repository
        ↓
Does a correct concept already exist?
        ├── Yes → REUSE / ALTER / ADD_RELATIONSHIP
        └── No  → CREATE
```

Available classifications:

```text
REUSE
CREATE
ALTER
ADD_RELATIONSHIP
RENAME
RESTRUCTURE
SPLIT
MERGE
DROP
MIGRATE_DATA
VERIFY
```

Do not create duplicate domain concepts such as:

```text
properties_v2
new_unit_features
second tenant architecture
second permission architecture
second notification path
```

---

## 4. Existing Architecture That Must Be Respected

Before changing related code, inspect and reuse the existing:

```text
company/tenant Global Scope + Trait
Filament architecture
Filament Shield
Policies
roles/permissions
notification infrastructure
Eloquent model conventions
services/actions
API conventions
documents/files
existing tests
```

Do not replace the existing tenant Global Scope merely because another approach is possible.

---

## 5. Every New Eloquent Model Must Be Complete

A new model is not complete until the implementation has considered:

```text
migration
correct nullability
foreign keys
indexes
casts
relationships
inverse relationships where useful
existing tenant Global Scope / Trait
Policy
Filament Shield permissions
Filament Resource / Relation Manager when applicable
validation
business-state rules
important notifications
audit/activity implications
factories/tests where useful
eager-loading / N+1 implications
company_id inheritance in relation managers (see below)
```

### Relation Manager Company Context Inheritance (MANDATORY)

The `HasCompany` trait auto-injects `company_id` on `creating` **only for non-super-admin users**. For super admins, `company_id` is intentionally NOT auto-injected.

This means every relation manager that creates child records **must explicitly set `company_id`** from the parent record. Do not rely on the `HasCompany` trait alone.

Required pattern:

```php
// On the CreateAction in headerActions:
Tables\Actions\CreateAction::make()
    ->mutateFormDataUsing(function (array $data): array {
        $data['company_id'] = $this->getOwnerRecord()->company_id;
        return $data;
    }),

// Also keep mutateFormDataBeforeCreate as a safety net:
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['company_id'] = $this->getOwnerRecord()->company_id;
    return $data;
}
```

Use explicit `$data['company_id'] = ...` assignment, never `$data += [...]` (the `+=` operator does not overwrite existing keys).


---

## 6. Authorization Rule

Every new model must have a Policy and use the existing Filament Shield permission convention.

Sensitive business actions need explicit permissions when appropriate.

Examples:

```text
update != approve
update != cancel
update != waive
update != transfer ownership
update != approve budget
```

Authorization must consider:

```text
permission
+
tenant/company
+
record context
+
business state
```

Hiding an action in Filament is not sufficient authorization.

### Permission Naming — Critical

This project has TWO permission naming conventions. Using the wrong one causes silent authorization failures. See `AGENTS.md → Filament Shield Permission Naming Convention` for the complete specification, templates, and decision matrix.

Summary:

```text
Top-level Filament Resource  → Shield-generated → {action}_{word1}::{word2}
                                Example: view_property::acquisition

Non-resource / relation manager / workflow → Custom → {action}_{snake_case}
                                Example: approve_property_acquisition
```

Every policy must be verified against the actual permission strings in the `permissions` database table.


---

## 7. Notification Rule

Important business operations should notify the correct responsible users.

Do not create notification noise for trivial CRUD.

Preferred flow:

```text
Business Action
→ DB Transaction
→ Successful Commit
→ Domain Event / Notification Dispatch
→ Persistent In-App Notification when useful
→ Immediate Filament toast for actor UX
→ Optional queued external channel
```

Do not send the same notification independently from multiple layers.

Do not notify every tenant user by default.

---

## 8. Performance Rule

Avoid N+1 and over-fetching.

Use deliberately:

```text
with()
withCount()
withSum()
loadMissing()
aggregate queries
pagination
indexes based on actual query patterns
```

Do not blindly place every relationship in model `$with`.

Filament list pages must remain query-efficient.

---

## 9. Clean-Code Rule

Keep domain logic out of controllers, Filament presentation classes, Livewire presentation code, and React presentation code when that logic belongs in reusable actions/services/domain classes.

Reuse existing project architecture rather than introducing unnecessary abstractions.

---

## 10. Filament / Livewire UX & Display Rule

Normal CRUD and workflow actions should be reactive.

Preferred:

```text
Action
→ backend domain logic
→ transaction
→ affected Livewire state/table refresh
→ UI reflects result
```

Avoid forced browser reloads or unnecessary redirects as state synchronization.

### Clear Display of Relational Data

When ANY complex workflow or nested relationship exists across the application (e.g., Budgets, Contracts, Property Acquisitions, Construction), having the actions work on the backend is not enough. The UI must cleanly surface all related data and results.

- **Use Infolists and Tabs:** ALWAYS use categorized Tabs or Sections within a `ViewAction` (or Resource View page) to group nested relationships (e.g., children records, documents, workflow history, reviews). Do not use a simple single-column text display.
- **Visualize State:** Always display status and severity fields using color-coded Badges to make the state immediately clear at a glance.
- **Surface Important History:** Actions that generate an audit trail (like revisions, approvals, or document versions) must have their history clearly mapped and visible in the UI, rather than hidden in the database.
- **Complete Mapping:** Displaying just the top-level parent record is insufficient. The UI must clearly map out its child items, workflow submissions, and all related entities in a report-like format.

---

## 11. Safe Migration Rule

For risky restructuring:

```text
add target structure
→ migrate/backfill trustworthy data
→ update reads/writes
→ validate
→ stop legacy writes
→ remove obsolete structure only when safe
```

Never invent fake values to satisfy non-null constraints.

Never destroy historical financial, ownership, contract, design, or approval data casually.

---

## 12. Automated Testing Is Mandatory

No phase is complete until automated tests covering that phase pass.

Use the appropriate level:

```text
Unit Tests
→ isolated business logic

Feature / Integration Tests
→ DB workflows
→ policies
→ tenant isolation
→ relationships
→ validation
→ state transitions
→ notifications
→ transactions
→ Filament/Livewire critical behavior when practical
```

Test happy paths, failure paths, unauthorized access, wrong tenant access, invalid state transitions, and important edge cases.

When a bug is found in current-phase behavior:

```text
write regression test
→ prove failure
→ fix
→ prove pass
```

Never weaken a valid test merely to make the suite green.

---

## 13. Test Commands and Completion Gate

Run targeted tests during implementation.

Before declaring the phase complete:

```bash
php artisan test
```

The phase must not introduce unresolved test failures.

If the repository already has unrelated failing tests, identify them clearly in the implementation report rather than hiding them.

---

## 14. Phase Execution Lifecycle

Every phase follows:

```text
READ
↓
INSPECT REPOSITORY
↓
MAP CURRENT → TARGET
↓
IMPLEMENT CURRENT PHASE ONLY
↓
MIGRATE DATA SAFELY IF REQUIRED
↓
POLICIES + SHIELD
↓
FILAMENT
↓
NOTIFICATIONS
↓
PERFORMANCE REVIEW
↓
AUTOMATED TESTS
↓
FULL TEST SUITE
↓
IMPLEMENTATION-REPORT.md
↓
STOP
```

The developer then performs:

```text
manual Filament testing
manual UX testing
code review
database review if useful
```

Only after developer approval does the next phase begin.

Do not automatically start the next phase.

Do not automatically commit to Git unless explicitly asked.

---

## 15. Stop Conditions

Continue autonomously through normal implementation decisions.

Stop and ask the developer before performing an area when repository inspection reveals:

```text
credible risk of irreversible data loss
ambiguous destructive migration
major contradiction between approved domain and existing production behavior
a decision that would materially change previously approved architecture
insufficient trustworthy legacy data for a required transformation
```

Do not stop merely because a table/relationship has a different existing name.

Investigate and map it.

---

## 16. Implementation Report

At the end of each phase, create:

```text
IMPLEMENTATION-REPORT.md
```

inside that phase directory.

Use:

```text
real-estate-planning/_templates/IMPLEMENTATION-REPORT-TEMPLATE.md
```

as the minimum structure.

The report represents the actual repository state after implementation and will be read by all following phases.

---

## 17. Day 1 Execution Order

Execute strictly:

```text
01-property-acquisition
↓ developer review
02-project-planning
↓ developer review
03-design-engineering
↓ developer review
04-budgeting
↓ developer review
```

Never execute Day 1 as one giant change set.

Each phase is its own stable checkpoint.
