# 03 — Design, Engineering, Review & Approvals

> **Phase:** Design / Engineering / Approvals  
> **Depends on:** Approved project planning structure  
> **Goal:** Track external engineering-office assignments, scope, design work, formal submission rounds, exact document versions, review findings, revisions, resubmissions, and final approval without losing history.

---

# 1. Purpose

This phase answers:

```text
What design package must be produced?
Which engineering office is responsible?
What is included in its scope?
What work happened?
What exact files were formally submitted?
Who reviewed them?
What findings were raised?
What revision addressed each finding?
What was resubmitted?
What exact submission was approved?
```

---

# 2. Core Workflow

```text
Design Package
   ↓
Assignment to Engineering Office
   ↓
Scope Items
   ↓
Activities / Work
   ↓
Formal Submission
   ↓
Review
   ↓
Findings
   ↓
Revision Required
   ↓
Revision Work
   ↓
New Submission
   ↓
Review
   ↓
Approval
   ↓
Package Closed
```

A resubmission is a **new submission**, never an overwrite of the previous one.

---

# 3. Important Domain Separation

```text
Package ≠ Assignment
Assignment ≠ Scope
Activity ≠ Submission
Document ≠ Document Version
Submission ≠ Approval
Review Finding ≠ Revision
Revision ≠ Resubmission
```

---

# 4. External Engineering Office Rule

The current requirement is to track the external engineering office/party.

Do not add individual engineer tracking inside the office unless a later requirement demands it.

Assignment points to:

```text
party_id
```

representing the engineering office.

---

# 5. Existing-System Mapping

Expected:

| Concept | Action |
|---|---|
| Projects | REUSE |
| Parties | REUSE from Phase 01 |
| Documents | REUSE / ALTER if versioning missing |
| Design packages | CREATE |
| Assignments | CREATE |
| Scope items | CREATE |
| Activities | CREATE |
| Submissions | CREATE |
| Submission documents | CREATE |
| Reviews | CREATE |
| Findings | CREATE |
| Revisions | CREATE |
| Revision-findings relation | CREATE |
| Approvals | CREATE |
| Notifications | REUSE + EXTEND |
| Policies / Shield | EXTEND |

---

# 6. Entity: Project Design Package

## Table

```text
project_design_packages
```

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_id | FK | required |
| name | string | required |
| code | string | nullable |
| discipline | string | nullable |
| description | text | nullable |
| status | string | required |
| target_submission_date | date | nullable |
| approved_at | timestamp | nullable |
| closed_at | timestamp | nullable |
| created_by | FK users | nullable |
| timestamps | timestamps | required |

Examples of `discipline`:

```text
architectural
structural
electrical
mechanical
civil
interior
other
```

Keep it configurable/extensible.

---

# 7. Package Lifecycle

Approved lifecycle:

```text
planned
  ↓
assigned
  ↓
in_progress
  ↓
submitted
  ↓
under_review
  ↓
revision_required
  ↓
resubmitted
  ↓
under_review
  ↓
approved
  ↓
closed
```

Transitions may repeat:

```text
revision_required
→ resubmitted
→ under_review
→ revision_required
```

until approval.

Do not store only a final status and lose round history.

---

# 8. Entity: Design Package Assignment

## Table

```text
design_package_assignments
```

## Purpose

Preserve assignment history when engineering office changes.

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_design_package_id | FK | required |
| party_id | FK | required |
| assigned_at | timestamp | required |
| ended_at | timestamp | nullable |
| status | string | required |
| notes | text | nullable |
| assigned_by | FK users | nullable |
| timestamps | timestamps | required |

Statuses:

```text
active
completed
cancelled
replaced
```

Do not overwrite the old `party_id` when assignment changes.

---

# 9. Entity: Design Package Scope Item

## Table

```text
design_package_scope_items
```

## Critical Rule

Scope belongs to the **assignment**, not merely the package.

This preserves what a specific office was actually contracted/assigned to deliver.

## Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| design_package_assignment_id | FK | required |
| code | string | nullable |
| title | string | required |
| description | text | nullable |
| status | string | required |
| sort_order | integer | required/default |
| target_date | date | nullable |
| completed_at | timestamp | nullable |
| timestamps | timestamps | required |

Status:

```text
pending
in_progress
ready
cancelled
```

`ready` means internally finished and ready for submission.

It does **not** mean approved.

---

# 10. Entity: Design Package Activity

## Table

```text
design_package_activities
```

## Purpose

Track meaningful work/activity without pretending each activity is a formal submission.

## Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_design_package_id | FK | required |
| design_package_scope_item_id | FK | nullable |
| type | string | required |
| description | text | required |
| occurred_at | timestamp | required |
| performed_by | FK users | nullable |
| metadata | JSON | nullable |
| timestamps | timestamps | required |

Use JSON only for genuinely variable supporting metadata, not core relational data.

---

# 11. Documents & Versions

Reuse:

```text
documents
document_versions
```

## Rule

`documents` = logical document.

`document_versions` = exact file version.

Example:

```text
Structural Drawings
├── V1
├── V2
└── V3
```

Never replace V1 file with V2.

---

# 12. Document Version Minimum Needs

If existing `document_versions` is missing required metadata, consider fields such as:

```text
document_id
version_number
file_path/storage_key
file_name
mime_type
size
uploaded_by
created_at
notes
checksum nullable
```

Only ALTER what existing infrastructure actually lacks.

---

# 13. Entity: Design Package Submission

## Table

```text
design_package_submissions
```

## Purpose

Represents one formal submission round.

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_design_package_id | FK | required |
| design_package_assignment_id | FK | required |
| submission_number | integer | required |
| submitted_at | timestamp | required |
| submitted_by | FK users | nullable |
| status | string | required |
| notes | text | nullable |
| timestamps | timestamps | required |

Recommended submission statuses:

```text
submitted
under_review
reviewed
superseded
approved
```

Package status and submission status must not be conflated.

---

# 14. Entity: Submission Document

## Table

```text
design_package_submission_documents
```

## Purpose

Pin a submission to the exact document versions formally delivered.

## Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| design_package_submission_id | FK | required |
| document_version_id | FK | required |
| purpose | string | nullable |
| notes | text | nullable |
| timestamps | timestamps | required |

Unique:

```text
(submission_id, document_version_id)
```

---

# 15. Entity: Design Package Review

## Table

```text
design_package_reviews
```

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| design_package_submission_id | FK | required |
| reviewer_id | FK users | required |
| status | string | required |
| started_at | timestamp | nullable |
| completed_at | timestamp | nullable |
| summary | text | nullable |
| timestamps | timestamps | required |

Statuses:

```text
pending
in_review
completed
cancelled
```

Multiple reviews may exist if business requires multiple internal reviewers.

---

# 16. Entity: Design Review Finding

## Table

```text
design_review_findings
```

## Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| design_package_review_id | FK | required |
| design_package_scope_item_id | FK | nullable |
| document_version_id | FK | nullable |
| severity | string | required |
| status | string | required |
| title | string | required |
| description | text | required |
| resolution_notes | text | nullable |
| resolved_by | FK users | nullable |
| resolved_at | timestamp | nullable |
| timestamps | timestamps | required |

Severity:

```text
info
minor
major
critical
```

Status:

```text
open
addressed
accepted
waived
```

`waived` requires explicit authorization.

---

# 17. Entity: Design Package Revision

## Table

```text
design_package_revisions
```

## Purpose

Represents revision work after review findings.

## Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_design_package_id | FK | required |
| source_submission_id | FK | required |
| revision_number | integer | required |
| status | string | required |
| description | text | nullable |
| started_at | timestamp | nullable |
| ready_at | timestamp | nullable |
| created_by | FK users | nullable |
| timestamps | timestamps | required |

Statuses:

```text
draft
in_progress
ready
submitted
cancelled
```

---

# 18. Entity: Revision Finding Relation

## Table

```text
design_revision_findings
```

## Purpose

Records which findings a revision is intended to address.

## Fields

```text
id
design_package_revision_id
design_review_finding_id
notes nullable
timestamps
```

Unique pair required.

---

# 19. Resubmission Rule

There is **no separate resubmission table**.

When revision is ready:

```text
create a new design_package_submission
attach new exact document versions
link it to current assignment
mark revision submitted
update package status
```

Previous submission remains untouched.

---

# 20. Entity: Design Package Approval

## Table

```text
design_package_approvals
```

## Recommended Fields

| Field | Type | Nullability |
|---|---|---:|
| id | PK | required |
| project_design_package_id | FK | required |
| design_package_submission_id | FK | required |
| approved_by | FK users | required |
| approved_at | timestamp | required |
| notes | text | nullable |
| timestamps | timestamps | required |

Approval always identifies the exact approved submission.

Never approve only the abstract package without knowing which submission/version was approved.

---

# 21. Approval Conditions

Before final approval, validate:

```text
submission exists
review is complete
no unresolved blocking/major findings according to policy
document versions are accessible
reviewer/approver authorized
package belongs to same tenant/project
```

Do not automatically approve based only on all scope items being `ready`.

---

# 22. Closing Package

Package may move:

```text
approved → closed
```

Closing means the design process is operationally finished.

Do not delete assignments/submissions/findings after closure.

---

# 23. Important Notifications

## Notification Matrix

| Trigger | Recipients | Channel | Notes |
|---|---|---|---|
| Engineering office assigned | project manager/design responsible users | in-app | external office notification optional |
| Assignment replaced/cancelled | project/design responsible users | in-app | |
| Scope item reaches `ready` and completes assignment scope | responsible design users | in-app | avoid per-item noise unless important |
| Formal submission created | assigned reviewers + project manager | persistent in-app | important |
| Review assigned/started | reviewer | in-app | |
| Major/critical finding created | package responsible users + project manager | in-app | high priority |
| Revision required | project manager + assignment responsible users | in-app; external optional | |
| Revision ready | reviewers/design responsible users | in-app | |
| Resubmission created | reviewers | in-app | |
| Submission approved | project manager + design team + relevant downstream users | in-app | major lifecycle event |
| Package closed | project manager | in-app | |
| Target submission date overdue | responsible design users | scheduled in-app | condition watch/job |

Notifications must dispatch after successful commit.

---

# 24. Authorization / Filament Shield

Every new model requires Policy + Shield.

## Models / Policies

```text
ProjectDesignPackage            → ProjectDesignPackagePolicy
DesignPackageAssignment         → DesignPackageAssignmentPolicy
DesignPackageScopeItem          → DesignPackageScopeItemPolicy
DesignPackageActivity           → DesignPackageActivityPolicy
DesignPackageSubmission         → DesignPackageSubmissionPolicy
DesignPackageSubmissionDocument → DesignPackageSubmissionDocumentPolicy
DesignPackageReview             → DesignPackageReviewPolicy
DesignReviewFinding             → DesignReviewFindingPolicy
DesignPackageRevision           → DesignPackageRevisionPolicy
DesignRevisionFinding           → DesignRevisionFindingPolicy
DesignPackageApproval           → DesignPackageApprovalPolicy
```

Existing Document/DocumentVersion policies should be reused/extended.

---

# 25. Custom Workflow Permissions

At minimum consider:

```text
assign_design_package
replace_design_assignment
submit_design_package
review_design_submission
create_design_finding
waive_design_finding
request_design_revision
submit_design_revision
approve_design_package
close_design_package
```

Generic update must not imply approval or finding waiver.

---

# 26. Policy State Rules

Examples:

```text
approved submission:
  immutable.

closed package:
  no normal structural edits.

finding waiver:
  authorized action only.

approval:
  approver cannot act across tenant.

submission document:
  exact attached version cannot be silently swapped after submission.
```

---

# 27. Filament UX

Recommended Project Design area:

```text
Project
  └── Design Packages
       ├── Assignment
       ├── Scope
       ├── Activity
       ├── Submissions
       │    ├── Documents
       │    ├── Reviews
       │    └── Findings
       ├── Revisions
       └── Approval
```

Use tabs/relation managers/pages to keep workflow understandable.

Do not expose 11 unrelated menu items if they are primarily child workflow records.

---

# 28. Reactive Filament Behavior

Actions such as:

```text
assign office
mark scope ready
submit package
start review
add finding
request revision
create resubmission
approve
close
```

must update status/actions/badges without full page reload.

Use confirmation modals for irreversible/important state transitions.

---

# 29. Query Performance

List pages:

```text
load project
current/latest assignment
counts of scope/submissions/open findings
latest submission
```

Prefer:

```text
withCount()
latestOfMany()/ofMany() where appropriate
targeted eager loading
```

Do not load every historical submission and every document version on the package index.

Detail pages may load full timeline deliberately.

---

# 30. Indexes

Likely:

```text
project_design_packages(project_id, status)
design_package_assignments(package_id, status)
design_package_scope_items(assignment_id, status)
design_package_submissions(package_id, submission_number)
design_package_submissions(package_id, status)
submission_documents(submission_id, document_version_id)
design_package_reviews(submission_id, status)
design_review_findings(review_id, status)
design_review_findings(severity, status)
design_package_revisions(package_id, revision_number)
design_revision_findings(revision_id, finding_id)
design_package_approvals(package_id, submission_id)
```

---

# 31. Delete Rules

After formal submission:

```text
do not delete submission
do not replace attached version
do not delete completed review
do not delete findings merely because resolved
```

Draft activities/scope items with no downstream references may follow normal policy.

---

# 32. Financial Effect

Design phase can later generate:

```text
contracts
financial commitments
actual costs
payments
```

but design workflow status itself is not financial truth.

Do not mark cost as paid because design package is approved.

Budget/commitment integration belongs to Phase 04 and later financial modules.

---

# 33. Real-World Scenario

```text
Architectural Package
→ assigned to ABC Engineering Office
→ scope: plans + elevations + unit layouts
→ office uploads V1
→ Submission #1
→ review creates 4 findings
→ package revision_required
→ Revision #1 addresses all 4
→ V2 files uploaded
→ Submission #2
→ review passes
→ Approval links to Submission #2
→ package closed
```

Submission #1 and V1 remain fully queryable.

---

# 34. Tests

Minimum:

```text
tenant isolation
assignment history
scope belongs to assignment
submission numbering
exact document-version attachment
review lifecycle
finding lifecycle
revision-finding relation
resubmission creates new submission
old submission immutable
approval points to exact submission
package status transitions
custom permissions
Shield/Policy authorization
important notifications
overdue reminder job
N+1-sensitive list queries
```

---

# 35. Definition of Done

```text
design workflow preserves all rounds/history
document versions are immutable per submission
external engineering office assignment works
scope belongs to assignment
review/findings/revisions work
approval identifies exact submission
every model has Policy + Shield
important events notify correct recipients
Filament is reactive
queries are efficient
tests pass
```

---

# 36. Handoff

Approved design becomes input for:

```text
04-budgeting.md
05-construction.md
```
