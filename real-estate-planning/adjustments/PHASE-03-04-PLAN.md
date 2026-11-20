# Adjustment Plan — Phase 3 (Streamlined Sales & CRM Pipeline) and Phase 4 (Permissions, Localization, Quality)

_Status: PLAN — awaiting developer review. No code has been changed for these phases yet._
_Date: 2026-11-19_
_Source: `ADJUSTMENT_PLAN.md` §3 and §4, checked against the code in the repository today._

---

## 0. Main finding

Most of Phase 3 **already exists**. The services, locks, histories and permissions are already in place.
The problem is that the steps between them are **not joined up**. Users have to retype data and walk
through extra screens between Lead → Offer → Reservation → Contract. So Phase 3 is mostly **wiring and
pre-filling**, plus one real domain gap: there are no **Sold / Rented** unit statuses.

| ADJUSTMENT_PLAN item | Current state in repo | Classification |
|---|---|---|
| 3.1 Lead → Party conversion | `LeadPartyConversionService`, "Convert to Party" on `ViewLead`, `convert_lead_to_party` permission, `parties.lead_id` | REUSE + ALTER (dedupe, auto-ensure) |
| 3.1 Dedup service with fuzzy matching | `LeadIntakeService` already normalises phone/email and blocks duplicate leads | REUSE — **no fuzzy matching** (see §5) |
| 3.2 Accept offer | `accept_offer` action on `ViewCrmOffer` → `OfferNegotiationService::confirmAgreement` | REUSE + ALTER (fewer steps) |
| 3.2 Offer → Reservation | Button redirects to Reservation create form via query string | RESTRUCTURE (create draft directly) |
| 3.3 Unit → reserved + double-booking guard | `approveAndActivate` locks unit row, `ReservationAvailabilityService`, `UnitStatusHistory`, release on cancel/expire | REUSE (verified correct) |
| 3.4 Reservation → Contract | Contract form has a "Source reservation" select that fills unit/amount/currency | ALTER (button + parties + type pre-fill) |
| 3.4 Unit → sold / rented on contract activation | **Missing.** `UnitStatus` has no `sold`/`rented`; `ContractWorkflowService::activate` never touches the unit | CREATE |
| 3.4 Occupancy recalculation | `OccupancyStats` computes live from `currentLease` | ALTER (count new statuses) |

---

## 1. Re-entry points found (what the user retypes today)

1. **Opportunity has no unit/project.** `opportunities` has only `listing_id`, and Listings are switched off by
   the `features.advertisements` flag. So an Offer never knows its unit, and when the user clicks "Convert to
   reservation" `unit_id` is always empty → the user picks the unit **again**.
   (ADJUSTMENT_PLAN §0.2 said "Link Opportunities directly to Projects and Units". That part was never done.)
2. **Offer has no price when created.** Creating an Offer saves only type and assignee. Then the user must open
   the Revisions tab → add draft revision (type amount/currency/terms) → submit → (request approval → approve) →
   confirm agreement / accept. That is **5–7 clicks across 2 screens** for a normal "client agreed on the list price" case.
3. **Customer Party must exist before reservation.** If the lead was not converted, the user must leave the
   offer, open the lead, convert, come back. The "auto-trigger on first reservation" that exists today
   (`ReservationWorkflowService::autoConvertLeadToParty`) only links an **already existing** Party to a lead by
   phone after activation, so it does not remove this step.
4. **Lead → Party conversion can create a duplicate Party.** It never checks whether a Party with the same
   phone/email already exists (for example, a client who bought before).
5. **Reservation → Contract has no button.** The user goes to Contracts → Create → picks the reservation →
   then types **contract parties again** (customer + company) and picks the contract type by hand,
   even though the reservation already knows the customer and sale/rent.
6. **Reservation lifecycle needs two approvers' clicks** (send for approval → approve & activate) even when the
   person clicking has approve permission.

---

## 2. Phase 3 — Implementation steps

### 3.A Link Opportunity → Unit / Project (removes re-entry #1) — ALTER
- Migration: add nullable `project_id`, `unit_id` (FK, indexed) to `opportunities`. The same migration must work on MySQL and PostgreSQL.
- Opportunity form (resource + `LeadResource\OpportunitiesRelationManager`): "Project" select → dependent "Unit"
  select. Only `ready` units of the same company are listed. Each option shows the unit's sale/rent price.
- `OfferNegotiationService::createOffer`: inherit `unit_id` from the opportunity (new nullable `crm_offers.unit_id`).
  If a Listing is present, the Listing still decides the unit (current rule kept).
- `ReservationWorkflowService::acceptedOfferAttributes`: when there is no Listing, use `offer.unit_id` by default.
  The form may still override it if the unit was changed during negotiation.

### 3.B Quick offer: price on create (removes re-entry #2) — ALTER
- The "Create offer" form (Opportunity → Offers relation manager and `CreateCrmOffer`) gets the first revision's
  fields: amount, currency, down payment, payment method, terms. **Pre-filled** from the unit's sale/rent price
  (Phase 1.4 fields) and the opportunity currency.
- Toggle "Submit to customer now" (default on). One service call creates the offer, revision #1, and submits it.
  This uses the existing `createDraftRevision` + `submitRevision` in one transaction.
- The revision/counter-offer model is **kept unchanged**. Negotiation history stays. Only the first step is shorter.
- "Accept offer" already exists (it confirms the latest submitted revision). Keep it, and show in the modal the
  **amount being accepted** so the user knows exactly what they confirm.

### 3.B-2 Streamline the offer screen for admins (decision D3: approvals stay optional) — ALTER (UI only)
Seen from a sales admin's seat, the offer screen today shows engineering concepts ("revision", "submit",
"confirm agreement") spread over the header **and** the revisions table. It also has two buttons that do the
same thing (header "Accept offer" and row "Confirm agreement"). The domain rules stay the same. Only what the admin sees changes:
- **One set of header actions, shown only when they apply, in business words:**

  | Today (technical) | New label (business) | Shown when |
  |---|---|---|
  | submit revision | **Send this price to the customer** | latest price is a draft |
  | counteroffer | **Record customer's counter-offer** | price was sent to customer |
  | request approval | **Ask a manager to approve this price** (optional) | price is a draft or sent, and has no approval yet |
  | approve / reject approval | **Approve price / Refuse price** | an approval is waiting on me |
  | confirm agreement + accept offer | **Customer accepted this price** (one button) | price sent, all requested approvals approved |
  | reject / withdraw | **Customer declined / Withdraw our offer** | offer open |
- Remove the workflow buttons from the revisions table. Rename the tab to **"Price history"**, shown as a
  read-only timeline: who proposed (us / customer), amount, change against the previous price, status.
- Each modal says in one sentence **why** you would use it. Example: "Ask a manager to approve this price — use this
  when you give a discount or special payment terms. The customer cannot accept until the manager approves."
- A "pending my approval" filter/badge on the Offers list, so a manager does not have to hunt for offers.

### 3.C Offer → Reservation in one action (removes re-entry #3) — RESTRUCTURE
Replace the redirect-with-query-string with a modal action on `ViewCrmOffer`:
- The modal shows read-only: customer, unit, agreed amount, currency, payment terms. The only inputs are **Unit**
  (pre-filled, editable only when there is no Listing) and **Valid until** (default: today + company default
  days, or 7 days).
- Inside one transaction:
  1. **Ensure the customer Party**: use `lead.party`. If missing, find an existing Party by normalised
     phone/email and link it (`lead_id`). Otherwise create one through `LeadPartyConversionService`. The user
     needs `convert_lead_to_party` for the create path, otherwise they get a clear error.
  2. `ReservationWorkflowService::createDraft` (source = accepted offer).
  3. If the user also has `approveAndActivate` permission and ticked "Activate now", run `sendForApproval` +
     `approveAndActivate`. Both history rows are still written.
- Redirect to the new reservation's view page with a success toast.
- Remove `CreateUnitReservation::mount()` query-string pre-fill (it trusts URL params and is no longer needed).
- Remove the now-redundant `autoConvertLeadToParty` best-effort hook (it is replaced by step 1, which is explicit and transactional).
- Add a `convertToReservation` method to `CrmOfferPolicy`. Today `can('convert_offer_to_reservation', $offer)` has no
  policy method. It only works through the Spatie gate fallback, and that fallback **skips the company check**.

### 3.D Lead → Party: dedupe and link existing (fixes #4) — ALTER
- `LeadPartyConversionService::convert`: before creating, look for an existing Party in the same company with the
  same normalised phone or email:
  - one match with no lead → link it (`lead_id`) and return it (toast: "Linked to existing client X")
  - match already linked to another lead → validation error that names the other lead
  - no match → create (current behaviour)
- Add `normalized_phone` / `normalized_email` to `parties` (indexed) and backfill them. Reuse the
  `LeadIntakeService` normaliser (move it into a small shared `ContactNormalizer`). **No fuzzy matching.**
- `ViewLead`: show the linked Party (name + link) in the infolist. Stay on the lead after conversion and do not
  jump to Party edit, so the user keeps working on the deal. Replace the hardcoded English strings.

### 3.E Unit statuses Sold / Rented + reservation fulfilment — CREATE / ALTER
- `UnitStatus`: add `Sold = 'sold'`, `Rented = 'rented'`. Transitions: `Reserved → Sold|Rented`, and `Ready → Sold|Rented`
  (direct contract without reservation). `Rented → Ready` happens on lease end/termination. `Sold` is final
  (resale is out of scope). Add labels, colors and translations.
- `ReservationStatus`: add `Converted = 'converted'`. Without it, an active reservation whose contract was
  activated would later be **expired by the scheduler** and show as "expired" in history.
- `ContractWorkflowService::activate` (inside the existing transaction, unit row already locked by `assertAvailable`):
  - unit → `sold` (sale contract) or `rented` (rent contract) through one shared `UnitStatusService::change()`.
    That service is extracted from `ReservationWorkflowService::setUnitStatus` so status history is written in one place.
  - source reservation (if any, and `active`) → `converted` + a reservation history row.
- `OccupancyStats` / dashboard: `rented` counts as occupied, `sold` is excluded from vacant, and `reserved`
  is shown separately. A new "Sales inventory" stat shows Available / Reserved / Sold / Rented.

### 3.E-2 Contracts are the only rental record (decision D2) — ALTER
The `Lease` module is **deliberately disabled, not removed**. `Contract` (types `sale`, `off_plan_sale`, `lease`) is the single
record for selling and renting. The code supports this: lease-type contracts already validate rent terms, end date
and overlapping leases per unit. The work left is to stop reading the disabled Lease table:
- `OccupancyStats`, `FinancialReport` occupancy, `OperationalOverviewWidget` "active leases": read from unit status
  (`rented`) and active `lease` contracts, not `currentLease`. **These numbers are wrong today** because nothing writes Leases any more.
- `FinancialReport` revenue: read contract payments (`ContractPaymentAllocation`), not `lease.unit` payments.
- **Do not touch** the `Lease` model, table, API controllers, `CheckLeaseExpirations` or the policies. They stay
  dormant so nothing that references them breaks. `UnitPolicy::delete`'s `leases()->exists()` check stays (harmless).
- Note: the tenant mobile API (`Api/Tenant*Controller`) still serves Leases only. If a tenant app is used, it will
  not see contract-based rentals. This is out of scope unless you say otherwise.

### 3.E-3 End of a rental contract → unit available again — CREATE (small)
`ContractStatus` stops at `active`, so a rented unit would stay `rented` forever.
- Add `ContractStatus::Ended` ("Ended") with an "End contract" action. It takes a reason (term finished / early
  termination) and an end date, and is allowed for `lease` contracts in `active` state. It needs the new permission `end_contract`.
- On end: unit `rented → ready` with a history row, plus a contract status history row.
- A daily scheduled command lists lease contracts past their `end_date` and **notifies** the owner/manager. It does
  **not** auto-end them, because a tenant may be renewing. The person ends the contract with the action above.
- Sale contracts never end, and the unit stays `sold`.

### 3.F Reservation → Contract button (removes re-entry #5) — ALTER
- `ViewUnitReservation`: "Create contract" header action, visible when the reservation is `active`, has no
  existing contract, and the user can `create` Contract + `create_contract_from_reservation`.
- Modal: Template (existing template selector, filtered by contract type), start date. Everything else is derived:
  - contract type from `transaction_type` (sale → sale, rent → lease type)
  - parties: customer Party (buyer/tenant role) + company legal party as seller/landlord
    (`CompanyLegalPartyService` already exists)
  - unit / project / property / amount / currency (already done by `ContractDraftService` from reservation)
- Calls `ContractDraftService::create`, then redirects to the contract view.
- Contract create form: when a reservation is chosen, also fill the parties repeater and the type.
  This is the same rule, used when the user starts from the Contracts screen.

### 3.G In-app guidance (user asked for explanations) — CREATE (UI only, no schema)
1. **Sales journey bar**: one shared infolist component on Lead, Opportunity, Offer, Reservation and Contract
   view pages: `Lead → Client → Opportunity → Offer → Reservation → Contract`. Each step shows its status
   badge and a link. Done steps are green and the current step is highlighted.
2. **"Next step" callout** under the journey bar. It is a sentence + the button that moves forward. Examples:
   - Offer `negotiating`: "The customer's latest price is waiting for a decision. Accept it to lock the agreed terms, or send a counter-offer."
   - Reservation `pending`: "A manager must approve this request. On approval the unit becomes Reserved and nobody else can reserve it."
   - Reservation `active`: "The unit is held until {valid_until}. Create the contract before that date or the reservation expires and the unit is released."
3. **Status meaning**: every status enum used in sales (`OfferStatus`, `ReservationStatus`, `UnitStatus`,
   `ContractStatus`) gets `description()`, shown as a tooltip on badges and in a "What do these statuses
   mean?" collapsible section on list pages.
4. **Action consequences** in every workflow modal: what will change and what cannot be undone. Example
   (activate contract): "Unit A-12 will become **Sold**. The reservation RES-2026-000014 will be closed as Converted. This cannot be undone. Use an amendment to change terms later."
5. **"How sales work" guide page** (CRM navigation, read-only, everyone with CRM access). It explains the domain
   in admin language with a simple diagram:
   - Lead = a person who asked. Client (Party) = a person we do business with, created once and reused.
   - Opportunity = one deal for one unit. Offer = the price discussion. Price history = every price proposed by either side.
   - Reservation = the unit is held for this client until a date. Nobody else can reserve it.
   - Contract = the legal agreement for a sale or a rent. When it is activated, the unit becomes Sold or Rented.
   - Who approves what (permission names in plain words), and what cannot be undone and why (legal/financial history).
   - Each "Next step" callout links to the matching section of this page.
6. **Field helper texts** on the sales forms (valid-until, down payment, payment terms, contract template, unit) say
   what the value is used for later. Example: "Valid until — the unit is released automatically after this date if no contract is created."
- All text lives in `lang/{ar,en}/crm.php` and `reservations.php`. Nothing is hardcoded (per the `localized-labels-no-hardcoded-strings` skill).

### Phase 3 tests (Feature)
- Opportunity unit → offer → reservation carries the unit with no manual input.
- Quick offer creates offer + submitted revision. Accept → reservation → contract happy path with **zero re-typed fields**.
- Party ensure: existing party linked, not duplicated. Conflicting linked party is rejected with a message.
- Double booking: two reservations for one unit → the second activation fails (existing test kept). The "Activate now" shortcut respects the permission.
- Contract activation: unit `sold`/`rented`, reservation `converted`, scheduler no longer expires it, history rows written.
- Wrong company / missing permission for every new action (UI hidden **and** service rejects).
- Filament tests mirror the real forms (per the `filament-ui-mirroring-tests` skill). Run on MySQL **and** PostgreSQL.

---

## 3. Phase 4 — Implementation steps

### 4.1 Permissions (custom, Convention 2, `_` separator)
| Permission | Status | Action |
|---|---|---|
| `convert_lead_to_party` | exists, enforced | VERIFY |
| `accept_offer` | exists, enforced | VERIFY |
| `convert_offer_to_reservation` | exists, **no policy method** | ALTER (3.C) |
| `create_contract_from_reservation` | missing | CREATE + seed + policy (3.F) |
| `approve_actual_cost` | exists | VERIFY |
| `convert_acquisition_to_project` | missing in code | VERIFY Phase 1.2 status first, then CREATE |
| `approve_budget` | missing — check what budget approval uses today | VERIFY → CREATE if needed |
| `approve_commitment` | missing — check what commitment approval uses today | VERIFY → CREATE if needed |

All of them go in `RolesAndPermissionsSeeder`, the Shield custom-permissions UI, and the permission translation file.
Check them in the running app with one role that has the permission and one role without it.

### 4.2 Arabic localization
- About **1,338** `__('English sentence')` calls remain in `app/Filament` and `app/Services`. They are heaviest
  in the Contract*, Company*, Document*, FinancialReport and ContractTemplate classes. Some of them may already
  be covered by `lang/ar.json`.
- Add an artisan command `lang:find-missing` that lists keys used in code but missing in `ar` / `en`
  (PHP group files + JSON). Add a test that fails when a key is missing in either language.
- Fix order: sales path first (Lead, Opportunity, Offer, Reservation, Contract, Unit), then finance, then admin screens.
- Convert raw-sentence keys in touched files to grouped keys (`contract_domain.*`), not more JSON entries.

### 4.3 Feedback, confirmations, N+1
- Add **one** shared helper (trait `RunsWorkflowAction`) that wraps a service call and maps
  `ValidationException` / `AuthorizationException` / `Throwable` to persistent localized notifications. Today
  each page copies this try/catch by hand, with different (sometimes English) messages.
- Every state-changing action: `requiresConfirmation()` + a consequence description (ties into 3.G-4).
- N+1 pass on sales list pages (`getEloquentQuery()->with(...)`), checked with query-count assertions in tests.

### 4.4 Tests
Covered by the Phase 3 tests, plus: a permission matrix test for all 8 permissions, the translation key existence
test, unit status transition tests, and a dashboard inventory counts test.

---

## 4. Proposed order

1. 3.E statuses + `UnitStatusService` → 3.E-2 dashboards off Lease → 3.E-3 end contract (foundation) → tests
2. 3.A opportunity unit link → 3.B quick offer → 3.B-2 offer screen streamlining
3. 3.D party dedupe → 3.C offer→reservation one action
4. 3.F reservation→contract button + parties pre-fill
5. 3.G guidance UI
6. Phase 4.1 → 4.3 → 4.2 (4.2 is the largest; it can run in parallel with review)

Each step is its own commit and can be reviewed on its own.

---

## 5. Complexity / over-engineering notes for the developer

1. **Fuzzy duplicate matching (plan 3.1)**: skip it. Normalised exact match on phone/email already exists and is
   predictable. Fuzzy name matching produces false merges of real clients. If needed later, show a
   "possible duplicates" hint, never auto-merge.
2. **DB unique constraint for active reservation per unit (risk table)**: skip it. MySQL has no partial indexes
   (it would need a generated column) and the row lock + availability check already serialises activation on both DBs.
3. **"Recalculate occupancy rate"**: there is nothing to recalculate. The widget computes live. We only add the new statuses to it.
4. **Offer approval chain** (revision → submit → request approval → approve → confirm → accept) is heavy for a
   normal sale. Suggestion: require approval **only when the price is below the unit list price by more than a
   company-set discount %**. Otherwise "Accept" is enough. _(Needs your decision. Not included in the steps above.)_
5. **Two rental concepts**: resolved by D2. Contract is the only rental record and Lease stays dormant.
6. **Reservation snapshot + contract reservation_snapshot + unit_snapshot**: the same data is stored three
   times. This is acceptable for legal history, so keep it, but do not add more snapshots.
7. **Contract lifecycle** (draft → review → approved → signatures → active) is a legal process. It stays as it is. Only the entry into it gets faster.

---

## 6. Decisions (recorded 2026-11-19)

- **D1 — approved.** Add nullable `project_id`/`unit_id` to `opportunities` and `unit_id` to `crm_offers` (3.A).
- **D2 — Contracts only.** Leases are deliberately disabled and stay dormant. Rent = `lease`-type Contract →
  unit `rented`, and the end action → unit `ready`. The dashboards move off the Lease table (3.E-2, 3.E-3).
- **D3 — approvals stay optional.** The offer screen is streamlined and explained in business language (3.B-2, 3.G-5).
  Note 4 (discount threshold) is dropped.
- **D4 — approved.** Users who hold the approve permission get "Activate now" (both history rows are still written).

Extra permission introduced by these decisions: `end_contract` (Convention 2).
