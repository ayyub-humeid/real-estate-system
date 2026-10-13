# Phase 07.3 — Implementation Prompt
## PROMPT.md

You are implementing **Phase 07.3 — Leads, Opportunities & CRM Kanban** for **Madar | مدار**.

Implement this phase completely and only after reading the project documentation and inspecting the existing codebase.

---

# 1. Read Before Coding

Before changing code, read:

1. `real-estate-planning/README.md`
2. all global planning / architecture / conventions files
3. tenancy and company-isolation rules
4. permissions / Spatie / Shield conventions
5. previous relevant phase documentation
6. Phase 07 main `SPEC.md` and `PROMPT.md`
7. Phase 07.2 Listings documentation
8. this `07.3` `SPEC.md`
9. all relevant documentation under `docs/`
10. **both Arabic and English documentation**

Do not ignore Arabic docs because English docs exist.
Do not ignore English docs because Arabic docs exist.

Use the current repository as the implementation truth for existing models, naming conventions and shared abstractions.

If documentation conflicts materially, prefer:
1. explicit Phase 07.3 decisions in this SPEC
2. global project architecture / tenancy conventions
3. existing stable code conventions

If a conflict could cause destructive or incompatible implementation, report it clearly instead of silently inventing behavior.

---

# 2. Environment

The environment is already working.

- Laravel Herd is installed and running.
- PHP is working.
- Composer is working.
- the Laravel app is running.
- database connectivity already works.

Do not reinstall or rebuild the local environment.

Do not spend implementation time troubleshooting Herd unless a real Phase 07.3 issue proves the environment is involved.

---

# 3. Scope

Implement:

- Leads
- Lead deduplication
- Lead inquiry/source tracking
- Opportunities
- Lead owner / Opportunity owner
- Manual assignment
- Round Robin assignment
- Unassigned Queue
- CRM Pipelines
- CRM Pipeline Stages
- default Pipeline provisioning
- default Stage provisioning
- CRM Kanban
- per-company Stage customization
- Stage Requirements
- requirement evaluators
- Opportunity stage workflow protection
- Won/Lost behavior
- Stage history / audit
- Arabic + English Filament UI
- RTL support
- tests on MySQL and PostgreSQL

Do not implement:

- WhatsApp Business API
- Facebook / Instagram integration
- AI message analysis
- full Activities module
- formal Offers
- Reservations
- Contracts

Keep the architecture ready for those later phases.

---

# 4. Important Business Rules

## Lead

- One customer should normally map to one Lead per company.
- The same Lead may have many Opportunities.
- Lead status is only:
  - active
  - inactive
- Do not implement `converted`.

## Deduplication

Match inside the same company using:

1. normalized phone
2. normalized email

Never merge by name alone.

If phone/email conflict between different Leads, do not auto-merge.

When a duplicate is detected, present a safe path to:

- open existing Lead
- add inquiry
- create Opportunity under the existing Lead

Never deduplicate across companies.

---

# 5. Lead Intake

Implement or reuse a clean application/domain service boundary for intake, for example:

```text
LeadIntakeService
```

Generic inquiry:

```text
find/create Lead
create inquiry
do not require Opportunity
```

Specific Listing inquiry:

```text
find/create Lead
create inquiry
create Opportunity in New stage
```

Do not auto-qualify the Opportunity.

This service must be reusable later by WhatsApp / Facebook / Instagram / Website integrations.

Do not duplicate intake logic inside Filament.

---

# 6. Opportunity

Support:

```text
lead
optional listing
sale/rent
pipeline
stage
owner
budget range
currency
preferred property type
preferred location
preferred bedrooms
expected close date
notes
source
optional source reference
```

`listing_id` must be nullable.

Do not persist manual win probability.

If a win probability accessor is introduced, it must be calculated/derived, not stored as authoritative database state.

---

# 7. Ownership and Assignment

Keep Lead owner and Opportunity owner separate.

Default a new Opportunity to the Lead owner when appropriate.

Authorized users may reassign the Opportunity.

Support company assignment modes:

```text
manual
round_robin
unassigned_queue
```

Default:

```text
manual
```

Round Robin must be tenant-safe and concurrency-safe.

Use existing company settings infrastructure where appropriate.

---

# 8. CRM Pipelines and Stages

Implement company-owned Pipelines and Stages.

Pipeline = complete Kanban board.

Stage = Kanban column.

Provision a default Pipeline for each company.

Default stages:

```text
New
Contacted
Qualified
Viewing
Negotiation
Won
Lost
```

Companies can:

- rename visible labels
- reorder stages
- add stages
- deactivate stages
- configure requirements

Do not make workflow logic depend on label text.

Use protected internal semantics such as:

```text
stage_type = open | won | lost
```

A visible label like `Deal Closed` may still have:

```text
stage_type = won
```

---

# 9. Kanban — Implement Now

Implement the CRM Kanban in this phase.

Requirements:

- board displays Opportunities
- board is scoped to the current company
- board supports Pipeline selection if more than one exists
- each Stage is a column
- each Opportunity is a card
- support drag/drop or an equivalent polished move interaction
- movement must call backend workflow logic
- rejected movement must restore UI state and show a clear translated error
- Arabic Kanban must render correctly in RTL
- English Kanban must render correctly in LTR

Suggested concise card content:

- Lead name
- Listing / target if present
- Sale / Rent
- Budget context
- Opportunity owner
- source

Do not mix Phase 07.4 follow-up/activity UI into the Kanban.

Most important rule:

> Drag/drop is not authorization and not workflow validation.

Every move must be revalidated on the backend.

---

# 10. Stage Requirements

Implement company-customizable Stage Completion Requirements.

Use a dedicated table for requirements.

Do not store all requirements as one Stage JSON document.

Use JSON only for type-specific configuration.

Support a safe evaluator architecture.

Initial supported requirement types should include useful generic concepts such as:

```text
field_required
field_equals
related_record_exists
checklist_completed
```

Do not allow custom PHP or SQL from company users.

Each requirement supports:

- label
- type
- config
- required yes/no
- order
- active yes/no

Provision sensible default requirements for the default Pipeline.

The company can edit/disable/add supported requirements.

---

# 11. Stage Movement Rules

Implement stage movement through a domain/application service, not direct model updates from Filament.

Example concept:

```php
OpportunityWorkflowService::moveToStage(...)
```

Before movement validate:

- permission
- company ownership
- same Pipeline
- active target Stage
- current Stage required completion rules
- target-entry rules if implemented
- Won/Lost requirements
- terminal stage rules
- no cross-company references

Use DB transactions where appropriate.

If required data is missing, return a useful translated message listing missing requirements.

Example:

```text
Cannot move to Qualified.
Missing:
- Contact result must be recorded.
```

Arabic equivalent must exist.

---

# 12. Completion Requirements Are Real Rules

Do not implement fake checkboxes for conditions that the system can verify directly.

Examples:

```text
budget_max required
```

should inspect actual Opportunity data.

For true checklist confirmations, persist a completion record with:

- opportunity
- requirement
- completed_by
- completed_at
- optional notes

---

# 13. Won / Lost

## Lost

Require an analytics-friendly reason.

Use structured internal values plus translated labels.

Allow optional notes.

## Won

Close the CRM Opportunity only.

Do not:

- reserve a unit
- change Listing status
- create contract
- create sale/rental legal state

Won/Lost belong to CRM only in this phase.

Ordinary Kanban movement must not reopen terminal records.

Examples:

```text
Won → New ❌
Lost → Negotiation ❌
```

If future reopening is needed, it must be a separate explicit domain action.

---

# 14. Stage History

Persist immutable Stage transition history.

Capture:

- opportunity
- from stage
- to stage
- changed by
- changed at
- useful metadata if needed

This history will support future analytics such as time-in-stage and AI scoring.

---

# 15. Localization — Mandatory

Everything added in 07.3 must work in:

- English
- Arabic

Add translation keys/files for all new user-facing strings.

This includes:

- navigation
- resources
- forms
- tables
- filters
- actions
- validation messages
- duplicate warnings
- Kanban stages/settings UI
- requirements UI
- assignment UI
- Won/Lost UI
- workflow errors

Do not hard-code user-facing English strings.

Arabic must support RTL correctly.

Arabic is part of Definition of Done.

Do not postpone it.

---

# 16. Filament

Follow existing project Filament conventions.

Implement appropriate resources/pages for:

- Leads
- Opportunities
- CRM Kanban
- CRM Settings / Pipelines
- Stages
- Stage Requirements

Do not introduce a separate UI framework.

Use existing authorization / Shield patterns.

If a Kanban package/component is considered, first verify compatibility with the project's Laravel/Filament versions and avoid introducing unnecessary package risk. A clean custom Filament implementation is acceptable.

---

# 17. Multi-Tenancy

Company isolation is mandatory on every CRM entity.

Reject:

- cross-company Lead/Opportunity relations
- cross-company employee assignment
- cross-company Pipeline/Stage selection
- cross-company Stage Requirements
- cross-company Listing association

Do not rely only on UI filtering.

Validate ownership in backend services/policies.

---

# 18. Permissions

Reuse existing Spatie/Shield conventions.

Create/generate permissions for the new CRM resources and custom workflow actions.

Examples:

- view/create/update Leads
- assign Leads
- view/create/update Opportunities
- assign Opportunities
- move Opportunity stage
- close Won
- close Lost
- manage Pipelines
- manage Stages
- manage Requirements

Follow existing Super Admin behavior.

---

# 19. Database

Inspect the repository first.

Likely concepts:

```text
leads
lead_inquiries
opportunities
crm_pipelines
crm_pipeline_stages
crm_stage_requirements
opportunity_requirement_completions
opportunity_stage_histories
```

Do not create duplicate tables if equivalent structures already exist.

All migrations must work on:

- MySQL
- PostgreSQL

Avoid DB-engine-specific implementation when possible.

Add indexes/unique constraints deliberately for:

- company scoping
- normalized dedup fields where appropriate
- Pipeline/Stage ordering
- stage lookups
- assignment queries

Do not create global uniqueness that breaks multi-tenancy.

---

# 20. Tests

Create focused Feature tests.

At minimum test:

- Lead create
- phone duplicate detection
- email duplicate detection
- duplicate scoped per company
- conflict does not auto-merge
- multiple Opportunities per Lead
- generic inquiry
- Listing-specific inquiry
- separate Lead/Opportunity owners
- cross-company assignment rejection
- manual assignment
- round robin
- unassigned queue
- default Pipeline
- default Stages
- default Requirements
- Stage labels are customizable
- workflow does not depend on labels
- Stage reordering
- inactive Stage protection
- successful Kanban stage move
- failed move when requirement missing
- translated workflow failure response where practical
- Lost requires reason
- Won does not create Reservation/Contract
- terminal reopening blocked
- immutable Stage History
- requirement completions
- tenant isolation
- permissions
- MySQL
- PostgreSQL

Rerun relevant Phase 07.2 Listings and Unit Setup regression tests.

---

# 21. Implementation Quality

Use:

- services/domain layer for workflow
- DB transactions
- PHP Backed Enums where appropriate
- policies/permissions
- company scoping
- eager loading where needed
- indexes
- immutable audit/history where appropriate
- clear validation

Avoid:

- status dropdowns that bypass workflow
- direct Stage mutation from generic forms
- hard-coded English UI
- logic based on translated labels
- duplicated intake logic
- arbitrary SQL/PHP requirement rules
- speculative abstractions unrelated to 07.3

---

# 22. End-of-Work Verification

Before declaring 07.3 complete:

1. run migrations on MySQL
2. run 07.3 tests on MySQL
3. run relevant regressions on MySQL
4. run migrations on PostgreSQL
5. run 07.3 tests on PostgreSQL
6. run relevant regressions on PostgreSQL
7. verify English Filament
8. verify Arabic Filament
9. verify RTL Kanban
10. verify company isolation
11. verify permissions
12. verify drag/drop cannot bypass workflow
13. verify customization of Stage labels/order/requirements
14. verify default provisioning

---

# 23. Final Report

When done, report:

- files created
- files modified
- migrations added
- models/enums added
- services added
- policies/permissions added
- Filament resources/pages/components added
- translation files/keys added
- Kanban implementation summary
- default Pipeline/Stage/Requirement provisioning
- tests added
- MySQL test result
- PostgreSQL test result
- regression test result
- any manual UI checks still required
- any unresolved issue

Do not simply say "completed".

Provide concrete verification.

---

# 24. Final Boundary Reminder

Phase 07.3 must end with a strong CRM core:

```text
Lead
→ Inquiry
→ Opportunity
→ Custom Pipeline
→ Protected Kanban Stages
→ Won / Lost
```

It must **not** continue into:

```text
Activities (07.4)
Offers (07.5)
Reservations (07.6)
Meta/WhatsApp integration (07.7)
AI automation/scoring (07.8)
```

Design for those phases, but do not implement them now.
