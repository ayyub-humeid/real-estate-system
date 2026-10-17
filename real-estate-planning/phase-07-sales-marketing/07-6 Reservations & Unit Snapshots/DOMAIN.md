# Phase 07.6 — Reservations & Unit Snapshots
## Domain Design

## 1. Domain Purpose

The Reservation domain answers this business question:

> Which customer currently has an approved temporary reservation on a specific Unit, under which commercial terms, and what exactly was approved at that moment?

A reservation is not a contract, payment, lease, or ownership transfer.

It is a controlled temporary hold on a Unit after an approval workflow.

---

## 2. Aggregate Root

`UnitReservation` is the aggregate root.

It controls:

- lifecycle
- source validation
- Unit conflict protection
- approval/activation
- cancellation
- expiration
- extension
- snapshot creation
- audit history

Controllers and Filament Resources must not change workflow statuses directly.

---

## 3. Reservation Sources

### `accepted_offer`

Required domain facts:

- valid Company
- valid Customer
- valid Unit
- accepted Offer
- agreed/accepted Offer Revision
- negotiated commercial terms

### `direct`

Required domain facts:

- valid Company
- valid Customer
- valid Unit
- direct commercial terms

No Offer or Opportunity is required.

---

## 4. Core Invariants

### Tenant invariant

Every related business record belongs to the same Company.

Cross-company relationships are rejected even if IDs are valid.

### Source invariant

For `accepted_offer`:

- Offer exists
- accepted Revision exists
- Revision belongs to Offer
- Offer is accepted
- Revision is the agreed Revision
- Company/Unit/Customer context is consistent

For `direct`:

- Offer and Offer Revision are null

### Activation invariant

Only `pending` can become `active`.

Activation requires:

- permission
- valid source
- valid customer/unit
- resolved commercial terms
- no active Unit conflict
- snapshot creation

Activation + snapshot + history happen atomically.

### Unit reservation invariant

A Unit may have multiple draft/pending/rejected/cancelled/expired records.

A Unit must not have conflicting active reservations.

### Snapshot invariant

An active reservation must have its activation snapshot.

Snapshot data must not be silently rewritten when live Unit, Listing, Customer, Offer, or Revision changes.

### Actor invariant

Important actions preserve the actor:

- `created_by`
- `submitted_by`
- `approved_by`
- `rejected_by`
- `cancelled_by`
- history `changed_by`

Actor IDs come from authenticated/server context, not editable request input.

---

## 5. State Machine

```text
draft
  |
  | Send for Approval
  v
pending
  | \
  |  \ Reject Reservation Request
  |   v
  |  rejected
  |
  | Approve & Activate Reservation
  v
active
  | \
  |  \ Cancel Reservation
  |   v
  |  cancelled
  |
  | Expire Reservation
  v
expired
```

`Extend Reservation` keeps the reservation `active` while updating its valid-until date with audit history.

---

## 6. Transition Rules

### `draft → pending`

Action: `sendForApproval`

Requires:

- permission
- required data complete
- valid source
- valid commercial terms
- valid same-company relations

Effects:

- status = pending
- submitted_by = actor
- submitted_at = now
- history record

### `pending → active`

Action: `approveAndActivate`

Requires:

- approval permission
- pending state
- reservation lock
- Unit lock
- no active conflict
- source revalidated
- terms resolved

Effects:

- create immutable snapshot
- status = active
- approved_by = actor
- activated_at = now
- history record

### `pending → rejected`

Action: `rejectRequest`

Effects:

- status = rejected
- rejected_by = actor
- rejected_at = now
- reason/history recorded

### `active → cancelled`

Action: `cancelReservation`

Effects:

- status = cancelled
- cancelled_by = actor
- cancelled_at = now
- reason/history recorded
- Unit released

### `active → expired`

Action: `expireReservation`

Requires `valid_until` reached.

Effects:

- status = expired
- expired_at = now
- history record
- Unit released

### `active → active`

Action: `extendReservation`

Requires permission and a valid future date.

Effects:

- update `valid_until`
- preserve snapshot
- history metadata stores old/new expiry

---

## 7. Snapshot Boundary

The snapshot is created at the exact moment the company approves and activates the reservation.

### Unit Snapshot example

```json
{
  "unit_id": 501,
  "unit_number": "A-501",
  "project_name": "Tower A",
  "building_name": "Building 1",
  "floor": 5,
  "unit_type": "apartment",
  "area": "150.00",
  "bedrooms": 3,
  "bathrooms": 2
}
```

### Sale Commercial Terms example

```json
{
  "transaction_type": "sale",
  "agreed_amount": "114000.00",
  "currency": "USD",
  "down_payment": "20000.00",
  "payment_method": "installments"
}
```

### Rent Commercial Terms example

```json
{
  "transaction_type": "rent",
  "rent_amount": "700.00",
  "currency": "USD",
  "rent_frequency": "monthly",
  "duration_months": 12,
  "deposit_amount": "700.00"
}
```

These values represent agreed commercial terms only.
They do not create payment schedules, invoices, installments, or leases.

---

## 8. Snapshot Source Resolution

### Accepted Offer

Conceptually:

```php
$terms = $acceptedRevision->commercialTerms();
```

### Direct Reservation

Conceptually:

```php
$terms = $reservation->draftCommercialTerms();
```

Both then pass through one builder/factory:

```php
$snapshot = $snapshotFactory->make(
    reservation: $reservation,
    unit: $unit,
    terms: $terms,
);
```

This avoids duplicating snapshot logic.

---

## 9. Suggested Services

### `ReservationWorkflowService`

Responsibilities:

- send for approval
- approve and activate
- reject request
- cancel reservation
- extend reservation
- expire reservation

### `ReservationSnapshotFactory`

Responsibilities:

- collect historical Unit data
- build stable commercial terms payload
- build source metadata

It does not make workflow decisions.

### `ReservationAvailabilityService`

Responsibilities:

- detect active conflicts
- encapsulate Unit reservation availability rules

Must be called within the activation transaction after required locks are acquired.

### `ReservationNumberService`

Only add if the project does not already have a reusable numbering service.

---

## 10. Conceptual Activation Code

```php
public function approveAndActivate(
    User $actor,
    UnitReservation $record
): UnitReservation {
    $this->authorize($actor, 'approve', $record);

    return DB::transaction(function () use ($actor, $record) {
        $reservation = UnitReservation::withoutGlobalScopes()
            ->whereKey($record->id)
            ->lockForUpdate()
            ->firstOrFail();

        $this->assertSameCompany($actor, $reservation);
        $this->assertPending($reservation);

        $unit = Unit::withoutGlobalScopes()
            ->where('company_id', $reservation->company_id)
            ->whereKey($reservation->unit_id)
            ->lockForUpdate()
            ->firstOrFail();

        $this->availability->assertCanActivate(
            reservation: $reservation,
            unit: $unit,
        );

        $terms = $this->resolveCommercialTerms($reservation);

        $snapshot = $this->snapshotFactory->make(
            reservation: $reservation,
            unit: $unit,
            terms: $terms,
        );

        $reservation->snapshot()->create([
            'company_id' => $reservation->company_id,
            'unit_data' => $snapshot->unitData,
            'commercial_terms' => $snapshot->commercialTerms,
            'source_data' => $snapshot->sourceData,
            'created_by' => $actor->id,
        ]);

        $reservation->update([
            'status' => ReservationStatus::Active,
            'approved_by' => $actor->id,
            'activated_at' => now(),
        ]);

        $reservation->statusHistories()->create([
            'company_id' => $reservation->company_id,
            'from_status' => ReservationStatus::Pending,
            'to_status' => ReservationStatus::Active,
            'action' => 'approve_and_activate',
            'changed_by' => $actor->id,
        ]);

        return $reservation->fresh([
            'snapshot',
            'unit',
            'customer',
        ]);
    });
}
```

This is conceptual. Final code must follow actual project model names, scopes, enums, policies, transactions, and event conventions.

---

## 11. Commercial Terms Strategy

Do not move the contract domain into Reservation.

Use explicit columns for values that are:

- validated
- filtered/searched
- used in rules
- frequently displayed

Use JSON only for additional structured conditions.

Do not use JSON as a dumping ground for every business field.

---

## 12. Contract Boundary

Future flow:

```text
Reservation
    ↓
Reservation Snapshot
    ↓
Contract Creation
    ↓
Contract Terms / Contract Snapshot
```

The future Contract domain copies/transforms approved reservation terms into its own immutable legal/contractual structure.

Reservation Snapshot is historical commercial evidence, not the contract itself.

---

## 13. Events / Notifications

If the existing architecture uses events/notifications, candidates include:

- `ReservationSentForApproval`
- `ReservationActivated`
- `ReservationRejected`
- `ReservationCancelled`
- `ReservationExtended`
- `ReservationExpired`

Prefer external notifications after transaction commit.
Reuse existing project conventions rather than inventing a parallel event framework.

---

## 14. Filament Protection

Filament is the interface, not the business-rule owner.

Do not do this directly from a Resource action:

```php
$reservation->update(['status' => 'active']);
```

Filament actions must call the domain workflow service.

Status must not be a generic editable dropdown.

---

## 15. Localization Rule

All static domain/UI values use translation files and existing localization conventions.

Includes:

- enum labels
- statuses
- action names
- validation messages
- business exceptions
- notifications
- tabs
- Relation Managers
- resource labels
- filters
- confirmation dialogs

Arabic/English and RTL/LTR must be complete.
User-entered data is not automatically translated.

---

## 16. Phase Boundary

Phase 07.6 ends when the system can reliably produce and manage an active/rejected/cancelled/expired reservation with a trustworthy immutable activation snapshot.

It intentionally does not answer:

- how contracts are generated
- how signatures work
- how installments are scheduled
- how payments are posted
- how ownership transfers
- how handover is completed
