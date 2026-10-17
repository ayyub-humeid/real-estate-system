# Phase 07.6 — Reservations & Unit Snapshots: Implementation Report

## Delivered

- Added the `UnitReservation` aggregate with two explicit sources: a direct
  reservation and a reservation derived from an accepted Offer.
- Added the protected lifecycle: draft → pending → active, plus reject,
  cancel, extension, and expiry. Generic status editing is not exposed.
- Added automatic company/year-scoped `RES-YYYY-######` reference generation.
  A locked Company row serializes number allocation without `MAX(id)`.
- Approval/activation locks the Reservation and Unit, then checks for an
  existing active reservation inside the same transaction. Pending records do
  not block the Unit.
- Approval atomically creates one immutable Unit and commercial-terms snapshot
  and a status-history row. Live Unit changes do not rewrite the snapshot.
- Added `reservations:expire-due`, scheduled every five minutes. It uses row
  locks and records a system-originated expiry history entry without
  impersonating a user.

## Database

`2026_10_17_000000_create_unit_reservations_domain_tables.php` adds:

- `unit_reservation_number_sequences`
- `unit_reservations`
- `reservation_snapshots`
- `reservation_status_histories`

The migration uses portable strings, decimal values, JSON, foreign keys, and
short explicit index names compatible with MySQL and PostgreSQL.

## Authorization and isolation

- `UnitReservation` is a top-level Shield resource using the generated
  `unit::reservation` permission names.
- Workflow permissions are custom and seeded: send for approval, approve and
  activate, reject, cancel, extend, and expire.
- Policies and `ReservationWorkflowService` authorize server-side. All Units,
  Parties, Offers, Opportunities, and derived Offer revisions are explicitly
  company-scoped when global scopes are bypassed.
- Accepted Offers derive their agreed Revision and actual Unit from the Offer;
  users cannot provide arbitrary negotiated terms or a different Unit.

## Filament and localization

- Added a localized Reservations resource in CRM with source-specific forms,
  state-aware list and view actions, draft-only edit/delete, immutable
  snapshot details, and an audit-only status history relation tab.
- Added Arabic and English translations for static labels, enum values,
  workflow controls, history, validations, and scheduler output.
- Reservation workflow permissions are grouped under Reservations on the
  existing Role custom-permissions UI.

## Verification

MySQL `realState_test`:

- Phase 07.6 tests: **7 tests / 42 assertions passed**.
- Related CRM, Activities, and Offers regression selection: **24 tests / 75
  assertions passed**.
- Complete suite, run in safe batches: **128 tests / 640 assertions passed**.

PostgreSQL `realstate_pg_test` (port 6000):

- Phase 07.6 tests: **7 tests / 42 assertions passed**.
- Related CRM, Activities, and Offers regression selection: **24 tests / 75
  assertions passed**.
- Complete suite, run in safe batches: **128 tests / 640 assertions passed**.

## Intentional boundaries

No Contract, Lease, payment, installment, invoice, ownership-transfer, or
handover data is created by this phase. An accepted Offer does not itself
reserve a Unit; activation is the only operation that creates the hold.
