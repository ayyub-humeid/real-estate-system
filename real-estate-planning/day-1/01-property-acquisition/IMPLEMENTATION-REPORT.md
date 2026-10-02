# IMPLEMENTATION REPORT — Phase 01: Property, Acquisition & Ownership

## Status

```text
READY TO CLOSE — Phase 01 targeted verification passes on MySQL and PostgreSQL.
```

## Scope Delivered

- Reused the existing `properties`, `HasCompany` global scope, Filament, Shield, database notifications, and generic polymorphic documents infrastructure.
- Added the Phase 01 domain tables/models: `parties`, `property_acquisitions`, `acquisition_properties`, `acquisition_parties`, `property_ownerships`, `due_diligence_cases`, and `due_diligence_items`.
- Kept contracts and payment redesign out of scope.

## Domain and Workflow

- Acquisition lifecycle is `draft → under_due_diligence → approved → completed`, with cancellation permitted from the approved pre-terminal states.
- Starting due diligence requires at least one Property and one Party. Approval requires every due-diligence case to be `cleared`.
- Required pending or failed items set the case to `blocked`; authorized waiver permits subsequent clearance.
- Ownership changes use the replacement transaction: validate an exact active total of 100%, close prior active rows, create the new rows, and retain complete history. Ownership records are not directly edited or deleted.
- `AcquisitionProperty.share_percentage` is per attached Property; it is not summed globally across an acquisition.
- `AcquisitionParty.share_percentage` is constrained per `role` group. A seller and buyer can each be 100%; create and edit validation excludes the current record before summing its role group.

## Authorization and Shield

`PropertyAcquisitionResource` is a top-level Shield resource and uses the generated `::` convention exclusively:

```text
view_any_property::acquisition
view_property::acquisition
create_property::acquisition
update_property::acquisition
delete_property::acquisition
...standard Shield restore/replicate/reorder permissions
```

Relation-manager and workflow operations use the existing custom underscore permissions, for example:

```text
create_acquisition_property
update_acquisition_party
clear_due_diligence_case
waive_due_diligence_item
change_property_ownership
approve_property_acquisition
cancel_property_acquisition
complete_property_acquisition
```

- Policies enforce all record/workflow permissions server-side.
- `completed` and `cancelled` acquisitions cannot be deleted even when the actor has the Shield delete permission. Draft deletion remains allowed when that permission exists; bulk acquisition deletion is disabled because it cannot safely enforce the per-record history rule.
- Acquisition History creation calls a service which checks `create_property::acquisition` server-side, uses a transaction, and derives the company from the parent Property.

## Multi-Tenancy and Super Admin

- New company-owned models reuse `HasCompany` and the existing `CompanyScope`.
- Association services reject cross-company Property/Party links.
- Child creation derives `company_id` from its parent Acquisition or Property, never from a Super Admin user.
- Super Admin child selectors query explicitly with `withoutGlobalScopes()` and the parent company ID. Due Diligence and ownership selectors therefore only display records that belong to the parent context; cross-company IDs are also rejected in the service layer.

## Filament

- Added Party and Property Acquisition resources.
- Property has Ownership History and Acquisition History relation managers.
- Acquisition has Properties, Parties, and Due Diligence relation managers.
- Workflow operations are reactive and do not force a browser reload.
- Relation-manager actions that delegate to the service retain server-side policy checks; validation failures use domain exceptions for Filament to render.

## Notifications

- Acquisition transition, due-diligence clearance, and ownership replacement notifications are registered with `DB::afterCommit`.
- Recipients are restricted to the actor and same-company users with the relevant acquisition/ownership view permission. Users in another company and unrelated same-company users do not receive the ownership notification.
- Direct ownership replacement now has an intentional recipient path even without an Acquisition reference.
- Filament success toasts are separate actor UX feedback; persistent domain notifications are dispatched once by the service after commit.

## Database Compatibility

The migration uses portable Laravel Schema Builder definitions. The MySQL unique-index name for `acquisition_properties` is explicitly short enough for MySQL while remaining PostgreSQL-compatible.

| Engine | Isolated database | Clean migration | Phase 01 targeted tests |
| --- | --- | --- | --- |
| MySQL | `realState_test` | Passed | Passed — 18 tests, 55 assertions |
| PostgreSQL (port 6000) | `realstate_pg_test` | Passed | Passed — 18 tests, 55 assertions |

## Test Coverage

- same-company multiple Properties/Parties and cross-company rejection
- invalid/unauthorized approval, completion, cancellation, waiver, and clearance
- due-diligence required-item blockers and clearance
- Property/Party share semantics including edit exclusion and role change
- ownership percentage validation/history preservation and company-as-owner Party reuse
- completed/cancelled deletion protection
- Acquisition History server-side authorization and parent-company inheritance
- Super Admin parent context and Due Diligence cross-company rejection
- notification recipients and rollback behavior

## Files Added or Updated

- Phase 01 migration, models, policies, workflow service, notification, resources/relation managers, test database guard/configuration, and Phase 01 feature tests.
- `AGENTS.md` / global guidance contains the reusable Shield naming, parent-company inheritance, relation-manager authorization, notification, and dual-database verification lessons discovered during Phase 01.

## Deferred Intentionally

- Contract redesign/integration beyond existing generic document compatibility.
- Payment redesign and all Phase 02+ work.
