# Madar — Phase 07.7 | Contracts & Agreements Engine
## Final Domain Specification and Unified Implementation Plan

**Status:** Approved implementation specification based on the decisions finalized during the discussion. One integrated delivery; do not split into three or four separate implementation phases.  
**Scope:** Contract creation, versions, parties, terms, templates, review, approval, documents, signatures, activation, formal amendments, audit trails, and reservation integration.  
**Out of scope:** Building or generating installments, payments, collections, or payment-gateway integrations. These belong to a new financial engine in Phase 07.8.

> **Non-negotiable decision — NEW DOMAIN FIRST**  
> This is a new, independent domain design, not an extension of the legacy `Lease`, `PlanStrategy`, or installment/payment system. Implement this specification even if that requires replacing code or retiring the old design. Inspect legacy code **only to identify dependencies and avoid breaking functionality or losing data**, never to copy its architecture or preserve its business-model constraints. Legacy components may be replaced or removed once all usages and data have been handled safely. **Do not create a new payment engine in this phase.**

---

## 1. Domain Objective

Madar is a multi-tenant real estate platform requiring a single contract engine for agreements that can be reviewed, approved, signed, and historically audited. The initial supported types are:

- **Sale Contract:** Sale of an actual unit.
- **Off-plan Sale Contract:** Sale of a planned unit within a project and its planning structure before an actual unit exists, with safe linkage to an actual unit when one becomes available.
- **Lease Contract:** Lease of an actual unit for a specified period, allowing consecutive non-overlapping agreements.
- **Extensibility:** Additional agreement types through type-specific policies and rules without duplicating the shared contract lifecycle.

A contract can be created **from an existing reservation** or **directly without a reservation**. It must be accessible through an independent Filament `ContractResource` and appropriate relation managers under reservations, units, and projects where genuine relationships exist. The contract is the **source of truth for the signed legal agreement**. Reservations, property data, and templates do not replace the immutable agreed contract version.

## 2. Boundaries with Existing Systems

| System | Agreed interaction |
|---|---|
| `Reservation` / commercial workflow | Optional source when creating a contract. Capture a snapshot at creation; subsequent reservation changes must not rewrite historical snapshots. |
| `parties` | Reuse existing party identity through `party_id`; do not create a duplicate party master table. |
| `documents`, `document_versions` | Reuse the existing upload, versioning, storage, authorization, and polymorphic relations. No new `contract_documents` table or parallel upload subsystem. |
| Templates | Provide initial clauses; copy operative terms into a contract version so later template updates cannot alter existing agreements. |
| Legacy `leases` / old installment and payment planning | **Not an authoritative domain model** for the new engine. Do not copy its schema, fields, workflow, or installment generators. Examine dependencies only for safe migration. |
| New Phase 07.8 finance engine | An activated contract exposes trustworthy commercial data for a future independent finance engine. It does not generate installments in Phase 07.7. |

**Explicit principle:** Do not name or model the new unified contract engine as `Lease` simply because the legacy code uses that term. Sale, lease, and off-plan sale are types within the same `Contract` domain.

## 3. Ten Agreed New Tables

These tables form the **agreed reference architecture**. The coding agent may examine the existing schema to choose integration details and column conventions, but must not change the core domain relationships merely to accommodate legacy code.

### 3.1 `contracts` — Stable Contract Identity

Represents the agreement across all its versions. Relevant fields include `id`, `company_id`, `contract_number` (unique within a company), `contract_type`, `status`, optional source/target references such as `reservation_id`, `property_id`, `project_id`, `unit_id`, and/or `planned_unit_id` depending on contract type; current/effective version references where useful; operational timestamps; and creator details. Reference fields must be **nullable when the business scenario requires**, rather than being forced into the old Lease shape. Any derived or duplicated data must have explicit consistency rules.

### 3.2 `contract_versions` — Immutable Historical Agreements

Fields include `contract_id`, `version_number`, `status`, optional `template_version_id`, reportable commercial fields such as `price`/`total_amount`, `currency`, `start_date`, `end_date`, and `delivery_date`; issue/freeze/approval metadata; `unit_snapshot` JSON; `reservation_snapshot` JSON; and small type-specific snapshots where warranted. There is **no separate `contract_snapshots` table**. Fields needed for filtering or reporting must not exist only inside JSON. Version numbers must be unique within each contract. Version 1 is created as an editable draft, **not** as an immediately frozen version.

### 3.3 `contract_parties` — Parties per Contract Version

Fields include `contract_version_id`, `party_id` (FK to existing `parties`, nullable only where supported by an explicit domain reason), `role`, and historical snapshots of names, identifiers, addresses, representatives, ownership percentages, and other relevant attributes. The stable `party_id` preserves party identity, but subsequent edits to the master `parties` record must not alter who participated in V1, in what capacity, or with which share. When V2 is created, its party rows are intentionally copied as **independent historical records**.

### 3.4 `contract_terms` — Operative Terms in a Specific Version

Fields include `contract_version_id`, term type, ordering, title, text, structured metadata as necessary, and optional references to a `contract_party_id` **belonging to the same version**. Terms may express commercial **payment intent** (such as total amount, down payment, or agreed scheduling arrangement), but must not create actual installments or receivables. Avoid duplicating authoritative financial fields without an explicit consistency mechanism.

### 3.5 `contract_templates` — Company- and Type-Specific Templates

Company-owned, contract-type-specific templates with lifecycle status and management metadata. Support an appropriate default template per type when needed, respecting tenant-specific access controls.

### 3.6 `contract_template_versions` — Template History

Immutable template revisions containing their structure, legal text, and default clauses. On selection, copy the applicable clauses into `contract_terms`; link the `contract_versions` row to the source template version for traceability. Editing a template later must not mutate existing contracts.

### 3.7 `contract_approvals` — Approval of a Specific Version

Fields include `contract_version_id`, approving/reviewing user, decision/status, `reviewed_at`, reasons/comments, and necessary audit metadata. Approval applies only to the **exact version reviewed**; approval of V1 must not automatically authorize V2.

### 3.8 `contract_signatures` — Signature Evidence

Fields include `contract_version_id`, `contract_party_id` (from the same contract version), signatory or authorized representative, signing method/evidence, `signed_at`, a reference to the exact existing `document_version_id`, and a **SHA-256 digest of the actual signed file bytes** or the official signed content frozen at that time. A hash alone does not prove signatory identity or legal enforceability. Keep appropriate evidence and do not present internally documented manual signing as a universally qualified legal e-signature service.

### 3.9 `contract_status_histories` — Workflow Transition Audit

Fields include `contract_id`, applicable version reference, `from_status`, `to_status`, `actor_id`, `reason`, timestamps, and relevant metadata. Persist important transitions in the same database transaction as the actual change. Audit history must be **append-only**.

### 3.10 `contract_amendments` — Formal Changes After Signing

References the original contract and the signed contract version being amended; tracks amendment type, reason, scope, lifecycle state, and resulting version/document references. **Never modify the signed contract or its original PDF in place.** Reuse the same draft → review/approval → signing services, with additional amendment-specific domain rules, rather than implementing a duplicate workflow. An amendment becomes effective only after the necessary approvals/signatures and according to its applicable effective date.

### Reused Tables — Do Not Rebuild

- `parties`: Canonical person/company identity.
- `documents` and `document_versions`: Contract files, legal document versions, and attachments through the established polymorphic relations, storage, and policies.

**Core relationship structure:**

```text
Contract
├── hasMany ContractVersion
│   ├── hasMany ContractParty ── belongsTo existing Party
│   ├── hasMany ContractTerm
│   ├── hasMany ContractApproval
│   ├── hasMany ContractSignature ── references exact existing DocumentVersion
│   └── morphMany existing Document ── hasMany existing DocumentVersion
├── hasMany ContractAmendment
└── hasMany ContractStatusHistory

ContractTemplate
└── hasMany ContractTemplateVersion
```

General supporting documents may attach at contract level only if the existing document system properly supports that relationship. **Any legal document that was reviewed or signed must be linked to the specific contract version.**

## 4. Contract Lifecycle and Workflow

```text
Create Contract (from Reservation or Direct)
    ↓
Contract + Version 1 (DRAFT: editable)
    ├── manage parties / terms / commercial data / documents
    ├── optional copy from specific ContractTemplateVersion
    └── validate company, party, unit/planned unit, source and permissions
    ↓
Submit for Review  ⇒ freeze version content
    ↓
Review / Approval
    ├── Approved ⇒ issue/finalize reviewable PDF ⇒ collect signatures
    └── Revision Required ⇒ create Version N+1 (DRAFT copy)
                                    ↓
                               resubmit and reapprove
    ↓
Required signatures on EXACT approved version & document version
    ↓
Activate Contract after business/availability/precondition checks
    ↓
Amendment if needed (separate formal record, same reusable engine)
```

**Mandatory workflow rules:**

1. Only **drafts** can be edited in place. Submission freezes the version's commercial data, parties, and terms for review. Subsequent substantive changes require a new version; never rewrite frozen V1.
2. Every approval and signature references `contract_version_id` and cannot be carried forward automatically. Altering a final signed document requires a legitimate new version and signing flow, never a silent file replacement.
3. Workflow-controlled `status` fields must not be editable through generic CRUD forms. Transitions occur through explicit domain actions with authorization, validation, and transactional integrity.
4. Define enforceable version states: Draft, Submitted/Under Review, Approved, Signed, Superseded, plus any narrowly justified substates. Do not allow two competing effective versions.
5. `Contract.status` represents the operational state of the entire agreement; `ContractVersion.status` represents the state of an individual version. Do not conflate them. Cancellation, termination, and expiry require valid transitions and audit records.
6. Reject signing an unapproved draft, activation without type-specific prerequisites, changes to a signed version, and any cross-company approval or signature.
7. After signing, legally substantive changes must use a formal Amendment linked to the original and resulting versions. Preserve the prior signed version, and do not prematurely terminate its legal effect before the amendment's effective date under the relevant rules.

## 5. Commercial Availability and Contract Conflicts

- **Do not** enforce `unique(unit_id) WHERE active` across all contract types. Consecutive leases are legitimate, and future non-overlapping leases can coexist; off-plan targets are different as well.
- **Lease:** Reject prohibited overlaps in the occupancy/right-of-use periods for the same unit, accounting for cancelled/terminated states, reservations, extensions, and renewals. Do not reject consecutive periods simply because they reference the same unit.
- **Sale / Off-plan Sale:** Prevent conflicting sales rights over actual or planned units according to reservation/offer rules and the point when a binding commitment is made. Properly distinguish planned from actual units and their mapping.
- Run conflict checks at consequential workflow transitions, not only when creating a draft. **Recheck before issuing a binding commitment and before activation.** Use appropriate transactional locks and atomic checks to prevent race conditions.
- If a contract originates from a reservation, do not treat **that same source reservation** as a conflicting claim, while preventing the reservation from producing contradictory effective agreements.
- Reuse `CommercialAvailabilityService` **if it exists and actually enforces the necessary rules**. Otherwise extend or create a shared domain service. Merely finding an old service does not mean its existing behavior is acceptable; avoid scattering conflicting checks across controllers.
- Implement constraints that are actually supported by the deployed database (MySQL or PostgreSQL) and suitable indexes such as `(company_id, status)`, `(contract_id, version_number)`, and target/date access paths. A PostgreSQL partial unique index must not be assumed to work unchanged in MySQL.

## 6. Signatures and Documents

- The approved legal PDF/document is linked to a specific contract version and the exact `document_version_id` in the existing document subsystem.
- When recording a signature, calculate SHA-256 from the **actual stored file contents**, not a filename or URL; store the digest and immutable version reference.
- Reuse existing upload, storage, document-versioning, and polymorphic relations; do not implement a parallel upload system.
- Do not integrate an external electronic-signature provider or claim universal legal validity without explicit identity verification, provider requirements, and applicable legal context.
- Never overwrite a signed file. New versions, files, and signatures must remain independently retrievable.

## 7. Terms, Templates, and Financial Boundaries

- Selecting a specific template version copies its initial text and clauses into the contract's editable draft version.
- Operative terms, price, currency, and due-date agreements are **commercial commitments**. Generating installment schedules, receivables, and collecting money belong exclusively to the **new Phase 07.8 finance engine**.
- **Do not depend on `leases`, `installments`, `PlanStrategy`, or any legacy generator to complete Phase 07.7.** Do not create new installments or payments tables now.
- Preserve clear and internally consistent financial facts sufficient for future integration: contract/version identity, agreed commercial terms, and effective dates, without guessing the future financial schema.

## 8. Laravel / Filament Implementation

- Implement migrations, Eloquent models and relations, useful enums/value objects, policies, services/actions, Filament `ContractResource`, and applicable relation managers.
- Support both reservation-sourced and direct contract creation through a common application/domain entry point. Respect the project's existing company-isolation global scope/trait; carefully audit sensitive `withoutGlobalScopes()` queries and explicitly validate tenant ownership.
- Prevent cross-company references using Form Requests, policies, and domain service validation; a generic foreign-key `exists` check is not sufficient.
- Put multi-record workflow transitions in `DB::transaction`, use `lockForUpdate` where needed, and ensure retryable operations are idempotent.
- Prevent N+1 queries with deliberate eager loading, pagination, filtering, and indexing for Filament/API. Do not duplicate business rules in resources or controllers.
- Enforce contract-number uniqueness within a company and version-number uniqueness within a contract, including under concurrent creation.
- Apply contract-type-specific policies for eligibility, availability, and signature requirements on top of a shared workflow. Avoid a giant model with unmaintainable conditional branches.
- Handle nullable relationships correctly: planned unit without actual unit, direct contract without reservation, and other legitimate cases.

## 9. Legacy System Handling — Implementation Rule

1. Inspect existing `Lease`, installment/payment-plan classes and referring routes, jobs, views, and tables **only** to understand the impact of change.
2. **Do not** reuse the old design as the source of truth, assume every contract is a Lease, or constrain the new schema to legacy field names.
3. Build the complete new engine independently. Where legacy features conflict, migrate callers to the new engine, isolate old functionality, or remove it when the replacement is ready, as appropriate.
4. Removing old tables is permitted **only after** examining dependencies, existing data, and migrations and preparing a safe, testable migration. Never drop production data arbitrarily or break existing pages or APIs. Report unresolved data-loss risks explicitly rather than silently deleting data.
5. Keeping legacy code temporarily does not justify structural coupling to the new contracts engine, nor does it mean contract delivery must wait for the new payment engine.

## 10. Final Acceptance Tests — One Delivery

**Core:** Direct and reservation-based creation; Sale/Off-plan/Lease types; tenant isolation; party snapshots; immutable copies of terms/templates; draft editing; sequence/number generation.

**Versions:** Submit and review V1, then request changes and create V2. V1 data and approvals remain unchanged. Parties may differ between V1 and V2 while maintaining the same canonical `party_id` where applicable. Material changes to frozen versions create a new revision.

**Approvals / Signatures:** Disallow approval or signing in invalid states; bind signatures to the correct version, party, document version, and actual digest. Reject overwriting a signed document. Verify append-only transition logs, authorization, and tenant isolation.

**Availability:** Allow consecutive leases, reject overlapping leases, reject double sales; ignore the contract's own source reservation as a conflict; validate company/unit/project correspondence and test concurrency/atomic transitions where feasible.

**Amendments:** A post-signature amendment leaves the original intact, follows reusable approval/signature services, and creates a traceable formal record.

**Integration:** Existing polymorphic documents/document versions; Filament pages and actions; eager loading; no call to legacy `PlanStrategy` and no installment generation; no regressions when replacing legacy usages.

## 11. Definition of Done

**One unified implementation task for Phase 07.7 — not separate deliveries.** Completion requires a functioning end-to-end engine for contract creation, versions, parties, terms, templates, approvals, supporting documents, signatures, activation, formal amendments, and passing acceptance tests. Provide a final report covering changed tables/files, remaining risks, and any implementation decisions the agent needed to make.

Local implementation details depending on the actual codebase—class/action names, Filament placement, morph types, storage configuration—should be resolved by inspecting the project. **Do not reopen settled domain decisions simply because legacy code differs.** If a genuine conflict prevents compliance with this domain specification, report the specific conflict and viable options before making a substantive design change.
