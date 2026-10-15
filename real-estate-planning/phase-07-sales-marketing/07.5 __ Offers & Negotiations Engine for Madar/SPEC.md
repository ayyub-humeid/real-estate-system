# Phase 07.5 — Offers & Negotiations Engine

## 1. Purpose

Phase 07.5 introduces the commercial negotiation layer between CRM Opportunities and the later Reservation / Contract phases.

The goal is to let a company record, negotiate, review, approve, reject, expire, withdraw, and finally agree on commercial terms for a sale or rental opportunity while preserving the full negotiation history.

This phase must behave as a real domain workflow, not as a generic CRUD screen.

The central model is:

```text
Opportunity
   ↓
Offer
   ↓
Offer Revisions / Counteroffers
   ↓
Internal Approval when required
   ↓
Confirmed Commercial Agreement
   ↓
Phase 07.6 Reservation / Snapshot
   ↓
Later Contract flow
```

An accepted commercial offer does NOT reserve the unit, transfer ownership, create a lease, create a contract, or change the physical unit state in this phase.

---

## 2. Mandatory implementation principles

The implementation must reuse the project's existing architecture and conventions.

Before coding, inspect:

- `real-estate-planning/README.md`
- the global architecture / conventions documentation
- both Arabic and English documentation under `docs/`
- Phase 07 main planning
- Phase 07.2 Listings Management
- Phase 07.3 Leads / Opportunities / CRM Kanban
- Phase 07.4 Activities / Follow-ups
- existing shared Enums, Filament conventions, translation conventions, permission conventions, global scopes, company isolation, sequence / numbering utilities, audit helpers, workflow services, notifications, and tests

Do not duplicate an existing generic service or convention if the project already has one.

Laravel Herd is already configured and working. Do not reinstall or unnecessarily reconfigure the environment.

---

## 3. Core business model

### 3.1 Offer

An Offer represents one negotiation case / negotiation round for one Opportunity.

It is the parent record for all commercial proposals exchanged during that negotiation.

Example:

```text
Offer OFF-2026-0001
├── Revision 1 — Customer proposes 110,000 USD
├── Revision 2 — Company counteroffers 116,000 USD
└── Revision 3 — Customer proposes 114,000 USD → final agreed revision
```

Do not create a new Offer for every counteroffer. Counteroffers are revisions under the same Offer.

A new Offer may be created later for the same Opportunity only when there is a genuinely separate negotiation round / case and business rules allow it.

### 3.2 Offer Revision

An Offer Revision is an immutable commercial proposal once formally submitted.

It records:

- who proposed it (customer/company)
- who recorded it in the system
- the proposed commercial amount and structured terms
- submission / expiry information
- its workflow status
- optional notes

If the commercial terms change after submission, create a new revision. Do not silently edit the submitted revision.

### 3.3 Approval

An Approval is an internal company decision on a revision.

Internal approval is NOT the same as customer acceptance.

Examples:

- employee records customer proposal 114,000 USD
- company policy requires manager approval before accepting anything below 116,000 USD
- an approval request is created
- authorized approver approves or rejects it
- only after required internal approval and external agreement can the revision become the final agreed revision

### 3.4 Commercial agreement

Final agreement means both sides' agreement on one revision has been confirmed according to the source side and company workflow.

It closes the negotiation commercially, but does not perform reservation or contract creation.

---

## 4. Automatic reference numbering

All human-facing reference numbers must be generated automatically by the backend.

Users must never manually enter reference numbers.

Examples:

```text
Offer:    OFF-2026-0001
Approval: APR-2026-0001
Revision display: REV-001 or Revision 1 within the Offer
```

Rules:

- numbering is company-scoped where appropriate
- reuse the existing project numbering / sequencing system if present
- numbering must be concurrency-safe
- do not use unsafe `MAX(id) + 1`
- the reference number is for business readability; the database primary key remains independent
- inspect how other business entities in the project generate references and follow that convention

A separate globally meaningful revision reference is optional. If the project convention prefers only `revision_number` within an Offer, use that instead of introducing unnecessary global revision numbering.

---

## 5. Audit and actor tracking

Every important operation must preserve who performed it.

The backend is the authority for actor fields. Never trust a form/API field for these values when they represent the authenticated actor.

Important actor concepts include:

- `created_by` — creator of the record
- `recorded_by` — user who recorded a customer's proposal
- `submitted_by` — user who formally submitted a revision when useful
- `requested_by` — user who requested internal approval
- `assigned_approver_id` — intended approver when assignment is used
- `decided_by` — user who approved/rejected
- `confirmed_by` — user who confirmed final external agreement
- `withdrawn_by` — user who withdrew the offer if tracked separately

Use the project's existing audit conventions. Do not add duplicate columns if an existing auditable trait already provides equivalent immutable information.

---

## 6. Suggested database model

The agent must inspect the existing schema before adding anything. The following is the approved domain shape, not a command to duplicate existing tables.

### 6.1 `crm_offers`

Suggested responsibilities / columns:

```text
id
company_id
opportunity_id
listing_id nullable
reference_number / offer_number
transaction_type          // sale | rent
status                    // workflow-controlled
assigned_to nullable      // negotiator / owner if required
created_by
closed_at nullable
created_at
updated_at
```

Possible additional timestamps should be added only when they carry domain meaning and are not derivable from history.

#### Important relationship decision

Do NOT add `current_revision_id` merely to know the latest revision.

Current/latest revision should be derived from the `revisions()` relationship, for example by `revision_number` / submitted timestamp according to the final model.

Do NOT add `accepted_revision_id` merely as a convenience cache if the agreed revision can be represented safely through revision state / agreement relation.

Prefer domain relationships such as:

```php
revisions()
latestRevision()
agreedRevision()
```

The exact implementation must enforce the invariant that at most one revision is the final agreed revision for an Offer.

If the existing project architecture demonstrates that an explicit FK is required for correctness or performance, document the reason before adding it. It must not become duplicated uncontrolled state.

### 6.2 `crm_offer_revisions`

Suggested responsibilities / columns:

```text
id
company_id
offer_id
revision_number
proposed_by_side          // customer | company
recorded_by
status
amount
currency
submitted_at nullable
expires_at nullable
notes nullable
terms_json nullable
created_at
updated_at
```

The final schema may include explicit structured commercial fields described below.

Required constraints:

- revision numbers are unique within an Offer
- company must match parent Offer
- submitted revisions are immutable for material negotiated fields
- a new proposal becomes a new revision
- only valid revision status transitions are allowed

### 6.3 `crm_offer_approvals`

Suggested responsibilities / columns:

```text
id
company_id
offer_revision_id
requested_by
assigned_approver_id nullable
status                 // pending | approved | rejected
created_by             // if project convention requires both; otherwise avoid duplicate semantics

decided_by nullable
decision_notes nullable
decided_at nullable
created_at
updated_at
```

Depending on the project's audit standard, `requested_by` may be the meaningful creator field and a separate `created_by` may be redundant. Follow the established convention while preserving the actor who initiated the approval request.

---

## 7. Relationships

Conceptual relationships:

```text
Company
 ├── Offers
 ├── Offer Revisions
 └── Offer Approvals

Opportunity
 └── Offers

Listing
 └── Offers

Offer
 ├── belongsTo Opportunity
 ├── belongsTo Listing (nullable if business flow permits)
 ├── hasMany Revisions
 ├── hasOne latestRevision (derived)
 └── hasOne agreedRevision (derived by business state)

Offer Revision
 ├── belongsTo Offer
 └── hasMany Approvals

Offer Approval
 └── belongsTo Offer Revision
```

User relationships should clearly expose creator / recorder / approver / decision maker / negotiator roles.

All cross-company relations must be rejected server-side.

---

## 8. Offer statuses

Use a Backed Enum following the project's existing enum conventions.

Approved initial set:

```php
Draft
Negotiating
Accepted
Rejected
Withdrawn
Expired
```

Suggested values:

```text
draft
negotiating
accepted
rejected
withdrawn
expired
```

Final / terminal statuses must not be manually editable through a generic status dropdown.

Status changes must happen through explicit domain actions.

No business logic may depend on translated labels.

---

## 9. Revision statuses

The exact enum may be adjusted during implementation after inspecting current domain conventions, but it must support the following concepts:

- draft
- submitted / active proposal
- superseded
- agreed / accepted
- rejected
- withdrawn
- expired

A formally submitted revision must preserve its negotiated values.

When a counteroffer is submitted, the previous active proposal normally becomes `superseded`, unless the business rule says it remains simultaneously valid.

Default implementation should use one active proposal per Offer at a time unless an existing product requirement explicitly supports parallel proposals.

---

## 10. Proposed side vs authenticated actor

`proposed_by_side` answers who made the commercial proposal:

```text
customer
company
```

`recorded_by` answers which authenticated user put it into the system.

Example:

```text
proposed_by_side = customer
recorded_by = Mohammad (employee)
```

Never assume the authenticated employee is the commercial proposer.

---

## 11. Sale and rental commercial terms

Phase 07.5 must store only the commercial terms being negotiated. It must NOT duplicate the full Contract domain.

### 11.1 Common explicit fields

The fields that are important for filtering, comparison, reporting, validation, or later transfer should be explicit database columns rather than buried in JSON.

At minimum consider:

```text
amount
currency
```

### 11.2 Sale terms

Potential structured negotiated terms:

```text
sale_price / amount
currency
down_payment_amount nullable
payment_method nullable
payment_terms / installment summary nullable
```

Do not build the later installments / contract engine here.

If the project already has a normalized payment-term model that is appropriate for negotiation, reuse it. Otherwise keep the negotiation terms intentionally lightweight.

### 11.3 Rental terms

Potential structured negotiated terms:

```text
rent_amount / amount
currency
rent_frequency nullable       // monthly | quarterly | semiannual | annual | custom if existing domain supports it
lease_term_value nullable
lease_term_unit nullable
deposit_amount nullable
```

Do not create the actual Lease record in Phase 07.5.

### 11.4 `terms_json`

`terms_json` is allowed only for additional optional or evolving negotiated details that are not core searchable/reportable business fields.

Do not use JSON as a replacement for proper schema design.

### 11.5 Future contract handoff

Later phases must treat the final agreed revision as a source for a snapshot.

When creating a Reservation / Contract later:

```text
Agreed Offer Revision
        ↓
Create reservation / contract commercial snapshot
        ↓
Future changes to Offer data do not silently rewrite the legal/commercial snapshot
```

This preserves historical correctness.

---

## 12. Internal approval workflow

Internal approval must be permission-driven.

Example:

```text
Customer proposes 114,000 USD
        ↓
Employee records Revision #3
        ↓
Employee lacks authority to accept
        ↓
Request Approval
        ↓
Authorized manager reviews
        ├── Approve
        └── Reject
```

Approval rules may initially be explicit workflow actions rather than a full generic rules engine.

Do not over-engineer dynamic pricing approval matrices in this phase unless the existing project already contains such infrastructure.

The architecture should allow future policies such as approval thresholds without rewriting the Offer domain.

---

## 13. External agreement confirmation

Internal approval and external agreement are separate concepts.

Examples:

### Customer-originated revision

The customer already proposed the revision. If company approval is required and is granted, an authorized user can confirm the company accepts the customer's proposal.

### Company-originated revision

The company may have internally approved the proposed price before sending it. The Offer must remain negotiating until the customer acceptance is recorded / confirmed by an authorized user.

The domain service must determine whether the revision is eligible to become the final agreed revision.

Store the appropriate confirmation actor and timestamp according to existing audit conventions.

---

## 14. Main workflow actions

Implement important transitions through the domain/service layer.

Recommended actions:

```text
Create Offer
Create Draft Revision
Submit Revision
Create Counteroffer
Request Approval
Approve Revision
Reject Approval / Revision
Confirm External Agreement
Withdraw Revision / Offer
Expire Revision / Offer
Close as Rejected
```

Use explicit service methods / actions rather than direct Filament model updates for workflow-controlled data.

Multiple-record changes must run in database transactions.

Use row locks where concurrency could create conflicting active / agreed revisions.

---

## 15. Permissions and authorization

Authorization must be exact and server-side.

Reuse Spatie Permission / Shield / Policies conventions already used in the project.

Potential permission concepts:

```text
view_offer
view_any_offer
create_offer
update_draft_offer
assign_offer
create_offer_revision
submit_offer_revision
request_offer_approval
view_offer_approval
approve_offer_revision
reject_offer_revision
confirm_offer_agreement
withdraw_offer
close_offer
```

Do not blindly create these exact names if the project has a different permission naming convention. Inspect and follow the established convention.

Critical rules:

- being called `manager` does not bypass permissions
- creator does not automatically have approval permission
- assigned negotiator does not automatically have authority to accept commercial terms
- company context must match on every action
- direct URL / API / Livewire action attempts must be protected even if the UI button is hidden

---

## 16. CRM integration

### Phase 07.3 — Opportunity / Kanban

Add a supported stage requirement concept such as:

```text
accepted_offer_exists
```

or integrate through the existing finite requirement system in the cleanest compatible way.

This allows a company to configure a CRM stage rule requiring a commercially agreed Offer before moving out of Negotiation / into Won.

However:

- accepting an Offer does NOT automatically move the Opportunity
- moving the Opportunity remains an explicit CRM workflow action
- Offer logic must not depend on translated stage labels

### Phase 07.4 — Activities / Follow-ups

Offer negotiation can create / reuse follow-up tasks such as:

```text
Follow up with customer about counteroffer
```

Negotiation events may appear in the Opportunity timeline.

Do not rebuild the task/activity engine.

---

## 17. Phase 07.6 boundary

Phase 07.5 must NOT:

- reserve the unit
- block inventory as a reservation
- change unit ownership
- generate sales contracts
- create leases
- create payment schedules / installments
- collect payments
- transfer the Listing / Unit to sold or rented as a physical/legal fact

Phase 07.6 will consume the agreed revision and create a reservation / commercial snapshot according to its own business rules.

---

## 18. Filament UX

The UI should prioritize clarity and low friction.

Recommended surfaces:

### Opportunity → Offers

Relation Manager or equivalent showing negotiation cases for the Opportunity.

### Offer Details

Display:

- automatic offer number
- opportunity / listing
- transaction type
- assigned negotiator
- status
- current/latest revision summary
- final agreed revision when available
- negotiation history
- approval history

### Revisions

Use a timeline / table / cards that make the negotiation sequence obvious:

```text
R1 Customer   110,000 USD
R2 Company    116,000 USD
R3 Customer   114,000 USD — Agreed
```

### Actions

Context-aware actions only:

- Add Counteroffer
- Submit Revision
- Request Approval
- Approve / Reject
- Confirm Agreement
- Withdraw

Do not expose invalid actions for the current state.

### My Approvals

If useful and consistent with the project, provide an approval queue for users who have approval permissions.

---

## 19. Localization — Arabic and English

Full Arabic and English localization is mandatory for all static user-facing content.

The agent must first inspect how the existing CRM and shared domain implement localization and follow the same system and conventions.

Translate all static UI elements, including:

- Resource labels
- singular / plural model labels
- navigation groups
- page titles
- Relation Manager labels
- Tabs
- form labels
- table headers
- filters
- actions
- modal headings / descriptions
- enum labels
- statuses
- transaction types
- proposer-side labels
- validation messages
- notifications
- workflow errors
- empty states
- helper text
- approval actions / statuses

Support Arabic RTL and English LTR correctly.

Do NOT translate user-entered data automatically. Examples not automatically translated:

- customer names
- notes
- manually entered commercial comments
- free-form additional terms

Business logic must use enum/code values, never translated strings.

---

## 20. Company isolation

Every new record belongs to one Company directly or transitively.

The implementation must protect against:

- assigning an Opportunity from another company
- selecting a Listing from another company
- attaching a Revision to another company's Offer
- creating an Approval across companies
- selecting users / approvers / assignees from another company

Use the existing company global scope / trait where appropriate, but critical service methods must still validate cross-company consistency explicitly.

Tests must cover bypass attempts using `withoutGlobalScopes()` / direct IDs where relevant.

---

## 21. Validation rules

Examples of domain validations:

- amount must be valid and positive
- supported currency only
- sale Offer cannot use invalid rental-only combinations
- rental Offer must validate rent frequency / duration when required
- expiry cannot be before submission
- only draft revisions are materially editable
- submitted / superseded / agreed revisions cannot have negotiated fields silently changed
- final agreement requires all mandatory approvals
- revision must belong to Offer
- Offer must belong to Opportunity / Listing in current company
- transaction type must be compatible with Opportunity / Listing business context
- only one agreed revision per Offer
- terminal Offers reject ordinary negotiation actions

Use domain exceptions / validation exceptions consistent with existing architecture.

---

## 22. Concurrency and integrity

Critical operations must be safe under simultaneous requests.

Examples:

- generating business reference numbers
- assigning the next revision number
- submitting two counteroffers at the same time
- approving / rejecting the same pending Approval simultaneously
- confirming two revisions as agreed simultaneously

Use database transactions, unique constraints, and row locks where appropriate.

Support both MySQL and PostgreSQL.

---

## 23. Recommended services

Names may be adjusted to project conventions.

Suggested domain services:

```text
OfferService / OfferNegotiationService
OfferRevisionService
OfferApprovalService
OfferNumberGenerator (only if no shared numbering service already exists)
OfferAgreementService (optional if separation improves clarity)
```

Prefer cohesive services over one giant class, but do not create unnecessary abstraction.

Workflow rules belong in services/domain actions, not in Filament resources.

---

## 24. Suggested Enums

Follow existing project conventions for enum labels / translations.

Potential enums:

```text
OfferStatus
OfferRevisionStatus
OfferProposedBySide
OfferApprovalStatus
OfferTransactionType (only if no existing sale/rent enum can be reused)
RentFrequency (reuse existing domain enum if present)
```

Never duplicate an existing shared domain Enum just for CRM Offers.

---

## 25. Timeline and history

The negotiation history must remain understandable and auditable.

At minimum, immutable Revision rows and Approval records provide the core history.

If the project already has a generic activity / audit timeline mechanism, integrate important Offer events into it rather than inventing another generic history table.

If an explicit Offer event/history table becomes necessary, justify it by a concrete missing audit requirement first.

Do not create redundant history tables by default.

---

## 26. Delete behavior

Avoid destructive deletion of business history.

Rules:

- submitted Revisions should not be hard-deleted through normal UI
- Approval decisions should not be deleted casually
- accepted / closed Offers must preserve negotiation history
- draft-only records may follow project deletion conventions if safe

Prefer workflow states over destructive deletion for meaningful business records.

---

## 27. Testing requirements

The agent must implement focused automated tests, including at minimum:

1. company can create an Offer for its Opportunity
2. cross-company Opportunity rejected
3. cross-company Listing rejected
4. offer number generated automatically
5. automatic number generation remains unique under repeated creation
6. unauthorized user cannot create Offer
7. authorized user can create first Revision
8. customer proposal stores `proposed_by_side=customer` and authenticated `recorded_by`
9. company proposal stores correct side
10. revision number generated automatically within Offer
11. submitted Revision negotiated fields cannot be silently edited
12. counteroffer creates a new Revision
13. prior proposal becomes superseded according to workflow
14. unauthorized user cannot request approval if permission required
15. approval request records requester
16. approver must belong to same company
17. unauthorized user cannot approve
18. authorized approver can approve
19. `decided_by` and `decided_at` recorded correctly
20. rejected approval cannot be treated as approved
21. internal approval alone does not mark Offer accepted when external agreement is still missing
22. final agreement fails when mandatory approval is missing
23. final agreement succeeds when rules are satisfied
24. only one agreed Revision allowed per Offer
25. accepted Offer rejects ordinary counteroffer unless explicit reopen flow exists (reopen is out of scope unless already defined)
26. withdrawn Offer rejects new revision actions
27. expired Offer rejects invalid actions
28. sale structured terms validate
29. rental structured terms validate
30. `terms_json` optional details persist without replacing core fields
31. Stage Requirement can detect accepted/agreed Offer correctly
32. Offer acceptance does not automatically move Opportunity
33. Offer acceptance does not reserve unit / Listing
34. Arabic enum/static labels resolve using project conventions
35. English enum/static labels resolve using project conventions
36. Relation Manager / tabs use translation keys rather than hardcoded labels
37. policies protect direct action calls, not only hidden UI buttons
38. relevant Phase 07.3 regression tests pass
39. relevant Phase 07.4 regression tests pass
40. migrations and tests pass on MySQL
41. migrations and tests pass on PostgreSQL

Add more tests when the actual codebase reveals important invariants.

---

## 28. Manual Filament verification

After automated tests, manually verify where possible:

- Arabic RTL Offer Resource / Relation Manager
- English LTR Offer Resource / Relation Manager
- automatic offer number display
- revision history ordering
- counteroffer action
- permission-dependent actions
- approval queue
- confirmation of agreement
- validation messages
- no untranslated hard-coded static labels
- responsive usability where relevant

Do not claim manual verification unless it was actually performed.

---

## 29. Explicit non-goals

Do not implement in Phase 07.5:

- reservation engine
- inventory blocking
- contract generation
- lease creation
- payment schedules
- payment collection
- e-signature
- WhatsApp / Facebook / Instagram integration
- AI negotiation suggestions
- generic dynamic approval-rule builder unless already available

Architect cleanly for future integration without implementing future phases.

---

## 30. Definition of Done

Phase 07.5 is complete when:

- Offer negotiation has a clean parent + Revision model
- automatic business numbering works safely
- all important operations preserve the authenticated actor
- sale and rental commercial terms are represented without duplicating the Contract domain
- counteroffers preserve full history
- submitted revisions are protected from silent edits
- approval workflow is permission-safe
- internal approval is separate from external agreement
- one final agreed revision can be identified reliably through relationships / domain state
- accepted Offer does not reserve or contract the property
- CRM Stage Requirements can consume the agreed Offer state
- company isolation is enforced
- Filament UI is simple and usable
- all static UI/domain labels are localized in Arabic and English according to existing project conventions, including Relation Manager tabs/labels
- MySQL and PostgreSQL tests pass
- relevant Phase 07.3 / 07.4 regressions pass
- no future-phase functionality was accidentally implemented

