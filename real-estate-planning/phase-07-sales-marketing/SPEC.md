# Phase 07 — Sales & Marketing Engine

## Purpose

Phase 07 builds the commercial sales and marketing engine for the existing multi-tenant Real Estate SaaS.

This phase does **not** replace the physical Unit domain, lease/contract domain, or company tenancy/permissions infrastructure. It sits above them and coordinates marketing, CRM, negotiation, reservation, and later social-channel activity.

Primary commercial flow:

```text
Planned / Actual Unit
        ↓
      Listing
        ↓
       Lead
        ↓
   Opportunity
        ↓
Activities / Follow-ups
        ↓
Offer / Counteroffer
        ↓
Reservation / Pre-Reservation
        ↓
Phase 08 — Contracts & Payments
```

## Phase 07 Sub-Phases

- 07.1 — Commercial Foundation (domain decisions only; no standalone implementation required)
- 07.2 — Listings Management
- 07.3 — Leads, Opportunities & CRM Kanban
- 07.4 — Activities & Follow-ups
- 07.5 — Offers & Negotiations
- 07.6 — Reservations & Pre-Reservations
- 07.7 — Social Channels & Conversations
- 07.8 — Automation, AI & Analytics

---

# Global Commercial Decisions

## 1. Commercial Availability Is Contextual

Do not use one generic commercial status such as `available/unavailable`.

The system must treat these as separate concepts:

- `marketable` — can the unit be marketed?
- `reservable` — can a customer obtain an exclusive reservation now?
- `contractable` — can the unit legally/commercially proceed to contract?

`contractable` becomes authoritative in Phase 08.

Commercial availability is a derived result, not a physical Unit status.

Never add `reserved`, `sold`, `rented`, or `occupied` to the existing physical `units.status`.

Existing physical Unit statuses remain:

```text
draft
ready
maintenance
inactive
```

---

## 2. Planned ↔ Actual Unit Identity

Reuse the existing relationship:

```text
units.planned_unit_id
```

Rules:

- Independent Actual Unit (`planned_unit_id = null`) is commercially evaluated on its own.
- Actual Unit derived from Planned Unit must consider both Actual and Planned commercial commitments.
- Planned Unit that later has an Actual Unit must also consider the Actual Unit's commitments.
- Do not add a new `commercial_assets` table only to solve this relationship.

When reservation concurrency is implemented in 07.6, operations that represent the same real-world unit must lock the same canonical record inside a DB transaction before rechecking availability and inserting a reservation.

---

## 3. Source of Truth

Each domain owns a different truth:

| Domain | Source of truth for |
| --- | --- |
| Unit / Planned Unit | current physical/planned specifications and physical state |
| Listing | marketing presentation, asking price, publication lifecycle |
| Offer | customer negotiation terms |
| Reservation | temporary commercial exclusivity and reserved terms |
| Contract (Phase 08) | final legal/commercial obligations |

Do not automatically synchronize values between these domains in ways that erase history.

Examples:

- Changing Listing price must not change an existing Offer, Reservation, or Contract.
- A confirmed Reservation must not change Unit physical status.
- A Listing may remain `published` while the underlying unit is derived as `Reserved`.

---

## 4. Multiple Listings Are Allowed

A single Planned or Actual Unit may have multiple Listings, including multiple Listings of the same type.

Examples:

```text
Unit A-501
├── Sale Listing #1 — $95,000
├── Sale Listing #2 — $98,000
└── Rent Listing #3 — $800/month
```

Each Listing has independent:

- lifecycle
- asking price
- price history
- title/description
- target audience/campaign context

All Listings that represent the same real-world unit share the same underlying commercial availability.

---

## 5. Reservation Exclusivity

A confirmed Reservation is exclusive for the underlying real-world unit.

A confirmed reservation from one Listing must prevent a second confirmed reservation through another Listing representing the same unit.

A Listing may remain published while reservation availability becomes false.

The UI may show a derived badge such as `Reserved` while still accepting inquiries or Waiting List interest.

Reservation exclusivity details are implemented in 07.6.

---

## 6. Snapshots & Change Reviews

When customer-agreed terms are created in later commercial stages, preserve immutable snapshots.

The Unit/Planned Unit stores the **current** specifications.

An Offer/Reservation Snapshot stores **what was agreed at that time**.

For Reservation changes, use versioned snapshots conceptually:

```text
Reservation Snapshot V1
→ material Unit change detected
→ Change Review
→ customer/company decision
→ if approved, create Snapshot V2
```

Do not modify old approved snapshots.

A Unit change must not silently rewrite a customer's agreed terms.

Material change examples may include:

- area
- bedrooms
- floor
- unit type
- agreed/expected delivery information
- other commercially significant specifications

Implementation belongs to the relevant later sub-phase, especially 07.5/07.6.

---

## 7. Company Policies

Business invariants are fixed in domain logic.

Examples:

- no cross-company relationships
- no double booking
- no direct editing of workflow-controlled statuses
- immutable approved snapshots
- permission enforcement

Company preferences are configurable through the existing `company_settings` infrastructure.

When a sub-phase needs a new company-specific setting, add it immediately through a migration. Future phases can add more settings through later migrations.

Do not build a generic settings engine unless the current repository already has one.

---

## 8. Cross-Company Safety

All new Phase 07 records must respect the current Company / tenant context.

A record from Company A must never reference a Unit, Planned Unit, Lead, Opportunity, Offer, Reservation, etc. owned by Company B.

Reuse existing:

- `HasCompany`
- current-company context
- roles/permissions infrastructure

Do not rely only on Filament query scoping. Validate tenant ownership in the domain/application/service layer too.

---

## 9. Workflow Status Protection

Workflow-controlled statuses must never be manually editable through normal CRUD forms, generic edit actions, or direct status dropdowns.

Status changes happen only through explicit domain actions.

Each transition must validate:

- current status allows the transition
- authenticated user has required permission
- record belongs to correct Company
- required related records and conditions exist
- cross-company relationships are rejected

Use DB transactions when multiple records are affected.

Reuse existing audit infrastructure.

Any new `status` or `type` field in Phase 07 must use PHP Backed Enums. Avoid magic strings.

---

## 10. Off-Plan and Actual Units

Planned Units may be marketed and may later support Pre-Reservation.

Actual Units may be marketed and support standard Reservation.

Do not assume marketing must wait until construction is complete.

When a Planned Unit becomes an Actual Unit:

- preserve Listing history
- preserve Offer/Reservation snapshots
- do not automatically erase or rewrite prior commercial records
- continue treating Planned and Actual representations as the same real-world unit for exclusivity/availability checks

---

## 11. Listing vs External Channel Publication

`Listing.status = published` means the Listing is active inside the SaaS.

It does **not** mean Facebook, Instagram, WhatsApp, or another external channel has successfully published or synchronized it.

Channel publication state is a separate concern for Phase 07.7.

Do not implement Meta/WhatsApp/social publication infrastructure inside 07.2.

---

# Phase 07 Boundaries

## Phase 07 owns

- Listings
- Leads
- Opportunities / CRM pipeline
- Activities / follow-ups
- Offers / counteroffers
- Reservations / pre-reservations
- Social-channel commercial workflows
- Commercial automation/AI/analytics

## Phase 08 remains authoritative for

- contracts
- legal obligations
- payment schedules
- installments
- contractual amendments
- final payment enforcement

Do not let Phase 07 create or mutate Phase 08 contractual truth prematurely.

---

# Engineering Conventions

- Inspect the existing repository before implementing any sub-phase.
- Reuse existing architecture and conventions.
- Reuse enums where domain semantics genuinely match.
- Reuse `HasCompany`, permissions, audit, existing Unit/Planned Unit relations, and company settings.
- Do not introduce speculative tables for future sub-phases.
- Implement only what the active sub-phase needs.
- Keep business logic out of Filament-only validation.
- Test affected behavior on both MySQL and PostgreSQL.
- Prefer explicit domain/service actions for important workflow transitions.
