# EXECUTION PROMPT — Phase 03: Design, Engineering, Review & Approvals

## Mission

Implement **Phase 03 only**.

Do not start Budgeting or Construction.

## Mandatory Reading

Read:

```text
real-estate-planning/README.md
real-estate-planning/00-domain-rules-and-global-conventions.md
real-estate-planning/00-existing-system-gap-analysis.md
real-estate-planning/day-1/01-property-acquisition/IMPLEMENTATION-REPORT.md
real-estate-planning/day-1/02-project-planning/IMPLEMENTATION-REPORT.md
real-estate-planning/day-1/03-design-engineering/SPEC.md
```

If either prior report is missing, stop.

Inspect current code/schema before implementation.

## Scope

Implement/adapt:

```text
Design Packages
Assignments
Assignment Scope Items
Activities
Documents + Document Versions integration
Formal Submissions
Submission ↔ exact Document Versions
Reviews
Findings
Revisions
Revision ↔ Findings
Approvals
package lifecycle
Filament
Policies + Shield
notifications
tests
```

No separate resubmission table.

## Critical Rules

Preserve:

```text
Package ≠ Assignment
Scope belongs to Assignment
Activity ≠ Submission
Document ≠ Document Version
Submission pins exact Document Versions
Finding ≠ Revision
Revision ≠ Resubmission
Approval identifies exact approved Submission
```

Do not overwrite formal history.

## Inspection

Inspect existing document/version/storage architecture, Projects, Parties, approvals/reviews, notifications, Shield/Policies, tenant scope, Filament UI, and tests.

Reuse generic Documents when valid.

## Immutability

After formal submission:

```text
do not silently replace attached versions
do not delete completed reviews
do not delete findings because resolved
```

Create new versions/submissions.

## Authorization

Every new model requires Policy + Shield.

Explicitly protect assignment, submission, review, finding waiver, revision, resubmission, approval, and closure actions.

## Notifications

Implement important assignment/submission/review/finding/revision/approval/overdue events from the SPEC.

Use after-commit dispatch.

## Filament

Keep workflow understandable under Project context.

Use reactive actions and avoid forced reload.

## Performance

Use latest/current relationships, counts, targeted eager loading.

Do not load all history on index rows.

# Automated Tests

At minimum cover:

### Assignment / Scope
```text
history preserved
replacement behavior
scope belongs to assignment
cross-tenant office denied
```

### Submissions / Documents
```text
submission numbering
exact version pinning
submitted version immutability
resubmission creates new submission
previous submission preserved
```

### Reviews / Findings
```text
review lifecycle
major/critical finding
finding target relations
unauthorized waiver denied
resolved finding remains historical
```

### Revisions
```text
source submission relation
finding relations
new submission creation
no source overwrite
```

### Approval
```text
permission required
exact submission approved
blocking finding rule
approved/closed immutability
```

### Policies / Notifications / Jobs
```text
CRUD + custom permissions
tenant/state rules
correct recipients
overdue reminder logic
rollback sends no business notification
```

Add regression tests before fixing discovered current-phase bugs.

## Test Execution

Run targeted tests then:

```bash
php artisan test
```

Resolve Phase 03-caused failures.

# Completion

Create:

```text
real-estate-planning/day-1/03-design-engineering/IMPLEMENTATION-REPORT.md
```

Then STOP.

Do not start Phase 04 until developer manual review.
