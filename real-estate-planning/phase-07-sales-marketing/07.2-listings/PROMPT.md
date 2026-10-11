# Phase 07.2 — Listings Management Agent Prompt

Implement Phase 07.2 — Listings Management for the existing multi-tenant Real Estate SaaS.

Before changing code:

1. Read `../SPEC.md` for Phase 07 global commercial rules.
2. Read this folder's `SPEC.md` completely.
3. Read the repository's global conventions/planning files.
4. Inspect the existing codebase, migrations, models, enums, company scope, roles/permissions, audit system, Filament conventions, Planned Unit model, Unit model, company settings, and existing lease/rent frequency enums.

## Implementation Rules

- Reuse existing repository architecture.
- Do not introduce a new DDD folder structure unless the project already uses it.
- Reuse existing `HasCompany`, permissions, audit, company settings, Unit/Planned Unit relations, and semantically compatible enums.
- All new `status` and `type` fields must use PHP Backed Enums.
- Do not use magic status/type strings in services/actions.
- Keep workflow and tenant validation in the application's existing service/domain pattern, not only in Filament.
- Do not implement future Phase 07 modules.

## Required Functionality

Implement exactly what is defined in this phase's `SPEC.md`, including:

- `listings`
- polymorphic target restricted to Planned Unit / Actual Unit through stable Morph Map aliases
- multiple Listings per Unit, including same type
- Sale / Rent conditional validation
- `ListingStatus` workflow
- explicit Publish / Pause / Archive / Restore actions
- minimum marketability checks needed by Listings
- company policy for maintenance-unit marketing when needed
- `listing_price_histories`
- immutable/read-only price history
- permissions
- Filament Listing management
- audit
- multi-tenant ownership validation
- MySQL and PostgreSQL tests

## Important Behavior

Do not add `reserved`, `sold`, `rented`, or `occupied` to Listing lifecycle or physical Unit status.

A confirmed Reservation in a future phase may make a unit derived as not reservable while the Listing remains `published`.

Do not automatically re-point an old Planned Unit Listing to an Actual Unit when the Actual Unit is created.

Do not create a unique constraint preventing multiple Listings for the same Unit/type.

Do not implement Facebook, Instagram, WhatsApp, or `listing_publications` here.

Do not implement Leads, Opportunities, Activities, Offers, Reservations, Reservation Snapshots, Change Reviews, Contracts, or AI automation.

## Validation

Implement Sale/Rent rules outside Filament as well as in the dynamic UI.

Reject cross-company target relationships even if IDs are manually submitted.

Status must not be directly writable through generic edit forms/API payloads.

## Testing

Add the tests required by `SPEC.md`.

Run:

- focused 07.2 tests on MySQL
- focused 07.2 tests on PostgreSQL
- broader affected suite on MySQL
- broader affected suite on PostgreSQL where the repository's test setup supports it

Do not report completion while relevant failures remain.

## Final Report

At completion, provide:

1. files added/changed
2. migrations added
3. enums added/reused
4. company settings added/reused
5. permissions added
6. workflow actions implemented
7. tests added
8. MySQL results
9. PostgreSQL results
10. any repository details that required adapting the written spec
