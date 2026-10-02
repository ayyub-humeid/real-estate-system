# EXECUTION PROMPT — Phase 01: Property, Acquisition & Ownership

## Mission

Implement **Phase 01 only** in the existing Laravel repository.

Do not start Project Planning, Design, Budgeting, Construction, Sales, Contracts/Payments redesign, Handover, or SaaS Billing.

## Mandatory Reading

Before changing code, read:

```text
real-estate-planning/README.md
real-estate-planning/00-domain-rules-and-global-conventions.md
real-estate-planning/00-existing-system-gap-analysis.md
real-estate-planning/day-1/01-property-acquisition/SPEC.md
```

Then inspect the actual repository.

Do not blindly create all tables from the specification.

## Required Repository Inspection

Inspect at minimum:

```text
database/migrations
app/Models
existing tenant/company Global Scope + Trait
app/Policies
Filament Shield configuration / permissions
Filament Resources / Relation Managers
notification classes
contracts/documents/properties/current actor/customer structures
factories
tests/Feature
tests/Unit
Pest/PHPUnit configuration
```

Search for all usages before renaming, dropping, or changing semantics.

Continue into implementation unless a master README stop condition is reached.

## Phase Scope

Implement only what is required for:

```text
Party strategy
Property reuse/adaptation
Property Ownership History
Property Acquisition
Acquisition ↔ Properties
Acquisition ↔ Parties
Due Diligence Cases
Due Diligence Items
Generic Documents integration
Generic Contracts compatibility where required
important notifications
Filament UI
Policies + Shield
tests
```

Do not redesign the global Contracts/Payments system in this phase.

## Architecture

Reuse the current tenant/company Global Scope/Trait.

Every new model requires relationships, Policy, Shield permissions, validation, indexes, and tests.

Explicitly authorize sensitive actions:

```text
approve acquisition
cancel acquisition
complete acquisition
waive due-diligence item
clear due-diligence case
change property ownership
```

## Migration Safety

Do not fabricate legacy owner/acquisition/date/value/party data.

Use staged migrations for risky changes.

If destructive legacy cleanup is ambiguous or irreversible, stop before that destructive operation and explain the issue.

## Transactions + Notifications

Use transactions for important multi-record operations.

Send business notifications only after successful commit.

Avoid duplicate dispatch paths.

Use domain-based recipients, not every company user.

## Filament

Implement required Resources/Relation Managers/Actions.

Normal workflow changes should update reactively without forced full-page reload.

UI hiding is not authorization.

## Performance

Review list/detail queries, indexes, eager loading, counts, and N+1 risk.

Do not put all relationships in `$with`.

# Automated Tests

Create the appropriate Feature/Integration/Unit tests.

At minimum cover applicable cases:

### Acquisition
```text
authorized creation
cross-tenant access denied
multiple properties
multiple parties
invalid transition rejected
cancelled acquisition cannot complete
approval/completion requires permission
```

### Ownership
```text
percentage validation
history preserved
active ownership resolution
cross-tenant mutation denied
unauthorized change denied
transaction safety
```

### Due Diligence
```text
required pending item blocks clearance
required failed item blocks clearance
authorized waiver works
unauthorized waiver denied
blocked/cleared transitions
```

### Authorization
```text
Policy tenant boundaries
CRUD permissions
custom workflow permissions
server-side protection
```

### Notifications
```text
correct recipient
wrong tenant receives nothing
rollback sends no business notification
trivial edits avoid unnecessary noise
```

If a reproducible Phase 01 bug is found:

```text
write failing regression test
→ fix
→ prove pass
```

Never weaken a valid test to make the suite green.

## Test Execution

Run targeted tests during implementation.

Before completion:

```bash
php artisan test
```

Fix all failures introduced by Phase 01.

Report unrelated pre-existing failures accurately.

# Completion

Create:

```text
real-estate-planning/day-1/01-property-acquisition/IMPLEMENTATION-REPORT.md
```

using the report template.

Then STOP.

Do not start Phase 02.

Do not commit unless explicitly requested.
