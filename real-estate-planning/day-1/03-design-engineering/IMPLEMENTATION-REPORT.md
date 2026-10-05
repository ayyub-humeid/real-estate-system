# Phase 03 — Design, Engineering, Review & Approvals: Implementation Report

## Status

Implemented. Phase 03 remains limited to design/engineering workflow; it does
not create budgets, commitments, construction records, marketing, or sales.

## Implemented domain

The following company-owned models and tables were added:

- `ProjectDesignPackage` / `project_design_packages`
- `DesignPackageAssignment` / `design_package_assignments`
- `DesignPackageScopeItem` / `design_package_scope_items`
- `DesignPackageActivity` / `design_package_activities`
- `DocumentVersion` / `document_versions`
- `DesignPackageSubmission` / `design_package_submissions`
- `DesignPackageSubmissionDocument` / `design_package_submission_documents`
- `DesignPackageReview` / `design_package_reviews`
- `DesignReviewFinding` / `design_review_findings`
- `DesignPackageRevision` / `design_package_revisions`
- `DesignRevisionFinding` / `design_revision_findings`
- `DesignPackageApproval` / `design_package_approvals`

`documents` is now the logical-document model only: title, description,
creator, and its package relationship. All physical file metadata and storage
paths were removed from it. `document_versions` stores immutable file versions;
formal submission rows point to exact version IDs through
`design_package_submission_documents`.

## Workflow and history guarantees

`DesignEngineeringService` is the single server-side workflow path. It handles:

1. package creation under an existing Project;
2. external engineering-office assignment and replacement history;
3. assignment-owned scope and activity records;
4. versioned documents;
5. numbered formal submissions;
6. reviewer assignment, review completion, and findings;
7. revision creation linked to the source submission and selected findings;
8. resubmission as a new submission, never an overwrite;
9. exact-submission approval; and
10. package closure only after approval.

Workflow status is deliberately absent from normal mass-assignable package
attributes. Generic edit forms cannot jump a package to approval/closure.
Completed review history, finding history, submission-document links, and
approval records are not editable/deletable through normal CRUD actions.

Approval requires a reviewed submission, a completed review, exact attached
versions, and no open/addressed major or critical finding. It records the exact
approved submission.

## Tenant isolation and authorization

- Every new Phase 03 table has `company_id` and uses the existing `HasCompany`
  global scope.
- The service explicitly checks the parent Project/Package company for office,
  reviewer, scope, document-version, submission, finding, revision, and
  approval relationships.
- Super Admin access follows the existing record-parent company context rules.
- Each Phase 03 model has a dedicated policy.
- `ProjectDesignPackageResource` is a hidden top-level resource so Shield owns
  its standard CRUD permissions; the workflow stays in **Project → Design
  Packages**.
- Relation-manager/workflow permissions are custom underscore permissions and
  are seeded by `RolesAndPermissionsSeeder`.

Shield generated these standard names for the three-word resource:

```text
view_project::design::package
view_any_project::design::package
create_project::design::package
update_project::design::package
...standard Shield actions
```

This was verified rather than inferred from two-word resource examples.

## Filament UX

The Project View/Edit pages now include a **Design Packages** relationship.
Its grouped actions follow the real workflow:

```text
Prepare:          assign office, scope, document versions
Review:           submit, start/complete review, findings, waiver
Formal workflow:  request/start/ready revision, resubmit, approve, close
```

Actions call the service only, show success/error/authorization notifications,
and use the current Livewire relation-manager state rather than forcing a page
reload.

## Notifications

Important assignment, ready-scope, submission, review, major/critical finding,
revision, approval, and closure events notify active Project members plus the
acting user when necessary. All notifications are scheduled with
`DB::afterCommit()`.

## Database compatibility

The migration uses Laravel Schema Builder and explicit short foreign-key/index
names where MySQL's 64-character identifier limit would otherwise be exceeded.
It also corrects the pre-existing tenant migration so a clean database can
handle either historical `tenants.user_id` index shape before creating its
composite unique key. A follow-up migration converts the legacy Documents table
to the logical/versioned shape and intentionally removes the confirmed dummy
legacy attachment metadata and UI/API flow.

## Test coverage

`tests/Feature/DesignEngineeringWorkflowTest.php` covers:

- end-to-end assignment → scope → V1 submission → review/finding → revision
  → V2 resubmission → exact approval → closure;
- submission numbering and preserved V1/V2 history;
- scope-to-assignment ownership and assignment replacement history;
- cross-company office rejection and cross-package document-version rejection;
- generic Document policy protection for design documents across companies;
- required ready scope before submission;
- unauthorized package creation;
- Super Admin parent-company operation and normal-user missing-context denial;
- generic status mass-assignment protection; and
- blocked approval with an open critical finding.

## Verification results

| Database | Command | Result |
|---|---|---|
| MySQL `realState_test` | clean migrate + `php artisan test --filter=DesignEngineeringWorkflowTest` | PASS — 7 tests, 21 assertions |
| PostgreSQL `realstate_pg_test` (port 6000) | clean migrate + `vendor/bin/phpunit --configuration phpunit.pgsql.xml --filter DesignEngineeringWorkflowTest` | PASS — 7 tests, 21 assertions |

Full suites were also run on both engines. The only failures are the known,
pre-existing `LeaseBalanceTest` mismatch/overpayment failures; the Phase 03
tests pass in both full runs.

## Required release commands

Run once in the target production environment, after deployment and before
assigning the new permissions to roles:

```bash
php artisan migrate --force
php artisan shield:generate --resource=ProjectDesignPackageResource --option=permissions --panel=admin --no-interaction
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan optimize:clear
php artisan filament:cache-components
```

Then use the existing Roles page to assign the Shield-generated package
permissions and the seeded custom design workflow permissions to the intended
company roles.
