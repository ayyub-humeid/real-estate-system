# Phase 05 — Design packages: simple drawing versions with approve / reject

_Status: approved by the developer on 2026-11-21. Existing design data is dummy and is removed._

## Why

The old design domain was architecture-firm tooling: 11 tables (assignments, scope items,
activities, submissions, submission documents, reviews, review findings, revisions, revision
findings, approvals) and ~13 actions per package. A real-estate developer only needs to follow
the drawing versions an engineering office sends and approve or reject each one.

## The new domain

**Design package**: one per discipline/deliverable of a project.
Fields: name, discipline, engineering office (a party), target date, notes, status.

**Design package version**: one per upload, numbered automatically (V1, V2…).
One file per version (a combined PDF or a ZIP), stored through the shared Document /
DocumentVersion system so downloads and permissions keep working. Holds the decision:
`pending` (awaiting decision), `approved`, `rejected` (comment required) or `superseded`
(a newer version was uploaded before a decision).

**Package status**, computed from the latest version, never set by hand:
`no_drawings` → `awaiting_decision` → `changes_requested` ↺ → `approved`.

## Actions (3)

1. **Upload new version**: file + note. Any still-pending version becomes `superseded`.
2. **Approve / Reject**: on the latest pending version only. Reject needs a comment.
3. **Edit package**: name, discipline, office, target date, notes.

No "submit to a manager" step: uploading is the submission.

## Rules

- Versions are immutable, the record of what was approved.
- Only the latest version can be decided, and only once.
- Uploading after an approval re-opens the package; the earlier approval stays in history.
- Closed or cancelled projects refuse all design changes.
- A package can be deleted only while it has no versions.
- Permissions: Shield CRUD on `project::design::package` plus custom
  `upload_design_version` and `decide_design_version`.

## Action audit (AGENTS.md)

| Screen | List | View | Create | Edit | Delete | Workflow |
|---|---|---|---|---|---|---|
| Project → Design packages tab | ✓ | Open → package page | ✓ (service sets `company_id` from the project) | ✓ | ✓ only without versions | — |
| Package page | — | ✓ with version history | — | ✓ | — | Upload, Approve, Reject |

Intentionally excluded: editing or deleting a version, and un-deciding a decision. A version
is the record of what was sent and approved; a correction is always a new version.

## Removed

The 10 tables above with their models, policies, permissions, translations and tests.
Financial commitments can no longer point at a design assignment; design sources on existing
commitments are cleared because the packages themselves are recreated.
