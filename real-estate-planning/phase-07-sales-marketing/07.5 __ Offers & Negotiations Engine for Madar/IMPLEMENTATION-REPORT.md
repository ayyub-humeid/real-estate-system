# Phase 07.5 — Offers & Negotiations Engine: Implementation Report

## Delivered

- Added a company-scoped Offer negotiation domain: `CrmOffer`, immutable
  `CrmOfferRevision` history, and `CrmOfferApproval` decisions.
- Offer references are generated automatically as `OFF-YYYY-####` through a
  locked, per-company, per-year sequence; users never enter a reference.
- Revision numbers are generated under a locked Offer row. A counteroffer is a
  new submitted Revision and supersedes the former submitted proposal.
- Added explicit server-side workflows for draft creation/editing, submission,
  counteroffers, approval requests, approval/rejection decisions, external
  agreement confirmation, withdrawal, rejection, and expiry.
- Approval requests are permission-routed rather than employee-routed: the
  requester cannot select a manager. A pending approval can be decided by any
  same-company user with the explicit approval permission.
- Kept internal approval separate from external agreement. Agreement records
  its actor/time and produces the one derived agreed Revision; approval alone
  leaves the Offer negotiating.
- Added sale and rental negotiated terms with validation. Rental proposals
  require frequency and duration; sale proposals cannot retain rental-only
  values.
- Added `accepted_offer_exists` to the finite CRM stage-requirement engine.
  It can gate an Opportunity stage transition but never moves the Opportunity
  or reserves the Listing itself.
- Added the localized Offers Filament resource, Opportunity → Offers relation,
  Revision and Approval relation managers, state-aware actions, detail views,
  and Arabic/English labels/messages.
- Added policies and custom permission seeding for revision, approval and
  negotiation workflow actions. The top-level resource uses the Shield naming
  convention for `CrmOffer`.

## Database

- `2026_10_15_000000_create_crm_offers_domain_tables.php`
  - `crm_offer_number_sequences`
  - `crm_offers`
  - `crm_offer_revisions`
  - `crm_offer_approvals`

The migration uses ordinary portable strings/decimals/JSON and short explicit
indexes, compatible with MySQL and PostgreSQL.

## Authorization and isolation

- Every service entry point authorizes the authenticated actor before changing
  domain state.
- Offers, Opportunities, Listings, and assignees are explicitly checked
  against the parent company, even when global scopes are bypassed.
- Negotiator assignment requires `assign_offer`; an assigned negotiator does
  not receive approval or agreement authority automatically.
- `view_own_crm_offer` and `manage_own_crm_offer` let a negotiator see and
  work only on their assigned Offers. The Resource query, Opportunity → Offers
  relation table, and record Policies all enforce this scope; sensitive
  approval and final-agreement actions remain separate permissions.

## Automated verification

- MySQL `realState_test`
  - `OfferNegotiationWorkflowTest`: **10 tests / 30 assertions passed**.
  - Phase 07.2–07.4 regression selection: **17 tests / 75 assertions passed**.
- PostgreSQL `realstate_pg_test`
  - `OfferNegotiationWorkflowTest`: **10 tests / 30 assertions passed**.
  - Phase 07.2–07.4 regression selection: **17 tests / 75 assertions passed**.

- Full regression suite: **121 tests / 591 assertions passed** on both MySQL
  and PostgreSQL.

The focused workflow tests cover automatic numbering, revision history,
submission immutability, counteroffers, approval-versus-agreement separation,
actor audit fields, cross-company rejection, rental validation and CRM-stage
requirement behavior.

## Deployment follow-up

Run Shield generation for the new top-level resource, seed custom permissions,
and refresh company role copies:

```powershell
php artisan shield:generate --resource=CrmOfferResource --option=permissions --panel=admin --no-interaction
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan app:backfill-company-roles
php artisan optimize:clear
php artisan filament:cache-components
```

## Intentionally deferred

No reservation, inventory blocking, contract, lease, payment schedule,
payment collection, e-signature, messaging integration, or AI negotiation
feature was added.
