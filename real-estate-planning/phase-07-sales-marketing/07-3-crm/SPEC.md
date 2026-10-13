# Phase 07.3 — Leads, Opportunities & CRM Kanban
## SPEC.md

> Project: **Madar | مدار**
>
> Scope: Phase 07.3 only.
>
> This phase builds the CRM foundation on top of Phase 07.2 Listings Management. It must remain multi-tenant, customizable per company, bilingual (Arabic/English), and ready for later WhatsApp / Facebook / Instagram / AI integrations without implementing those external integrations now.

---

# 1. Mandatory Context Before Implementation

The implementation agent must read the existing project documentation before changing code.

At minimum, read:

- `real-estate-planning/README.md`
- all global architecture / conventions / tenancy / permissions / workflow documents
- previous relevant phase specs and prompts
- Phase 07 main `SPEC.md` and `PROMPT.md`
- Phase 07.2 Listings documentation
- any project documentation under `docs/`
- **both Arabic and English documentation**

Arabic documentation must not be ignored in favor of English documentation, and vice versa.

The agent must also inspect the current codebase before designing new abstractions.

If an existing model/service/table already solves part of this phase cleanly, reuse or extend it instead of duplicating it.

---

# 2. Environment Assumptions

The local environment is already working correctly.

- Laravel Herd is installed and running.
- PHP is working.
- Composer is working.
- The application is already running locally.
- Existing database connections are operational.
- MySQL and PostgreSQL compatibility must be preserved.

Do not waste time reinstalling or reconfiguring Herd, PHP, Composer, Laravel, or the local environment unless a real implementation requirement forces a change.

Do not modify a working environment configuration unnecessarily.

---

# 3. Phase Goal

Phase 07.3 introduces a real estate CRM engine with:

- Leads
- Lead deduplication
- Lead inquiries / source tracking
- Opportunities
- Lead ownership
- Opportunity ownership
- Manual / Round Robin / Unassigned assignment modes
- Customizable CRM Pipelines
- Customizable Pipeline Stages
- CRM Kanban Board
- Stage completion requirements
- Protected stage transitions
- Won / Lost outcomes
- Opportunity stage history / audit
- Arabic + English Filament UI

This phase must **not** implement:

- WhatsApp Business integration
- Facebook / Instagram messaging integration
- AI message classification
- detailed follow-up / activity engine from Phase 07.4
- formal offers / negotiation records from Phase 07.5
- reservations from Phase 07.6
- contract generation from Phase 08

However, the architecture created in 07.3 must allow those later modules to reuse the CRM intake and workflow logic.

---

# 4. Core Business Decisions

## 4.1 One Lead, Multiple Opportunities

Within the same company, the same customer should normally have one Lead record.

A Lead may have many independent Opportunities.

Example:

```text
Lead: Ahmad
├── Opportunity #1 — Apartment A-501 — Sale
├── Opportunity #2 — Apartment B-302 — Sale
└── Opportunity #3 — Villa C-10 — Rent
```

Do not create a new Lead every time the same customer asks about another property.

## 4.2 Lead Status

Keep Lead lifecycle intentionally simple:

```text
active
inactive
```

Do **not** use `converted`.

Reason: a Lead may win one Opportunity while still having other active Opportunities.

The commercial lifecycle belongs to the Opportunity, not to the Lead.

## 4.3 Hybrid Lead / Opportunity Intake

The intake model is hybrid.

### Generic inquiry

If the person contacts the company without asking about a specific Listing:

```text
Incoming inquiry
→ Find or create Lead
→ Keep inquiry under the Lead
→ No Opportunity is required yet
```

### Listing-specific inquiry

If the person clearly asks about a specific Listing:

```text
Incoming inquiry
→ Find or create Lead
→ Create Opportunity
→ Initial stage = New
```

The Opportunity is **not automatically qualified**.

Qualification remains a human/business decision.

Later AI integration may classify incoming WhatsApp/social messages and suggest whether the message is a generic Lead inquiry or a Listing-specific Opportunity, but this AI behavior is not part of 07.3.

---

# 5. Lead Deduplication

The CRM must avoid creating duplicate customers while avoiding unsafe automatic merges.

## Matching priority

Within the same company:

1. normalized phone number
2. normalized email address
3. never merge using name only

Example:

```text
Phone entered: 0591234567
Normalized: +970591234567
```

If a matching Lead exists, the UI should warn the employee and offer actions such as:

- Open Existing Lead
- Add New Inquiry
- Create New Opportunity

The normal flow should not silently create a duplicate.

If phone and email point to different Leads, do not auto-merge. Flag the conflict for review.

Tenant isolation is mandatory:

```text
Company A Lead Ahmad != Company B Lead Ahmad
```

A Lead record must never be deduplicated across companies.

---

# 6. Lead Inquiries and Source Tracking

Each inquiry/contact event that creates or enriches CRM data must preserve its own source.

Examples:

```text
phone
whatsapp
facebook
instagram
website
walk_in
manual
other
```

The first source may be stored on the Lead for attribution, but each inquiry should retain its own source independently.

This is required because the same customer may contact the company from multiple channels over time.

A future Phase 07.7 integration must be able to call the same intake/application service without bypassing CRM rules.

Recommended application boundary:

```php
LeadIntakeService
```

or an equivalent domain/application service following existing project conventions.

External social/webhook integrations must not contain duplicated Lead/Opportunity creation logic later.

---

# 7. Lead Ownership vs Opportunity Ownership

These are separate responsibilities.

## Lead Owner

Responsible for the overall customer relationship.

Example:

```text
Lead: Ahmad
Owner: Mohammad
```

## Opportunity Owner

Responsible for one specific commercial opportunity.

Example:

```text
Lead: Ahmad
Owner: Mohammad

Opportunity #101 — Apartment A-501
Owner: Mohammad

Opportunity #102 — Villa B-10
Owner: Khaled
```

When a new Opportunity is created, default its owner to the Lead owner when appropriate.

Authorized users may reassign the Opportunity independently.

Cross-company assignment must be rejected.

---

# 8. Assignment Modes

Each company can choose its default CRM assignment mode.

Supported modes:

```text
manual
round_robin
unassigned_queue
```

Default:

```text
manual
```

## Manual

An authorized user selects the responsible employee.

## Round Robin

The system distributes new eligible assignments across eligible sales employees.

The implementation must be deterministic and safe under concurrent requests.

Do not assign users from another company.

## Unassigned Queue

The Lead / Opportunity may enter an unassigned queue until an authorized employee or manager claims or assigns it.

Use existing company settings infrastructure when suitable instead of creating a duplicate settings system.

---

# 9. Opportunities

An Opportunity represents a specific commercial possibility.

Recommended core fields:

```text
id
company_id
lead_id
listing_id nullable
pipeline_id
pipeline_stage_id

type: sale | rent

assigned_to nullable

budget_min nullable
budget_max nullable
currency nullable

preferred_property_type nullable
preferred_location nullable
preferred_bedrooms nullable

expected_close_date nullable
notes nullable

source
source_reference nullable

closed_at nullable
created_by nullable
updated_by nullable
timestamps
```

Adapt naming to existing project conventions.

`listing_id` must remain nullable.

A customer may have a real opportunity before choosing a specific Listing.

Example:

```text
Budget: 90,000 – 110,000
Location: Ramallah
Bedrooms: 3
Property type: Apartment
Listing: null
```

Do not force an Opportunity to a Listing.

---

# 10. Win Probability

Do **not** store manual `win_probability` in the database in 07.3.

It should be a calculated/domain-derived value later.

Possible future inputs:

- current stage
- budget match
- completed viewing
- follow-up activity
- offer activity
- response speed
- AI scoring

If a simple calculated accessor is introduced now, it must be derived and not treated as persisted truth.

---

# 11. CRM Pipelines

A Pipeline is the complete Kanban workflow.

A company may have multiple Pipelines.

Example:

```text
Sales Pipeline
Rental Pipeline
```

Recommended table:

```text
crm_pipelines
- id
- company_id
- name
- is_default
- is_active
- timestamps
```

A company should receive a default Pipeline during CRM provisioning.

---

# 12. CRM Pipeline Stages

Stages are the Kanban columns inside a Pipeline.

Recommended table:

```text
crm_pipeline_stages
- id
- company_id
- pipeline_id
- code
- label
- stage_type
- position
- is_active
- timestamps
```

`stage_type` is internal and must not depend on the visible label.

Supported semantic types:

```text
open
won
lost
```

Example:

```text
label = "Deal Closed"
stage_type = "won"
```

The company may rename the visible label without breaking backend logic.

Business logic must never depend on comparing labels such as `"Won"` or `"Lost"`.

`code` / semantic identifiers used internally should be protected from casual user editing.

---

# 13. Default Pipeline

Provision a useful default Pipeline for every company.

When a company creates a Pipeline and marks it as the default, it must receive
the approved default Stages and Requirements in the same protected workflow if
it has no Stages yet. This makes the Kanban usable immediately. Provisioning
must be idempotent and must never append defaults to a Pipeline that already
has company-configured Stages.

Default visible stages:

```text
New
Contacted
Qualified
Viewing
Negotiation
Won
Lost
```

These are defaults, not hard-coded permanent labels.

A company may:

- rename stage labels
- reorder stages
- add new stages
- disable stages
- configure stage requirements

Do not hard-delete a Stage that is already referenced by CRM history or active Opportunities.

Prefer deactivation:

```text
is_active = false
```

If deletion is allowed for never-used stages, enforce safe checks.

---

# 14. Kanban Board

Provide a real Filament CRM Kanban experience.

The Board represents Opportunities.

Each column represents a Pipeline Stage.

Each card should display useful concise information such as:

- Lead name
- Listing / target if available
- sale/rent type
- budget or asking context where useful
- Opportunity owner
- source

Do not overload the card with Phase 07.4 activity details.

The board must support stage movement by drag/drop or an equivalent clear action.

However:

> **The UI movement is only a request. The backend workflow service is the authority.**

Never rely on frontend drag restrictions alone.

The board must work correctly in:

- English
- Arabic
- RTL Arabic layout

---

# 15. Stage Completion Requirements

This is a key requirement of 07.3.

Employees must not be allowed to move an Opportunity out of a Stage until the mandatory requirements of the current Stage are satisfied.

Requirements must be customizable per company.

Do not hard-code all stage completion logic directly inside the Kanban component.

Recommended table:

```text
crm_stage_requirements
- id
- company_id
- stage_id
- key
- label
- requirement_type
- config_json nullable
- is_required
- position
- is_active
- timestamps
```

Use a real table for requirement records.

Use JSON only for small type-specific configuration.

Do **not** store the entire requirements collection as one JSON blob on the Stage.

---

# 16. Requirement Types

Support a safe, finite evaluator system.

Examples:

```text
field_required
field_equals
related_record_exists
checklist_completed
```

Example:

```text
label: Customer budget confirmed
requirement_type: field_required
config_json:
{
  "field": "budget_max"
}
```

Another example:

```text
label: Viewing must be completed
requirement_type: related_record_exists
config_json:
{
  "relation": "viewings",
  "field": "status",
  "operator": "=",
  "value": "completed"
}
```

Important:

- A company chooses from supported requirement types.
- A company must never enter arbitrary SQL.
- A company must never enter arbitrary PHP.
- Requirement evaluation belongs to trusted backend evaluators.

The staff configuration UI must not expose `key`, `config_json`, relation
names, operators, or manual numeric positions. It must show a friendly,
type-specific business form instead. The service generates a stable unique
internal slug key and derives display position from submitted order. It must
whitelist and normalize the selected field/related-record option before saving
the evaluator configuration.

When movement is blocked, show the current and requested Stage, each missing
staff-facing requirement, and the practical next step. Example: a checklist
directs the employee to Opportunity → Requirement completions; a contact-result
condition directs them to Lead → Inquiries → Contact result. Provide a safe
link to the Opportunity from Kanban notification feedback where possible.

The Opportunity View page must also present the current Stage's active required
conditions before the employee attempts a move. Each condition shows complete
or pending status and a localized next step; the backend workflow remains the
authoritative validator.

It must additionally provide an all-stage workflow-requirements overview. Each
row identifies the Stage, staff-facing condition, state, and next step, so a
condition configured on one Stage never visually replaces conditions configured
on another Stage.

Moving from an open Stage to Won or Lost must open a guided Kanban confirmation
modal. It clearly names the source and outcome Stage and collects the standard
closure data there: closing notes for Won; loss reason and optional notes for
Lost. Custom conditions that cannot be collected in that modal still return a
translated, direct next-step action.

Use existing patterns if the project already has a rule/evaluator architecture.

---

# 17. Requirement Completion Records

If a requirement is an explicit checklist item completed by an employee, persist completion separately.

Recommended concept:

```text
opportunity_requirement_completions
- id
- company_id
- opportunity_id
- stage_requirement_id
- completed_by
- completed_at
- notes nullable
```

Do not use a manual completion checkbox to fake conditions that can be validated from actual data.

Example:

- `budget_max required` → validate the Opportunity field.
- explicit checklist confirmation → use completion record.

---

# 18. Default Stage Requirements

Every newly provisioned company receives default Stage Requirements as database records.

Example baseline:

## New

Require sufficient customer contact information.

## Contacted

Require a recorded contact result / contact outcome.

## Qualified

Require key qualification data such as customer needs and budget information.

## Viewing

If the company uses this requirement for the current workflow, require the relevant viewing completion evidence.

## Negotiation

Require the minimum negotiation outcome/context that the CRM supports in this phase.

Do not implement formal Offer records here; formal offer/negotiation entities belong to Phase 07.5.

## Lost

Require a Lost reason.

## Won

Require closure information needed by CRM.

Do not automatically create a Reservation or Contract.

Companies must be able to customize, disable, reorder or add supported requirements.

Not every company must use the same mandatory requirements forever.

---

# 19. Stage Transition Protection

All Opportunity stage changes must go through domain/application service logic.

Example:

```php
$opportunityWorkflowService->moveToStage(
    $opportunity,
    $targetStage,
    $context
);
```

Before changing the stage, validate:

1. authenticated user permission
2. same company / tenant
3. Opportunity belongs to the expected Pipeline
4. target Stage belongs to the same Pipeline
5. target Stage is active
6. current Stage mandatory requirements are complete
7. for a terminal target (Won/Lost), terminal closure requirements are
   satisfied after the supplied closure context is applied
8. terminal state rules
9. required Lost/Won closure data
10. cross-company relationships are rejected

Use a database transaction where multiple records are changed.

Backend validation is mandatory even if the Filament UI disables the move.

---

# 20. Open vs Terminal Stages

Open stages may be flexible.

Example:

```text
New → Qualified
Qualified → Negotiation
```

This phase does not need an unnecessarily rigid adjacent-only workflow.

However, completion requirements must still be enforced before leaving the current stage.

An open Stage's checklist belongs to work performed **while the Opportunity is
in that Stage**. The Opportunity must be allowed to enter that open Stage; an
employee then records its checklist completion from the Opportunity View page,
and the service blocks leaving the Stage until the completion exists. Do not
validate ordinary open-target requirements before entry, or staff would be
unable to enter the Stage in order to complete its work.

Moving from an open Stage to an earlier open Stage is a valid rework/correction
operation. It may bypass incomplete requirements of the Stage being left, must
remain subject to normal company and permission checks, and must write a Stage
History event with `event: moved_back`. A terminal Opportunity remains closed
and cannot be reopened through this exception.

Terminal semantics:

```text
won
lost
```

By default, a terminal Opportunity must not be moved back into an open Stage using ordinary Kanban drag/drop.

Examples:

```text
Won → New ❌
Lost → Negotiation ❌
```

If reopening is ever introduced, it must be a separate explicit domain action with permissions and audit; ordinary movement must not silently reopen a closed Opportunity.

---

# 21. Won / Lost Behavior

## Lost

A Lost Opportunity must capture a reason suitable for analytics.

Prefer structured reason + optional notes.

Examples:

```text
price_too_high
customer_changed_mind
property_not_suitable
competitor
financing_issue
no_response
other
```

Allow company-friendly labels / localization.

Store analytics-friendly internal values where appropriate.

## Won

Won closes the CRM Opportunity.

It does **not**:

- reserve the unit
- change Listing availability
- create a contract
- create legal sale/rent records

Those belong to later phases.

CRM success must remain separate from Reservation and Contract workflows.

---

# 22. Opportunity Stage History

Every successful Stage change should be auditable.

Recommended concept:

```text
opportunity_stage_histories
- id
- company_id
- opportunity_id
- from_stage_id nullable
- to_stage_id
- changed_by
- changed_at
- metadata nullable
```

This history is useful for:

- audit
- analytics
- time-in-stage calculations later
- AI scoring later

Do not mutate history records after creation.

---

# 23. Pipeline Customization Rules

A company may:

- create Pipeline(s)
- select a default Pipeline
- rename Pipeline
- activate/deactivate Pipeline
- add Stages
- rename Stage labels
- reorder Stages
- activate/deactivate Stages
- add Stage Requirements
- reorder Requirements
- activate/deactivate Requirements
- configure supported Requirement types

The visible labels are customizable.

Internal semantic types and workflow integrity must remain protected.

Do not allow a company to break existing CRM data by deleting referenced records.

---

# 24. Multi-Tenancy

Every relevant CRM record must respect company isolation.

Examples include:

- Leads
- Lead inquiries
- Opportunities
- Pipelines
- Pipeline Stages
- Stage Requirements
- Requirement Completions
- Stage History

Cross-company relationships must be rejected at both application/business logic level and through safe query scoping / foreign key design where possible.

Never trust IDs from a form without validating company ownership.

---

# 25. Permissions

Reuse the project's existing Spatie / Shield conventions.

Define clear permissions for actions such as:

- view leads
- create leads
- update leads
- assign leads
- view opportunities
- create opportunities
- update opportunities
- assign opportunities
- move opportunity stage
- manage pipelines
- manage stages
- manage stage requirements
- close opportunity as won
- close opportunity as lost

Do not invent a parallel authorization system.

Super Admin behavior should follow existing project conventions.

---

# 26. Localization — Arabic + English

Everything introduced in 07.3 is bilingual from day one.

Required:

- English Filament labels
- Arabic Filament labels
- Arabic navigation
- Arabic forms
- Arabic tables
- Arabic actions
- Arabic filters
- Arabic validation messages
- Arabic Pipeline Settings
- Arabic Stage Settings
- Arabic Requirements UI
- Arabic Leads UI
- Arabic Opportunities UI
- Arabic Kanban UI
- Arabic Won/Lost flows
- proper RTL rendering

Do not hard-code user-facing English text in:

- Filament Resources
- Pages
- Widgets
- Services
- Notifications/errors intended for end users

Use Laravel translation keys/files.

Arabic support is part of the Definition of Done, not a future task.

Do not translate unrelated old modules unless a shared component touched by this phase requires it.

---

# 27. Filament Screens

At minimum, provide appropriate UI for:

## Leads

- list/search/filter
- create/edit
- duplicate warning
- owner assignment
- inquiries
- related Opportunities

## Opportunities

- list/search/filter
- create/edit
- owner assignment
- Pipeline/Stage
- source
- Listing relation
- stage history

## CRM Kanban

- select Pipeline when company has more than one
- Stage columns
- Opportunity cards
- protected movement
- meaningful validation feedback when movement is rejected
- RTL support

## CRM Settings

- Pipelines
- Stages
- ordering
- Stage labels
- Stage active/inactive
- Stage Requirements
- requirement ordering
- requirement active/inactive
- requirement type configuration
- default assignment mode

Follow the existing project's Filament structure instead of forcing a new folder architecture without reason.

---

# 28. Database / Schema Guidance

The final schema should follow existing naming conventions.

Expected concepts:

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

Do not create every table blindly if the repository already has a correct equivalent.

The agent must inspect before migration design.

All migrations must work on:

- MySQL
- PostgreSQL

Avoid engine-specific SQL where possible.

---

# 29. Service Boundaries

Prefer focused domain/application services.

Likely responsibilities:

```text
LeadIntakeService
LeadDeduplicationService
CrmAssignmentService
OpportunityWorkflowService
StageRequirementEvaluator / evaluator registry
CrmPipelineProvisioningService
```

Names may change to match project conventions.

Avoid giant God Services.

Avoid putting workflow logic directly in Filament page actions.

Filament should call the domain/application layer.

---

# 30. Future Integration Readiness

Phase 07.3 must make Phase 07.7 and 07.8 straightforward.

Future example:

```text
WhatsApp Webhook
→ normalize incoming message
→ identify company
→ call LeadIntakeService
→ deduplicate Lead
→ create Inquiry
→ optionally create Opportunity
```

Future AI example:

```text
Incoming message
→ AI intent classification
→ confidence score
→ Lead vs Opportunity suggestion
→ human review when uncertain
→ existing CRM intake service
```

Do not implement these integrations now.

Do not design the CRM in a way that requires bypassing 07.3 services later.

---

# 31. Testing Requirements

Add feature tests covering at least:

1. Lead creation
2. same-company duplicate phone detection
3. same customer allowed independently in another company
4. email duplicate detection
5. conflict case does not auto-merge
6. Lead with multiple Opportunities
7. generic inquiry without Opportunity
8. Listing-specific inquiry creates/permits New Opportunity
9. Lead owner and Opportunity owner can differ
10. cross-company assignment rejected
11. Manual assignment
12. Round Robin behavior
13. Unassigned queue behavior
14. default Pipeline provisioning
15. default Stages provisioning
16. default Requirements provisioning
17. Stage rename does not break semantic logic
18. Stage reorder
19. disabled Stage protected
20. Stage move succeeds when requirements satisfied
21. Stage move fails when requirements missing
22. backend blocks invalid cross-company Stage
23. Lost requires reason
24. Won does not create Reservation / Contract
25. terminal stage normal drag-back is blocked
26. Stage History is written
27. requirement completion audit
28. Arabic and English translation keys used for new UI where testable
29. MySQL migration/tests
30. PostgreSQL migration/tests
31. Pipeline name duplicate is rejected as a translated `name` validation
    error before the database unique constraint is reached
32. Stage code and position duplicates are rejected as translated field
    validation errors scoped to their Pipeline
33. A manually created first Pipeline is promoted as the company default by
    default provisioning; it must never cause a second default-named Pipeline
    or a raw SQL unique-constraint error during Lead creation
34. Pipeline Stages and Lead child workflows render and are actionable from
    their parent **View** pages; immutable Opportunity history renders there
    read-only
35. Browser-shaped Livewire tests assert the real Resource inputs, View-page
    relation tabs, translated English/Arabic labels, and Kanban movement UI in
    addition to service-level workflow tests

Also rerun relevant Phase 07.2 / unit setup regression tests.

---

# 32. Definition of Done

Phase 07.3 is complete only when:

- Leads work
- deduplication works
- inquiry source tracking works
- multiple Opportunities per Lead work
- ownership and assignment work
- customizable Pipelines work
- customizable Stages work
- customizable Stage Requirements work
- default Pipeline + defaults are provisioned
- Kanban works
- Stage movement is backend-protected
- required stage completion rules work
- Won/Lost behavior works
- Stage History works
- company isolation works
- permissions work
- Arabic UI works
- English UI works
- RTL works
- MySQL tests pass
- PostgreSQL tests pass
- relevant regressions pass
- no WhatsApp / Meta / AI implementation is accidentally mixed into this phase

---

# 33. Non-Goals / Boundaries

Do not implement Phase 07.4 Activities in full.

Do not implement Phase 07.5 Offers.

Do not implement Phase 07.6 Reservations.

Do not implement Phase 07.7 Meta/WhatsApp integrations.

Do not implement Phase 07.8 AI scoring/automation.

Do not change Listing lifecycle semantics from Phase 07.2.

Do not make Kanban movement reserve, sell, rent, archive, pause or otherwise change the physical/commercial state of a Listing or Unit.

The CRM tracks commercial progress only.

---

# 34. Final Architectural Principle

The intended result is:

```text
Default CRM that works immediately
+
Per-company customization
+
Protected backend workflow
+
Bilingual Filament UI
+
Extensible future integrations
```

Madar should be simple for a small company on day one, while remaining flexible enough for larger real estate companies without rebuilding the CRM core.
