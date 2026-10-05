# Phase 04 — Project Budgeting & Financial Control: Implementation Report

## Delivered scope

Phase 04 implements the project cost-control chain:

```text
Approved budget plan → Financial commitment → Approved actual cost → Completed payment allocation
```

It deliberately does not implement customer receivables, lease schedules, or
installments. Those belong to Phase 08 and will extend the same `payments` /
`payment_allocations` foundation.

## Database

Migration `2026_10_06_000000_create_project_budgeting_domain_tables.php`:

- adds `projects.currency` as the project accounting currency;
- replaces the legacy lease-obligation `payments` table with canonical cash
  movement `payments`;
- adds `project_budgets`, `budget_categories`, `budget_lines`, `budget_items`;
- adds `financial_commitments` and `financial_commitment_amendments`;
- adds `actual_costs` and `payment_allocations`;
- includes project/company/status/party/line and allocation indexes used by
  project financial queries.

`budget_lines` is the stable identity across budget revisions. Historical
commitments and actual costs keep their original item snapshot and are never
re-pointed during a revision.

## Domain and workflow

- Budget versions are draft → pending approval → approved/superseded, with
  return-to-draft, cancellation, and transactional single-current-baseline
  handling.
- A project may have only one draft version at a time; categories may only
  parent categories in the same budget version.
- Budget items preserve planned amounts in project currency.
- Commitments support budgeted/unbudgeted entries, optional allow-listed
  workflow sources, over-budget authorization with a mandatory reason,
  release/cancellation, and controlled amendments.
- Actual costs require a same-company project/vendor context, explicit
  submission and approval, and corrective offset records instead of raw edits
  after approval.
- Outgoing Phase 04 payments require a project and vendor. Allocation locks
  payment and actual-cost rows, enforces completed/approved state plus same
  company/project/vendor, and guards both payment and cost balances.
- Original currency values and immutable project-currency snapshots are kept
  separately. Project Paid is derived only from
  `payment_allocations.project_amount`, never from mixed original currencies.
- Project currency locks once any budget or financial record exists.

## Authorization and tenancy

- Reused `HasCompany`, global scopes, Company/Super Admin context, and the
  existing company-owned policy helpers.
- Added policies for every new Phase 04 model.
- Added Shield-compatible top-level resource policy names:
  `project::budget`, `financial::commitment`, `actual::cost`, and `payment`.
- Added and seeded custom relation/workflow permissions for categories, items,
  amendments, allocations, lifecycle actions, corrections, and voids.
- Financial-manager template roles now receive Phase 04 permissions while
  platform-only permissions remain excluded from company roles.

## Filament UX

- Project View now has **Budgets**, **Financial Commitments**, **Actual Costs**,
  and **Payments** workspaces.
- A budget version opens a dedicated hidden-resource workspace with separate
  category and item management tabs.
- Lifecycle actions are reactive and service-backed: submit/approve/return/
  cancel/revise budgets; commit/amend/approve-amendment/release/cancel
  commitments; submit/approve/correct actual costs; record/allocate/void
  payments.
- Financial history has no raw update/delete action after the relevant
  business state. Draft budget structure remains editable.

## Legacy cutover

The old `Payment` model represented future lease obligations, not cash. It was
retired from lease balance calculations, tenant relation managers, dashboard
calculations, and overdue checks so no page treats Phase 04 project payments
as rent installments. Tenant schedule/checkout endpoints return a clear 410
response until Phase 08 introduces the proper billing model. The obsolete
lease-balance test was removed for the same reason.

## Verification

### MySQL — `realState_test`

- `migrate:fresh --env=testing`: passed from a clean database.
- `BudgetingWorkflowTest`: **9 passed, 27 assertions**.
- Full suite validated in three isolated groups to avoid a local lingering
  PHPUnit process competing for the shared MySQL test schema:
  **63 passed, 307 assertions**.

### PostgreSQL — `realstate_pg_test` (port 6000)

- clean `migrate:fresh` passed using explicit PostgreSQL test configuration;
- `BudgetingWorkflowTest`: **9 passed, 27 assertions**;
- `phpunit.pgsql.xml` pins the database to `realstate_pg_test` on port 6000.

## Deployment reminder

Before deploying Phase 04, run Shield generation for the four top-level
resources and seed role templates in the target environment:

```bash
php artisan shield:generate --resource=ProjectBudgetResource --option=permissions --panel=admin --no-interaction
php artisan shield:generate --resource=FinancialCommitmentResource --option=permissions --panel=admin --no-interaction
php artisan shield:generate --resource=ActualCostResource --option=permissions --panel=admin --no-interaction
php artisan shield:generate --resource=PaymentResource --option=permissions --panel=admin --no-interaction
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan app:backfill-company-roles
```

## Phase status

**READY TO CLOSE** — Phase 04 is complete. Phase 05 has not been started.
