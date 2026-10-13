# Phase 07.3 — Leads, Opportunities & CRM Kanban: Implementation Report

## Delivered

- Company-scoped Leads with normalized phone/email deduplication, safe conflict handling, lead ownership, and reusable intake service.
- Inquiry history with source/reference/message/contact-result capture. A listing inquiry creates an Opportunity; a generic inquiry does not.
- Company-scoped Opportunities with separate ownership, optional Listing, sale/rent intent, budget/profile fields, Pipeline, Stage, source, and immutable stage history.
- Default Sales Pipeline provisioning per company: New, Contacted, Qualified, Viewing, Negotiation, Won, Lost; labels are customizable while `stage_type` remains the protected workflow semantic.
- Company-customizable Pipelines, Stages, and typed Stage Requirements. Removing a requirement deactivates it rather than deleting referenced audit data.
- Pipeline, Lead, and Opportunity View pages now contain complete working relation tabs. Pipeline Stages and Lead Inquiries/Opportunities are manageable from View as well as Edit; Opportunity exposes checklist completion and immutable Stage History from View.
- Pipeline name, Stage code/position, requirement key, budget range, and cross-company child selections are validated in the form and service layer before database constraints. Parent aggregate locks retain concurrency safety.
- Existing manually-created Pipelines are promoted to the company default when CRM provisioning first runs, preventing duplicate default-name SQL errors during Lead creation.
- Protected transactional Kanban movement: same-company/pipeline/active-stage checks, server-side requirement evaluation, checklist completion records, terminal Won/Lost protection, and structured lost reasons.
- Manual, round-robin (locked settings record), and unassigned assignment modes using Company Settings.
- Lead, Opportunity, Pipeline Filament resources plus a custom HTML5 drag/drop Kanban page. The board has responsive horizontal Stage columns, Won/Lost visual treatment, card detail links, budget/owner/source context, and an accessible Move selector as a non-drag fallback. Super Admin must choose a company context on the board.
- English and Arabic CRM translations. The Kanban renders `dir="rtl"` for Arabic and `ltr` for English.
- Policies, Shield-compatible resource permission policies, seeded custom relation/workflow permissions, and Roles-page CRM grouping.

## Database

Migration: `2026_10_12_000000_create_crm_domain_tables.php`

New tables:

- `crm_pipelines`, `crm_pipeline_stages`, `crm_stage_requirements`
- `leads`, `lead_inquiries`, `opportunities`
- `opportunity_requirement_completions`, `opportunity_stage_histories`

`company_settings` now contains `crm_assignment_mode` and `crm_round_robin_last_user_id`.

All long composite index/unique names are explicitly named for MySQL's 64-character identifier limit.

## Automated verification

- MySQL `realState_test`: CRM tests passed — **11 tests, 56 assertions**.
- PostgreSQL `realstate_pg_test`: CRM tests passed — **11 tests, 56 assertions**.
- Phase 07.2 Listings regression:
  - MySQL: **8 tests, 47 assertions passed**.
  - PostgreSQL: **8 tests, 47 assertions passed**.
- Full regression suite:
  - MySQL `realState_test`: **100 tests, 499 assertions passed**.
  - PostgreSQL `realstate_pg_test`: **100 tests, 499 assertions passed**.
- Clean PostgreSQL migration passed.
- Clean MySQL CRM migration passed after correcting explicit index/constraint identifiers.

## Manual checks still required

Automated browser-shaped Livewire tests confirm actual Resource inputs, valid submits, all CRM View-page relation tabs, and Arabic Kanban RTL markup. Before release, manually verify in a browser:

1. Drag a card between columns and confirm a rejected movement stays in its original column with the translated notification.
2. Toggle the application locale between English and Arabic and visually check card/column alignment, keyboard focus, and tooltips.
3. Complete a checklist requirement from an Opportunity View page, then move the card from Kanban.
4. Run Shield permission generation for the three top-level resources, seed custom permissions, backfill company roles, then assign/review them through the Roles UI.

No Phase 07.4+ activity, offer, reservation, external messaging, or AI functionality was added.
