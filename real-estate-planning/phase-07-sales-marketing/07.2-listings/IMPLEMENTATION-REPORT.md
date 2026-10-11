# IMPLEMENTATION REPORT — Phase 07.2: Listings Management

## Status

```text
IMPLEMENTED — awaiting manual Filament review and release permission generation.
```

## Scope Delivered

- Added company-owned `Listing` records for exactly one Planned Unit or Actual Unit.
- Added immutable `ListingPriceHistory` records with an initial pricing snapshot and transactional subsequent changes.
- Added Sale/Rent validation, Listing lifecycle actions, controlled marketability checks, tenant isolation, Filament management, and focused dual-database tests.
- Did not add Leads, Offers, Reservations, Contracts, social publication, or other Phase 07 sub-phases.

## Schema

Migration `2026_10_11_000000_create_listings_domain_tables.php`:

- creates `listings` with indexed company/status, company/type, and polymorphic target columns;
- creates `listing_price_histories` with a Listing/time index;
- adds `company_settings.allow_maintenance_unit_marketing`, defaulting to `false`.

The Listing target is immutable after creation, so a planned Listing can never be silently re-pointed to an Actual Unit. The polymorphic type uses the stable aliases `planned_unit` and `unit`. `AppServiceProvider` registers the aliases with Laravel's non-enforcing morph map, so historical polymorphic rows using FQCNs remain readable.

## Domain and Workflow

- `ListingType`: `sale`, `rent`.
- `ListingStatus`: `draft → published → paused → published`, `published|paused → archived`, and `archived → draft` only.
- A Listing status is guarded from normal mass assignment and exposed only through service actions: Publish, Pause, Archive, Restore.
- Sale Listings clear all rent-only values. Rent Listings require `rent_frequency`, using a new enum matching the existing Lease values: monthly, quarterly, semi-annually, yearly.
- Multiple Listings of the same commercial type are intentionally allowed for the same Unit.
- Publishing blocks inactive Actual Units. Maintenance Units require the company policy toggle. Draft and ready Actual Units are marketable. Planned Units must be approved or converted; cancelled/unapproved planned units are rejected.
- Planned Listings are never silently re-pointed when an Actual Unit is generated.
- Price/currency/rent-frequency changes create one history row inside the same transaction. Non-price edits do not create duplicate price history rows. Existing Offers, Reservations, Snapshots, and Contracts are untouched.

## Multi-tenancy, Authorization, and Notifications

- `Listing` uses the existing `HasCompany` global scope.
- `ListingService` resolves the Company context server-side and rejects cross-company Planned/Actual Unit IDs even when injected manually.
- Super Admin creation requires an explicit Company selector. Normal users derive Company from their current account.
- `ListingPolicy` is a top-level Shield policy (`view_listing`, `create_listing`, etc.) plus custom workflow permissions:
  - `publish_listing`
  - `pause_listing`
  - `archive_listing`
  - `change_listing_price`
- `restore_listing` remains Shield's standard top-level restore permission.
- The service dispatches a single actor notification after commit for creation, price change, and lifecycle transitions. No broad tenant notification is sent.

## Filament

- New top-level `ListingResource` with an explicit Planned/Actual selector, reactive type-based target selector, Sale/Rent fields, status/type/representation filters, and state badges.
- The normal form cannot edit status. Rent-only values clear reactively when switching to Sale; the service repeats the same rule server-side.
- View/Edit screens include a read-only `Price History` relation manager. Historical price rows have no create/update/delete UI.
- Company Settings exposes the maintenance-marketing policy with a clear explanation.

## Tests

`tests/Feature/ListingWorkflowTest.php` covers:

- Sale Listing creation for Planned and Actual Units;
- stored morph aliases;
- cross-company target injection rejection;
- Sale/Rent conditional validation;
- multiple Sale and Rent Listings for one Unit;
- inactive and maintenance marketability policy;
- allowed and disallowed lifecycle transitions;
- server-side permission denial and Super Admin company-context requirement;
- initial price history;
- price/currency history, no-op update behavior, and Listing-to-Listing isolation.

## Database Compatibility

| Engine | Test database | Clean migration | Focused Phase 07.2 tests |
| --- | --- | --- | --- |
| MySQL | `realState_test` | Passed | Passed — 8 tests, 47 assertions |
| PostgreSQL (port 6000) | `realstate_pg_test` | Passed | Passed — 8 tests, 47 assertions |

## Release Steps

After deploying the migration, generate the top-level Listing Shield permissions and seed the custom workflow permissions:

```bash
php artisan migrate --force
php artisan shield:generate --resource=ListingResource --option=permissions --panel=admin --no-interaction
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan optimize:clear
php artisan filament:cache-components
```

Then assign the generated Listing resource permissions and the seeded custom workflow permissions through the existing Roles UI.
