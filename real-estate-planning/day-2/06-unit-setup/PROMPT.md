# EXECUTION PROMPT — Phase 06: Unit Setup

## Mission

Implement **Phase 06 only** according to `SPEC.md`.

Do not begin Marketing & Sales, Listings, Leads, Reservations, Contracts,
Installments, Payments, Handover, defects, or any later phase.

## Mandatory reading order

Read completely before implementation:

```text
real-estate-planning/README.md
real-estate-planning/00-domain-rules-and-global-conventions.md
real-estate-planning/00-existing-system-gap-analysis.md
real-estate-planning/day-1/01-property-acquisition/IMPLEMENTATION-REPORT.md
real-estate-planning/day-1/02-project-planning/IMPLEMENTATION-REPORT.md
real-estate-planning/day-1/03-design-engineering/IMPLEMENTATION-REPORT.md
real-estate-planning/day-1/04-budgeting/IMPLEMENTATION-REPORT.md
real-estate-planning/day-2/05-construction/IMPLEMENTATION-REPORT.md
real-estate-planning/day-2/06-unit-setup/SPEC.md
```

Then inspect actual migrations, Unit/UnitFeature models, Unit/Property/Project
Filament resources, documents/versions, policies, API consumers, leases,
maintenance, current roles/permissions, and test configuration. Planning files
are the target contract; repository inspection determines the safe migration.

## Non-negotiable reuse rules

```text
Reuse `units`; ALTER only. Never create actual_units / units_v2.
Reuse UnitFeature; do not create a second specifications/features domain.
Reuse images for gallery and Document → DocumentVersion for documents.
Reuse HasCompany, existing scopes, Shield, policies, notifications, and tests.
Planned Unit and Planned Unit Specification remain planning history.
```

`planned_unit_id` must remain nullable. Existing actual units without a plan,
including Units with leases/images/maintenance history, must continue working.

## Required implementation scope

```text
extend actual Unit identity, Project/Planned Unit traceability, actual area,
controlled status lifecycle/history, canonical plus custom UnitFeature UX,
Unit Ownership history, Unit documents/versions, UnitSetupService,
Project/Property/Unit Filament workspaces, policies/permissions,
after-commit notifications, MySQL/PostgreSQL tests, implementation report
```

## Planned → actual conversion

Implement a service-backed, transactional conversion action.

```text
approved unconverted Planned Unit
→ select a same-project active Property
→ prefill only initial values
→ user confirms actual physical values
→ create draft actual Unit
→ atomically link it and mark only Planned Unit as converted
```

Do not auto-create Units from Construction completion. Do not overwrite a
Planned Unit or its specifications. Do not silently copy arbitrary planning
specifications into actual Unit features.

## Status, commercial separation, ownership, and deletion

No generic mass assignment of Unit workflow status. `units.status` is the only
physical/operational field: `draft`, `ready`, `maintenance`, `inactive`. It is
a portable string column, controlled by the `UnitStatus` backed PHP enum and
the service transition map; it has no database enum/check constraint and no
second compatibility field.
`available`, `reserved`, and `occupied` must not survive as Unit status values:
they are commercial/listing, reservation, and derived tenancy concepts.

Perform the SPEC's full cutover: convert `units.status` to the portable string,
remove the PostgreSQL legacy check constraint when applicable, map every legacy
row, replace all legacy model scopes and Filament status controls, and refactor
Lease/API/reporting/seed/test uses in the same release. An active Lease determines tenancy; it must never
write `occupied`/`available` into a Unit. A ready Unit is not automatically
marketable or reservable. Do not create/alter commercial contracts,
reservations, or payments in this phase.

Before removing or changing any public API response field, inspect every
in-repository client and identify deployed external consumers. If an external
consumer cannot be released together, stop and obtain approval for the
SPEC-defined short deprecation adapter; do not silently retain the incorrect
domain model.

Ownership uses controlled replacement/history. Do not raw-edit/delete active
or historical ownership allocations. A valid active replacement totals exactly
100% and uses same-company Parties.

Delete only an empty draft Unit. Otherwise use inactive with a reason.

## Tenancy and Super Admin

Every Unit child inherits Company from its Unit/parent aggregate. For Super
Admin, require a valid parent context and derive Company server-side. Validate
every Property, Project, Planned Unit, Party, Document, and Version against
the Unit Company. Never rely only on a hidden field or UI selector.

Every standard relation-manager CreateAction must set `company_id` from the
parent in both required mutation hooks. Custom actions must derive it in the
service. Use `$data['company_id'] = ...`, never `+=`.

## Shield and Filament

UnitResource already exists. Generate/verify its Shield permissions instead of
creating a parallel resource. Seed required custom workflow permissions and
verify their exact names in the database/Roles UI.

The normal UX must be quick:

```text
Units inventory → fast actual setup
Project → Actual Units → convert plan / create actual unit
Property → Units → manual actual inventory
Unit Details → overview, planning comparison, features, ownership, documents,
status history, gallery, and existing operational context
```

Use semantic badges, tooltips, reactive modals, readable relationship labels,
clear danger/success notifications, and no forced reloads. Manually test all
reactive selectors using `$state` / `Forms\Set $set`, not invented callback
argument names.

## Tests and completion

Implement the full test matrix in the SPEC. At minimum test legacy null plan,
manual Unit, conversion, cross-company injection, Super Admin parent context,
features, status history, ownership history, documents, deletion protection,
authorization, notifications, legacy status cutover, derived tenancy, and
Lease/API/reporting compatibility.

Beside service/policy tests, add browser-like Filament/Livewire coverage for
every critical workflow: mount the real form/list/relation manager, assert the
critical UI contract, submit the actual action, and assert saved state and
validation. Add a separate realistic end-to-end business scenario which follows
the user journey through the UI rather than only invoking the service. For Unit
Setup this must cover Create form → draft Unit → Change status table action →
status history → Edit form, with direct status editing locked throughout.

Run clean migrations, phase tests, and full suites against:

```text
MySQL:      realState_test
PostgreSQL: realstate_pg_test (port 6000)
```

Before declaring completion:

```bash
php artisan shield:generate --all --option=permissions --panel=admin --no-interaction
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan app:backfill-company-roles
php artisan optimize:clear
php artisan filament:cache-components
```

Create `real-estate-planning/day-2/06-unit-setup/IMPLEMENTATION-REPORT.md`
from the supplied template, report both database results, then STOP. Do not
commit automatically and do not start Phase 07.
