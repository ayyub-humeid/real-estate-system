# Phase 07.6 — Reservations & Unit Snapshots
## Implementation Planning

## 1. Goal

Phase 07.6 introduces the reservation domain for Madar.

It converts an approved commercial intention into a controlled reservation of a real-estate unit while preserving the exact unit and commercial data that existed when the reservation became active.

This phase supports two entry paths:

1. Reservation created from an accepted Offer / accepted Offer Revision.
2. Direct reservation without requiring an Opportunity, Offer, or negotiation flow.

This phase does **not** create contracts, leases, payments, installments, invoices, or ownership transfers.

---

## 2. Main Business Flows

### 2.1 Reservation from Accepted Offer

Opportunity
→ Accepted Offer
→ Accepted Offer Revision
→ Create Reservation
→ Save as Draft
→ Send for Approval
→ Pending
→ Approve & Activate Reservation
→ Active
→ Reservation Snapshot created

Rules:

- Offer, Revision, Unit, Customer, and Company must be consistent.
- The Offer must be accepted and the Revision must be the agreed/accepted Revision.
- Commercial terms are copied from the accepted Revision into the reservation draft.
- Accepted negotiated terms must not be silently changed.
- An Accepted Offer may remain linked to its original Planned Unit Listing. If that Planned Unit has exactly one converted Actual Unit, the reservation resolves and snapshots that Actual Unit; it must be physically `ready`. The Listing is not rewritten merely to create a reservation.
- If no Actual Unit has been created from the Planned Unit yet, creation is blocked with a clear instruction to create and ready the Actual Unit first.
- An Accepted Offer without a Listing is valid for negotiation. Its reservation must require the employee to select one ready Actual Unit explicitly; the selected Unit remains company-scoped and is captured in the immutable snapshot.
- Activating the reservation does not create a contract.

### 2.2 Direct Reservation

Customer + Unit
→ Create Reservation
→ Enter commercial terms
→ Save as Draft
→ Send for Approval
→ Pending
→ Approve & Activate Reservation
→ Active
→ Reservation Snapshot created

Rules:

- No Opportunity is required.
- No Offer is required.
- No fake Offer or Opportunity should be created.
- Customer, Unit, transaction type, agreed amount, currency, and required commercial terms are entered directly.
- The same approval, locking, snapshot, audit, and availability rules apply.

---

## 3. Reservation Statuses

- `draft`
- `pending`
- `active`
- `rejected`
- `cancelled`
- `expired`

### Meaning

- `draft`: editable preparation; does not reserve the Unit.
- `pending`: sent for approval; does not reserve the Unit.
- `active`: approved and actually reserves the Unit.
- `rejected`: approval request rejected.
- `cancelled`: active reservation cancelled and Unit released.
- `expired`: reservation validity ended and Unit released.

Multiple pending requests may exist for the same Unit, but only one conflicting active reservation may exist.

---

## 4. Workflow Actions

Use explicit business names:

- `Save as Draft` — `حفظ كمسودة`
- `Send for Approval` — `إرسال للاعتماد`
- `Approve & Activate Reservation` — `اعتماد وتفعيل الحجز`
- `Reject Reservation Request` — `رفض طلب الحجز`
- `Extend Reservation` — `تمديد الحجز`
- `Cancel Reservation` — `إلغاء الحجز`
- `Expire Reservation` — `انتهاء الحجز`

Do not expose raw `status` editing through generic CRUD forms.

All transitions go through the domain/service layer.

---

## 5. Automatic Reference Numbering

Example:

`RES-2026-000001`

Rules:

- Generated automatically.
- Scoped per company.
- Concurrency-safe.
- Never use `MAX(id) + 1`.
- Reuse the project numbering/sequence mechanism if one already exists.

---

## 6. Database Tables

### 6.1 `unit_reservations`

Suggested fields:

- `id`
- `company_id`
- `reservation_number`
- `source` — `accepted_offer` / `direct`
- `status`
- `transaction_type` — `sale` / `rent`
- `unit_id`
- `customer_party_id`
- `opportunity_id` nullable
- `offer_id` nullable
- `offer_revision_id` nullable
- `agreed_amount`
- `currency`
- structured commercial draft fields required by the transaction type
- `valid_until` nullable
- `submitted_at` nullable
- `activated_at` nullable
- `rejected_at` nullable
- `cancelled_at` nullable
- `expired_at` nullable
- `created_by`
- `submitted_by` nullable
- `approved_by` nullable
- `rejected_by` nullable
- `cancelled_by` nullable
- timestamps

Important:

- Actor IDs are always set server-side from the authenticated user/system context.
- Opportunity/Offer fields remain nullable for direct reservations.
- Every related record must belong to the same company.

### 6.2 `reservation_snapshots`

Immutable activation snapshot.

Suggested fields:

- `id`
- `company_id`
- `reservation_id`
- `unit_data` JSON/JSONB
- `commercial_terms` JSON/JSONB
- `source_data` JSON/JSONB nullable
- `created_by`
- `created_at`

Recommended rule: one activation snapshot per reservation unless a future explicit amendment domain is introduced.

### 6.3 `reservation_status_histories`

Suggested fields:

- `id`
- `company_id`
- `reservation_id`
- `from_status` nullable
- `to_status`
- `action`
- `reason` nullable
- `metadata` JSON/JSONB nullable
- `changed_by`
- `created_at`

---

## 7. Relationships

### UnitReservation

- belongsTo Company
- belongsTo Unit
- belongsTo Customer Party
- belongsTo Opportunity nullable
- belongsTo Offer nullable
- belongsTo OfferRevision nullable
- belongsTo createdBy User
- belongsTo submittedBy User nullable
- belongsTo approvedBy User nullable
- belongsTo rejectedBy User nullable
- belongsTo cancelledBy User nullable
- hasOne ReservationSnapshot
- hasMany ReservationStatusHistory

### ReservationSnapshot

- belongsTo Reservation
- belongsTo Company
- belongsTo createdBy User

### ReservationStatusHistory

- belongsTo Reservation
- belongsTo Company
- belongsTo changedBy User

---

## 8. Source Validation

### `accepted_offer`

Required:

- `offer_id`
- `offer_revision_id`
- Offer is accepted
- Revision is the accepted/agreed Revision
- Revision belongs to Offer
- Offer/Revision/Unit/Customer/Company are consistent
- Commercial terms come from the accepted Revision

### `direct`

Required:

- `offer_id = null`
- `offer_revision_id = null`
- Opportunity may be null
- Direct commercial terms are complete

Enforce this in the domain layer, not only through Filament visibility.

---

## 9. Commercial Terms

Store only the commercial terms needed to approve and preserve the reservation.

### Sale examples

- agreed amount
- currency
- down payment amount if applicable
- payment approach/method if needed by current rules

### Rent examples

- rent amount
- currency
- rent frequency
- intended duration
- deposit amount if applicable

Use explicit columns for values that are validated, filtered, searched, or used in business rules.
Use JSON only for additional structured terms.

Do not create contracts, lease installments, payment schedules, invoices, or accounting entries in this phase.

---

## 10. Snapshot Design

Create the snapshot exactly when a reservation becomes `active`.

### Unit data snapshot may include

- unit id/reference
- unit number
- project/building references and display names
- floor
- area
- bedrooms
- bathrooms
- unit type
- selected important features
- relevant listing/reference information

### Commercial terms snapshot may include

- transaction type
- agreed amount
- currency
- sale or rental terms
- reservation validity dates
- source type
- accepted Offer/Revision references when applicable

The live Unit remains the current operational source.
The Snapshot is the historical source for what was approved at activation time.

---

## 11. Approve & Activate Transaction

This is the critical transaction.

General flow:

1. Authorize actor.
2. Lock Reservation.
3. Revalidate company and current status.
4. Lock Unit using a consistent lock order.
5. Check no conflicting active reservation exists.
6. Validate reservation source.
7. Resolve final commercial terms.
8. Create immutable Reservation Snapshot.
9. Transition Reservation to `active`.
10. Set `approved_by` and `activated_at`.
11. Create status history.
12. Dispatch external notifications/events after commit when appropriate.

Do not trust a prior UI availability check.

---

## 12. Double Booking Protection

Pending does not reserve the Unit.

At activation:

- acquire required row locks
- check active conflicts inside the transaction
- reject the second simultaneous activation

Must be tested on MySQL and PostgreSQL.

Do not rely only on frontend checks or a PHP pre-check outside the transaction.

---

## 13. Expiration

Active reservations may have `valid_until`.

When it expires:

- transition `active → expired`
- record `expired_at`
- record status history
- release the Unit under availability rules

Reuse the project's scheduler/job architecture if available.

---

## 14. Extension

`Extend Reservation`:

- requires explicit permission
- works only for active reservations
- validates the new expiration date
- the form must only accept a date strictly later than the reservation's
  current `valid_until` (not merely later than now), while the service repeats
  this validation under its transaction
- preserves audit history
- does not silently rewrite the activation snapshot

Store old/new expiry in history metadata if useful.

Reservation workflow actions in Filament must be state-gated: Draft shows Send
for Approval; Pending shows Approve/Reject; Active shows Cancel/Extend and only
shows Expire after its expiry time. The service remains the canonical guard.
After activation, render the immutable Unit and commercial snapshots through
explicit JSON/text display state, and cover the actual activation/re-render path
in a Filament test so a success toast cannot conceal a rendering exception.

The Create Reservation selectors must pre-filter deterministic invalid choices:
only ready Units without an active reservation may be selected, and an Accepted
Offer is shown only when its actual target is ready/available or its planned
target has already been converted to such an actual Unit. An accepted Offer
without a Listing remains valid only when the form can require a separate ready
available actual Unit. Server validation and locks still enforce the same rules
at save/activation time.

---

## 15. Permissions

Suggested permissions:

- `view_reservations`
- `create_reservations`
- `update_draft_reservations`
- `send_reservation_for_approval`
- `approve_reservations`
- `reject_reservations`
- `cancel_reservations`
- `extend_reservations`
- `view_reservation_history`
- `view_reservation_snapshot`

Rules:

- Spatie permissions + Policies + domain checks.
- No role-name shortcuts.
- All authorization enforced server-side.
- Cross-company relations always rejected.

---

## 16. Filament UX

### List

Useful columns:

- reservation number
- customer
- unit
- source
- transaction type
- status
- agreed amount
- valid until
- created by

### Create/Edit

First choose source:

- Accepted Offer
- Direct Reservation

For Accepted Offer:

- select/resolve accepted Offer
- derive accepted Revision
- display negotiated terms instead of forcing re-entry

For Direct:

- select customer
- select Unit
- enter commercial terms

### Record tabs / Relation Managers

- Overview
- Commercial Terms
- Snapshot
- Status History

All labels, tabs, Relation Managers, actions, confirmations, filters, enum values, validation errors, and notifications must be translated Arabic/English using the existing localization architecture.

Do not auto-translate user-entered data.

---

## 17. Integration With Previous Phases

### 07.2 Listings

Reservation references current Unit/listing context but preserves activation state in Snapshot.

### 07.3 CRM

Reservation may originate from Opportunity, but direct reservation does not require it.

### 07.5 Offers

Accepted Offer flow imports accepted Revision terms.
Offer acceptance alone is not a reservation.

---

## 18. Explicit Non-Goals

Do not implement:

- contracts
- signatures
- leases
- installments
- payments
- invoices
- accounting
- ownership transfer
- handover
- settlement

---

## 19. Tests

Cover:

- direct reservation draft
- accepted Offer reservation draft
- send for approval
- approve and activate
- reject pending
- cancel active
- extend active
- expire active
- invalid source combinations
- non-accepted Offer
- wrong Revision
- cross-company Unit/Customer/Offer
- forbidden transitions
- forged actor IDs
- unauthorized approval/cancel/extend
- two pending requests for same Unit
- simultaneous activation race
- one active reservation only
- snapshot created at activation
- snapshot unchanged when Unit/Listing later changes
- direct snapshot uses direct terms
- Offer snapshot uses accepted Revision terms
- MySQL
- PostgreSQL
- regressions for CRM/Offers/Unit availability

---

## 20. Definition of Done

Phase 07.6 is complete when:

- both reservation sources work
- workflow actions are explicit and protected
- numbering is automatic and safe
- active reservation conflict protection is reliable
- activation creates the snapshot atomically
- audit actors/history are complete
- company isolation is enforced
- Filament UX is usable
- Arabic/English localization is complete
- MySQL/PostgreSQL tests pass
- no contract/payment logic leaks into this phase
