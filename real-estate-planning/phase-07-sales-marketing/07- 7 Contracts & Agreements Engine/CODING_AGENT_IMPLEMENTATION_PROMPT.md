# Coding Agent Task — Madar Phase 07.7: Complete Contracts & Agreements Engine

> **READ FIRST:** The attached `PHASE_07_7_DOMAIN_SPEC.md` is the binding domain specification. Implement **all of Phase 07.7 as ONE integrated feature / ONE delivery**. Do not re-scope it into 3–4 separate user-facing implementation phases, and do not defer approval, signing, or amendments to another delivery. You may order the work internally by dependency and commit safely, but the assignment is not finished until the complete engine and tests work together.

## Absolute priority: new domain over legacy

**Implement the NEW contract domain as specified, regardless of how the old Lease / installments / payment-plan code was designed.** The legacy system is NOT your architecture, source of truth, compatibility template, or constraint. Do not map every contract into the old `leases` model. Do not reuse legacy payment schedule generation as a prerequisite. Do not use an old `PlanStrategy` to define contract terms or decide the new schema. The future payment/installment/collection engine will be newly designed in Phase 07.8.

You **may replace, disconnect, retire, migrate away from, or delete legacy components** if necessary to implement the new domain correctly. First inspect dependencies to prevent accidental breakage/data loss; where real data exists, use a safe migration path rather than destructive assumptions. A legacy integration must never force a change to the approved new domain. If safe deletion is not possible within this task, isolate and document it while the new engine stays fully independent. Do not quietly drop data.

## Implement the complete domain, not just CRUD

Create/update migrations, Eloquent models, relationships, DTOs/Enums when useful, services/actions, policies, validations, Filament resources and relation managers, shared domain checks, tests, and integration points for all of the following **ten new tables**:

1. `contracts` — stable contract identity, company, type, lifecycle, source (optional reservation), relevant actual/planned target.
2. `contract_versions` — independent, frozen legal/commercial revisions; initial Version 1 draft editable until submission; historical snapshots + reportable columns.
3. `contract_parties` — attached to **contract_version_id**, each row retains the existing `party_id` plus frozen name/identity/role/share/representation as applicable.
4. `contract_terms` — clauses/commercial intent attached to the specific version; optional links must not cross version/company boundaries.
5. `contract_templates` — per-company/type template identity.
6. `contract_template_versions` — immutable template revisions; copy starting clauses into `contract_terms` when composing contract draft.
7. `contract_approvals` — approvals/reviews anchored to the exact immutable version.
8. `contract_signatures` — who signed which exact approved version and immutable existing document_version; preserve signed-file SHA-256.
9. `contract_status_histories` — reliable append-only workflow audit.
10. `contract_amendments` — formal post-signature changes with immutable reference to original, reusing common review/approval/signature services.

**Reuse existing models/tables:** `parties`, `documents`, `document_versions` with the current morph/document upload/versioning mechanism. **Do not create** `contract_documents`, `contract_document_versions`, `contract_snapshots`, a second file uploader/storage pipeline, or a parallel Parties directory. Use `unit_snapshot` / `reservation_snapshot` JSON within `contract_versions`, plus first-class columns for reportable data.

### Required workflows

- Create a **Sale**, **Off-plan Sale**, or **Lease** contract either directly or based on a relevant existing reservation; maintain correct company/project/property/actual-or-planned-unit linkage. Nullable associations are intentional when the real business workflow permits it.
- A contract begins with **Version 1 / editable Draft**. Draft edit actions update the draft, not a new revision for each keystroke.
- On **Submit for Review**, freeze the submitted version's commercial/legal content, party snapshots, and attached documents. A normal review correction returns that same pre-approval version to `Revision Required`, where it may be corrected and resubmitted. A new version is only required for an approved legal revision or post-signature amendment.
- If substantive legal/commercial terms change after freeze, create **Version N+1** from a historical snapshot inside a transaction; copy the relevant version-owned parties/terms, then modify the new draft only. Never mutate frozen/approved/signed history. Approvals of V1 must not approve V2.
- Generate/use legally reviewable **PDF document versions** through the existing document system; lock approval/signature to the exact file and `contract_version_id`. Store a SHA-256 digest of the **actual signed file bytes**, signatory evidence/method, timestamps and relevant party/representative. Never claim full legal equivalence to qualified third-party digital signing unless actually integrated and validated.
- Only the designated signed/approved version may **activate**, after rechecking appropriate company, reservation, unit availability, dates, signers and required approvals. Record transitions immutably.
- **Amendments** after signing: formal process linked to the original signed version; use shared drafting/review/approval/signature components, preserve original signed data, and record the effective changed agreement without overwriting old evidence.
- `ContractResource` in Filament sidebar with necessary relation managers and context actions in existing reservation/unit/project UI when appropriate. No duplicated business logic across UI entry points.

### Non-negotiable correctness

**Concurrency / commercial availability:** A blanket unique active contract per unit is wrong. Sequential leases with non-overlapping periods must be allowed; overlapping claims forbidden. Sold/off-plan units need rules for actual vs planned unit and conflicting reservations/contracts. Handle the originating reservation correctly so it does not conflict with its own contract. Check in protected transactions when making binding transitions and use locks/constraints appropriate to MySQL/PostgreSQL; do not rely on read-only availability checks. Reuse an existing `CommercialAvailabilityService` only if its actual code supports this new domain, otherwise safely adapt/build the shared service.

**Workflow invariants:** No direct status dropdown bypass; guards for current state, permissions, tenant, cross-company references, required parties/terms/docs/approvals/signatures; transactions + locking for multi-record operations; stable version number allocation under concurrency; signed versions/documents cannot be silently altered or deleted; no approvals/signatures leakage across versions.

**Tenant/security/performance:** Respect the project's existing company global-scope trait and policies; never rely solely on hidden UI controls. Explicitly reject cross-company IDs. Inspect uses of `withoutGlobalScopes()`, eager load for lists/detail to prevent N+1, add indexes and uniqueness checks, validate all uploads with existing infrastructure, paginate resources, and make workflow actions idempotent where applicable.

**Finance boundary:** Store contract financial/commercial **intent** only. **DO NOT generate installments, payments, collections, invoices, or payment schedules in Phase 07.7** and DO NOT import the old `leases`/`PlanStrategy` flow to do so. Expose a clean, stable contract activation data boundary for the NEW finance phase 07.8. This new finance engine is not part of your assignment.

## Required working method (not phased delivery)

1. Read the domain spec fully and inspect the **actual repository**: migrations, existing Parties and polymorphic Documents/DocumentVersions, company global-scope trait, Filament panel/resources, commercial/reservation and planned-vs-actual unit architecture. Inspect legacy Lease/installment references **solely for transition safety**, not for business design.
2. Implement all necessary schema and services as **one coherent vertical feature**. Internal implementation order is your choice; do not stop or request approval after Core or Approval or Signing if the task can proceed.
3. Reuse the existing document morph, document versions, file upload, policies and storage. Do not hard-code nonexistent relation/morph names: inspect code and use its conventions.
4. Implement the complete UI/API/workflow, database protections, and tests together. Keep implementation clean, modular, extensible for future contract types, and consistent with repository conventions.
5. Run migrations/tests/lint/build as applicable, report what passed/failed, and fix failures before calling the task complete.
6. Produce a final report: new/changed files, implemented workflow and permission points, integrations reused, legacy components removed/isolated, tests run and results, plus any unresolved blockers **grounded in observed code**. No speculative “done” claims.

## Acceptance scenarios — MUST be covered by automated tests wherever feasible

- Sale contract from an active reservation; direct sale contract without reservation; off-plan contract to a planned unit; lease contract over valid dates.
- Create V1, edit V1 Draft, submit/freeze, request revision, create/edit V2, retain V1 immutable with its own parties/terms and review evidence.
- V1 with sole buyer; V2 with second buyer/change in shares: `party_id` stable reference, each version shows **correct historical party snapshot**.
- Change a company template after contract creation; existing terms are unchanged.
- Approval and signature belong to one exact contract version and document version; reject mismatched party/file/version and hash tampering.
- Activate only approved/signed contracts after real conflict/availability checks; cannot bypass workflow by editing status.
- Two consecutive non-overlapping lease agreements may coexist; overlapping agreements and conflicting sale/off-plan claims must be rejected safely; avoid self-conflict with originating reservation.
- An amendment to a signed contract follows review/signature rules and never mutates earlier signed versions or PDF.
- Tenant isolation on every read/write/action/morph document; no cross-company party/unit/project/template/reservation IDs.
- No installments/payments generated, and no dependency on old Lease/PlanStrategy required for these scenarios.

## Important decisions already settled — do not reopen without a concrete code blocker

- Keep `contracts` separate from `contract_versions`.
- Keep `contract_parties` on **version**, not merely on `contracts`; preserve existing `party_id` + historical attributes.
- Initial draft editable; new version after official freeze for substantive changes.
- Reuse existing `parties` and polymorphic `documents`/`document_versions` instead of duplicates.
- Preserve approval and signature references to the exact version and PDF; record SHA-256.
- Reuse common workflow services for amendments rather than duplicating code.
- Do **one complete Phase 07.7 delivery**, including amendments and thorough testing.
- Ignore old Lease/payment architecture as design guidance even if it is ultimately removed; build Phase 07.8 finance **later**, separately.

**Begin implementation now.** Ask for clarification only if the repository reveals a *real, blocking* contradiction that cannot be resolved without choosing between mutually exclusive business semantics. Do not invent new high-level phases or move approved functionality out of Phase 07.7.
