# Phase 07.7 — Contracts & Agreements Engine: Implementation Report

## Status

READY TO CLOSE

## Delivered domain

- Added the independent `Contract` domain for direct, reservation-sourced,
  sale, off-plan sale, and lease agreements. The legacy Lease and payment
  domains were not used as the source of truth and no installment/payment
  generation was introduced.
- Added immutable contract versions, per-version party snapshots, operative
  terms, template versions, approvals, signatures, status-history entries,
  and formal amendments.
- Added company-scoped, transaction-safe contract and template-version number
  allocation. Version creation locks its parent Contract or Template so the
  next number is safe on both MySQL and PostgreSQL.
- Added backed PHP enums and Eloquent casts for contract type and all workflow
  status fields. Services compare enum cases (and query with `->value`), never
  raw workflow strings.

## Workflow and safeguards

- Draft versions are the only editable versions. Submit freezes a version;
  review can approve or request a revision; a revision copies parties and terms
  into a new independent draft while retaining the frozen source version.
- A legal document attaches only to the exact approved version. Each signature
  is tied to that exact party, version, document version, SHA-256 digest of the
  stored file bytes, evidence, and optional signature image.
- Activation requires an approved version, every party signature, tenant
  ownership, and an availability recheck under row locks.
- Sale and off-plan-sale conflicts are prevented across mapped actual/planned
  targets. Lease overlap is rejected while consecutive lease periods are
  allowed.
- Amendments clone the signed version without changing it. The currently
  active agreement remains active while its amendment is submitted, reviewed,
  revised, and re-approved; only the amendment version changes state.

## Filament and access control

- Added `ContractResource` and `ContractTemplateResource`, with localized
  overview, workflow actions, relation-manager tabs, document/signature flow,
  approval/audit views, and amendment creation.
- Added read-only contract history to Unit, Reservation, and Project views.
- The Contract form now uses a dependent actual-unit selector: it loads only
  units belonging to the selected Property and clears a stale unit/reservation
  selection. This is one reactive request per Property selection, not a query
  for every search keystroke. Server-side company/property validation remains
  mandatory as the security boundary.
- Generated Shield permissions for the two top-level resources and seeded the
  custom version, signature, document, activation, amendment, and audit
  permissions locally.

## Database changes

- `contracts`
- `contract_versions`
- `contract_parties`
- `contract_terms`
- `contract_templates`
- `contract_template_versions`
- `contract_approvals`
- `contract_signatures`
- `contract_status_histories`
- `contract_amendments`
- A follow-up migration adds the optional `signature_image_path` safely after
  the workflow tables, keeping fresh and upgraded schemas consistent.

## Verification

- MySQL (`realState_test`): `ContractFoundationTest` passed — 11 tests, 51
  assertions. Full Laravel regression completed successfully.
- PostgreSQL (`realstate_pg_test`): clean `migrate:fresh` passed, then
  `ContractFoundationTest` passed — 11 tests, 51 assertions. Full compact
  Laravel regression completed successfully.
- Resource routes were registered and Laravel/Filament caches were cleared
  after generating the new permissions.

## Remaining boundary

Phase 07.8 remains responsible for payment schedules, receivables,
installments, collection, and payment processing. Phase 07.7 stores only the
approved commercial and legal agreement facts required for that future engine.
