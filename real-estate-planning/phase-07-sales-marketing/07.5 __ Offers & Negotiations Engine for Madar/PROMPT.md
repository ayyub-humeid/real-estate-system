# Implementation Prompt — Phase 07.5 Offers & Negotiations Engine

Implement **Phase 07.5 — Offers & Negotiations Engine** for **Madar | مدار** according to the approved `SPEC.md` in this folder.

## Mandatory reading before coding

Before writing code, read and understand:

1. `real-estate-planning/README.md`
2. all relevant global architecture, coding conventions, workflow, multi-tenancy, permissions, numbering, audit, localization, and Filament documentation
3. **all relevant documentation under `docs/` in both Arabic and English**
4. Phase 07 main planning
5. Phase 07.2 Listings Management
6. Phase 07.3 Leads / Opportunities / CRM Kanban
7. Phase 07.4 Activities / Follow-ups
8. this Phase 07.5 `SPEC.md`
9. the actual existing Laravel codebase before creating migrations, enums, services, permissions, translations, or Filament resources

Do not duplicate an existing shared implementation.

Laravel Herd is already configured and working. Do not reinstall Herd, PHP, Composer, Laravel, database drivers, or unnecessarily modify the environment.

---

## Core implementation goal

Build a real commercial negotiation workflow around:

```text
Opportunity
   ↓
Offer
   ↓
Revisions / Counteroffers
   ↓
Internal Approval when required
   ↓
Confirmed Commercial Agreement
```

This phase must preserve the full negotiation history and remain strictly separate from Reservation, Contract, Lease, and Payment execution.

---

## Required domain rules

### Offer + Revisions

- One Offer represents one negotiation case.
- Counteroffers are new Revisions under the same Offer.
- Do not create a new Offer for every price change.
- Formally submitted Revisions must not be silently edited; changed terms create a new Revision.
- Preserve customer/company proposer side separately from the authenticated employee who records the proposal.

### Automatic numbering

- Generate Offer references automatically.
- Generate revision sequence numbers automatically within an Offer.
- Reuse the existing project numbering service/convention if present.
- Numbering must be concurrency-safe.
- Never use user-entered reference numbers or unsafe `MAX(id)+1`.

### Actor / audit tracking

Important actions must record the authenticated actor according to the existing audit conventions, including creator, recorder, approval requester, approver/decision maker, and agreement confirmer.

Do not trust actor IDs coming from forms or APIs when they represent the current authenticated user.

### Current / agreed revision relationships

Do not add `current_revision_id` simply as duplicated state.

Derive the latest/current Revision through Eloquent relationships / revision ordering.

Prefer a derived `agreedRevision()` relationship based on the agreed revision state rather than an unnecessary cached FK.

Enforce at most one final agreed Revision per Offer using domain validation plus appropriate database protection.

### Sale / rental commercial terms

Store only negotiated commercial terms needed by this phase.

Use explicit columns for important searchable/reportable values and `terms_json` only for optional evolving details.

For sale, support the relevant lightweight negotiated terms such as amount, currency, optional down payment and payment summary/method if required.

For rent, support relevant negotiated terms such as rent amount, currency, frequency, lease duration and optional deposit.

Do NOT create actual Lease, Installment, Contract, Payment, or Reservation records here.

Later phases will consume the final agreed Revision into their own immutable snapshot.

### Internal approval vs external agreement

These must remain separate.

An internal approval does not automatically mean the customer accepted a company proposal.

A customer's proposal does not automatically become accepted unless the company has the required authority/approval and the agreement is explicitly confirmed.

Implement clear domain actions for approval and final agreement confirmation.

### Workflow protection

Workflow-controlled statuses must not be manually edited through generic status selects.

Use service/domain actions for operations such as:

- Create Offer
- Create Revision
- Submit Revision
- Create Counteroffer
- Request Approval
- Approve / Reject
- Confirm Agreement
- Withdraw
- Expire / close when appropriate

Run multi-record changes in transactions and use locking where concurrency matters.

### Permissions

Use the project's existing Spatie / Shield / Policy conventions.

Authorization must be enforced server-side, not only through hidden Filament actions.

Do not infer authority from role names such as `manager`.

Verify same-company membership for Offer, Opportunity, Listing, Revision, Approval, assigned users, and approvers.

### CRM integration

Integrate cleanly with the existing finite CRM Stage Requirement system so a company can require an agreed/accepted Offer where appropriate.

Do not automatically move an Opportunity when an Offer becomes accepted.

Reuse Phase 07.4 Activities / Follow-ups where negotiation follow-up tasks or timeline events are needed; do not rebuild those systems.

### Scope boundary

Offer acceptance must NOT:

- reserve the unit
- change physical/legal unit state
- create contracts
- create leases
- create payment schedules
- collect payments

Those belong to later phases.

---

## Filament requirements

Build a simple, clear UX integrated with the existing CRM.

Expected areas include, according to the project's existing patterns:

- Opportunity → Offers
- Offer detail page / relation management
- Revision / negotiation history
- Counteroffer action
- approval request / decision actions
- optional My Approvals queue when appropriate
- final agreement confirmation

Only display actions valid for the current state and user's permissions.

Do not expose raw workflow status editing.

---

## Arabic and English localization — mandatory

Inspect how the existing CRM and shared domain implement translations, enums, labels, navigation, Relation Managers, tabs, notifications, and RTL.

Follow those conventions exactly.

Provide Arabic and English translations for **every static user-facing value**, including:

- Resource and model labels
- navigation labels/groups
- Relation Manager names and tabs
- form fields
- table columns
- filters
- actions
- modal text
- enum labels
- statuses
- proposer side
- transaction type
- approval status/actions
- validation messages
- workflow errors
- notifications
- helper / empty-state text

All business logic must use stable enum/code values, never translated strings.

Do not automatically translate user-entered data such as notes, customer names, or free-form terms.

Support Arabic RTL and English LTR correctly.

---

## Tests

Add thorough automated tests based on the SPEC, especially:

- company isolation
- permissions
- automatic numbering
- revision sequencing
- submitted revision immutability
- counteroffer history
- approval flow
- distinction between internal approval and external agreement
- only one agreed Revision
- sale/rental term validation
- CRM requirement integration
- accepted Offer does not move Opportunity automatically
- accepted Offer does not reserve a unit
- localization conventions
- direct backend authorization
- Phase 07.3 / 07.4 regressions

Run migrations and tests on **MySQL and PostgreSQL**.

Do not claim tests or UI verification that you did not actually run.

---

## Final report

When complete, provide a concise implementation report containing:

- files created/modified
- migrations and schema decisions
- models / relationships
- enums
- services / workflow actions
- permissions / policies
- Filament screens/actions
- localization work
- automated test results on MySQL and PostgreSQL
- relevant regression results
- any manual UI verification performed
- any remaining issues or intentional future-phase boundaries

Implement **Phase 07.5 only**, according to the approved SPEC and the existing project architecture.
