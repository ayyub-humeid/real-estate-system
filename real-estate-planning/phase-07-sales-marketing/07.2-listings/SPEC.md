# Phase 07.2 — Listings Management SPEC

## Goal

Implement the Listings domain for the existing multi-tenant Real Estate SaaS.

A Listing is the commercial/marketing representation of either:

- a Planned Unit; or
- an Actual Unit.

A Listing is **not** the source of truth for reservation, occupancy, sale completion, contracts, or physical Unit status.

---

# 1. Listing Model

Create a `listings` table/model following existing project conventions.

Logical fields:

```text
id
company_id

listable_type
listable_id

listing_type

title
description

asking_price
currency

rent_frequency          nullable
security_deposit        nullable
minimum_lease_months    nullable

availability_notes      nullable

status

published_at nullable
paused_at    nullable
archived_at  nullable

created_by
updated_by nullable

created_at
updated_at
```

Use Laravel polymorphic relation:

```text
Listing
  morphTo listable
    ├── Planned Unit
    └── Unit
```

Use an explicit Morph Map with stable aliases such as:

```text
planned_unit
unit
```

Do not persist PHP FQCNs in `listable_type`.

A Listing references exactly one commercial subject.

---

# 2. Planned → Actual Behavior

Do not automatically re-point an old Planned Unit Listing to the generated Actual Unit.

The existing relationship remains:

```text
units.planned_unit_id
```

A company may later create a new Listing for the Actual Unit, or a future explicit business workflow may migrate/transition a Listing while preserving history.

Do not silently update the polymorphic target.

---

# 3. Multiple Listings

Allow multiple active Listings for the same Unit, including multiple Listings of the same type.

Valid example:

```text
Unit A-501
├── Sale Listing #10 — $95,000
├── Sale Listing #20 — $98,000
└── Rent Listing #30 — $800/month
```

Do not add a uniqueness constraint preventing this.

Each Listing owns independent:

- title/description
- lifecycle
- asking price
- price history

Commercial availability remains shared by the underlying real-world unit.

---

# 4. PHP Backed Enums

Implement/reuse PHP Backed Enums for all new status/type fields.

## ListingStatus

```text
draft
published
paused
archived
```

## ListingType

```text
sale
rent
```

## RentFrequency

Inspect the existing project before adding a new enum.

If an existing Lease/Rent frequency enum has the correct domain semantics, reuse it.

Do not duplicate enums unnecessarily.

---

# 5. Listing Workflow

Allowed transitions:

```text
draft -> published
published -> paused
paused -> published
published -> archived
paused -> archived
archived -> draft  (Restore)
```

Do not allow:

```text
archived -> published directly
```

The `status` field is workflow-controlled.

It must not be directly editable through normal CRUD forms, generic API updates, or a status dropdown.

Use explicit domain/service actions:

- Publish
- Pause
- Archive
- Restore

Each transition validates:

- current status
- permission
- current company/tenant
- related record ownership
- required business conditions

Reuse existing audit infrastructure.

Do **not** add these Listing statuses:

```text
reserved
sold
rented
occupied
```

These belong to other domains or are derived commercial availability.

---

# 6. Sale vs Rent Validation

Common required fields:

```text
listing_type
title
asking_price
currency
```

`asking_price` must be greater than zero.

## Sale

When `listing_type = sale`:

- `rent_frequency` must be prohibited/empty
- rent-only fields must not retain stale values

## Rent

When `listing_type = rent`:

- `rent_frequency` is required
- `security_deposit` may be nullable unless an existing/current company policy requires it
- `minimum_lease_months` may be nullable unless an existing/current company policy requires it

Validation must exist in application/domain/request code, not only in Filament.

Filament should dynamically show/hide rent fields and clear invalid stale values when type changes.

---

# 7. Marketability

Publishing a Listing must validate that the underlying Planned/Actual Unit is commercially marketable.

Implement only the minimum Commercial Foundation required by Listings.

Do not pre-build Reservations or Contracts availability logic.

For existing Actual Unit statuses:

```text
ready       -> marketable
inactive    -> not marketable
maintenance -> follow company policy
draft       -> marketable unless an existing domain restriction forbids it
```

For Planned Units:

- inspect and respect the existing Planned Unit workflow/statuses
- do not invent a new lifecycle

If the setting does not already exist, add the current-phase setting through a migration:

```text
allow_maintenance_unit_marketing
```

Follow the existing `company_settings` schema/style.

---

# 8. Price History

Create `listing_price_histories`.

Logical fields:

```text
id
listing_id
old_price nullable
new_price
old_currency nullable
new_currency
old_rent_frequency nullable
new_rent_frequency nullable
changed_by
reason nullable
created_at
```

Rules:

- Store the initial pricing state as the first history row on Listing creation.
- When `asking_price`, `currency`, or `rent_frequency` changes, update the Listing and insert history in the same DB transaction.
- Do not create a history row when none of the tracked pricing fields changed.
- Price History is immutable/read-only after creation.
- Changing Listing pricing must not automatically modify Offers, Reservations, Snapshots, or Contracts.

---

# 9. Multi-Tenancy / Company Safety

Reuse existing `HasCompany` / current-company infrastructure.

A user must never create/update/publish a Listing referencing a Planned Unit or Actual Unit belonging to another Company.

Do not rely only on Filament relationship scoping.

Validate tenant ownership in the domain/application/service layer as well.

---

# 10. Permissions

Inspect and follow the existing permission naming/provisioning conventions.

Add/reuse permissions equivalent to:

```text
view listings
create listings
update listings
publish listing
pause listing
archive listing
restore listing
change listing price
```

Do not create a parallel role/permission system.

---

# 11. Filament

Implement `ListingResource` following existing project patterns.

## Form

Include:

- Planned/Actual target selector
- Listing Type
- title
- description
- asking price
- currency
- conditional rent fields
- availability notes

Status must be read-only.

Use explicit workflow actions for Publish/Pause/Archive/Restore.

## Table

Show useful columns such as:

- title
- target unit
- Planned vs Actual
- Sale vs Rent
- asking price
- status
- relevant timestamps

Filters:

- status
- listing type
- Planned/Actual target type

## Price History UI

Provide read-only Price History display, e.g. Relation Manager or the repository's existing pattern.

---

# 12. Audit

Reuse existing audit infrastructure.

Record important events such as:

- Listing created
- Published
- Paused
- Archived
- Restored
- Price changed

---

# 13. External Social Publishing Is Out of Scope

Do not implement in 07.2:

```text
Facebook integration
Instagram integration
WhatsApp integration
listing_publications
Meta API
webhooks
channel synchronization
```

These belong to Phase 07.7.

`Listing.status = published` means active inside the SaaS only.

---

# 14. Explicitly Out of Scope

Do not create or implement:

```text
leads
opportunities
activities
offers
reservations
reservation_snapshots
reservation_change_reviews
contracts
listing_publications
social conversations
AI automation
```

Each belongs to its later sub-phase.

---

# 15. Tests

Add focused automated tests for at least:

```text
Create Sale Listing for Planned Unit
Create Sale Listing for Actual Unit
Cross-company target rejection
Sale conditional validation
Rent conditional validation
Multiple Sale Listings for same Unit
Multiple Rent Listings for same Unit
Publish inactive Unit rejected
Maintenance publishing follows company policy
Allowed workflow transitions
Invalid workflow transitions rejected
Permission failures
Initial Price History created
Price update writes history
Currency/frequency change writes history
No duplicate history when pricing did not change
Listing A price update does not affect Listing B
Morph aliases stored instead of class names
```

Run relevant tests against both MySQL and PostgreSQL.

Also run the broader affected suite before closing the phase.
