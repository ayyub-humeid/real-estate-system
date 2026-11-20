# Project Management Guide — From the First Idea to the Last Handover

> Arabic version: [`PROJECT_MANAGEMENT_GUIDE_AR.md`](PROJECT_MANAGEMENT_GUIDE_AR.md)
>
> This guide explains, in plain language and with one continuous real-life scenario, how a real-estate development project is run inside Madar: who does what, in which tab, in which order, and what the system checks before it lets you move on. There is no code in it. Button and tab names are written exactly as they appear on screen.

---

## 1. How to use this guide

- Read **Section 3** first for the big picture, then follow **Section 5** stage by stage. Every stage has the same layout: *Goal → Who → Where → Steps → Rules that stop you → What you get*.
- **Section 4** introduces the people and the project used in every example. The same project is followed from the first day to the last day.
- **Section 7** lists every status and its meaning. **Section 8** answers "why can't I do this?".
- Everything described here was checked against the way the system actually behaves. Where the system deliberately does **not** enforce something (for example, it does not stop you from completing a project with unfinished work), the guide says so explicitly.

---

## 2. Who uses the system

### 2.1 People who log in

| Function | What they do in a project | Typical permissions they need |
|---|---|---|
| **Company Admin** | Owns the company data. Creates and approves projects, assigns people, resolves blockers. | Holds every permission in the company. |
| **Project Manager** | Runs the project day to day: planning, team, design, construction, completion. | Update/approve/close project, manage members, design, construction. |
| **Financial Manager** | Plans the money: budgets, commitments, actual costs, payments. | Budget, commitment, actual-cost and payment permissions. |
| **Design Manager** | Manages engineering offices, scope, submissions, reviews. | Design package, review and approval permissions. |
| **Site Engineer** | Records construction progress, inspections, issues and delays. | Construction permissions. |
| **Legal / Contracts Officer** | Prepares, reviews and activates contracts. | Contract submit / approve / activate permissions. |
| **Sales Manager** | Turns finished units into listings, reservations and contracts. | Listing, reservation, contract permissions. |

> **Important:** *Project Manager*, *Design Manager* and the other titles above are **member roles** you assign in the project's **Project Members** tab (they are labels such as "Project manager", "Finance manager", "Site engineer"). What a person is **allowed to do** comes from the **system role and permissions** your company gives them. In the standard setup the system roles are **super_admin** (platform level), **company_admin** (everything inside the company), **property_manager**, **financial_manager**, and **tenant** (cannot enter the admin panel). Each company can create its own roles, so role names can differ from company to company — **permissions are what count**.

### 2.2 People who do **not** log in (they are "Parties")

- **Engineering office / designer** — recorded as a *Party* and assigned to a design package.
- **Contractor, supplier, vendor** — recorded as a *Party* and used on commitments, actual costs, payments and construction tasks.
- **Buyer / tenant / customer** — recorded as a *Party* on reservations and contracts.

### 2.3 Company isolation

Everything belongs to one company. A user only sees and changes records of **their own company**. Records from different companies can never be linked together (the system refuses with "Records from different companies cannot be associated"). Only a platform super admin works across companies and must pick the company when creating a project.

---

## 3. The project at a glance

### 3.1 The main status path of a project

```
 Planning ──approve──▶ Approved ──start──▶ In progress ──complete──▶ Completed ──close──▶ Closed
     │                     │                    │
     └────────cancel───────┴──────cancel────────┘            (Cancelled — needs a reason)
```

- **Planning** → **Approved**: the project has at least one active property attached.
- **Approved** → **In progress**: the manager decides the work begins.
- **In progress** → **Completed** → **Closed**: finishing and archiving.
- **Cancelled** is possible only from Planning, Approved or In progress, and always needs a written reason.
- **Closed** and **Cancelled** are final. Nothing in the project can be changed any more (planning, design, finance).

### 3.2 The tabs of a project

When you open a project (**Projects → View**) you see the header buttons (the status actions) and these tabs:

| # | Tab (English / Arabic) | Used for |
|---|---|---|
| 1 | **Project Properties** / عقارات المشروع | The land or buildings the project is built on |
| 2 | **Project Members** / أعضاء المشروع | The internal team |
| 3 | **Planning Structure** / الهيكل التخطيطي | Buildings → floors → planned units → specifications |
| 4 | **Actual Units** / الوحدات الفعلية | Real, sellable units created from the approved plan |
| 5 | **Design Packages** / حزم التصميم | Engineering offices, scope, submissions, reviews, approval |
| 6 | **Budget Planning** / تخطيط الميزانية | Versioned budgets with categories and items |
| 7 | **Financial Commitments** / الالتزامات المالية | Promises to pay vendors (before the invoice) |
| 8 | **Construction** / أعمال الإنشاء والتنفيذ | Work packages, tasks, progress, inspections, issues, delays |
| 9 | **Actual Costs** / التكاليف الفعلية | Invoices/costs that really happened |
| 10 | **Payments** / المدفوعات | Money paid out and matched to costs |
| 11 | **Contracts** / العقود | The contracts of this project (opened in the Contracts module) |

On top of the tabs a **Project Variance** panel shows four numbers: *Approved Budget Total*, *Committed Obligations*, *Approved Actual Costs* and *Budget Variance* (see Stage 6).

### 3.3 What needs what (the gates)

| You want to… | The system requires |
|---|---|
| Approve the project | At least **one active property** attached; permission *approve project*. |
| Create a budget, design package, commitment, cost or payment | The project is **not Closed or Cancelled**. |
| Create a commitment or an actual cost with a budget item | The item belongs to the **current approved budget**. |
| Create a construction plan | Project is **Approved or In progress**; no other active construction plan exists. |
| Start construction | Project is **In progress**. |
| Create an actual unit from a planned unit | The planned unit is **Approved**; the project is **In progress, Completed or Closed**; the property is actively attached. |
| Activate a contract | The version is **Approved** and **every party has signed**, and the unit/dates are still available. |
| Complete the project | Project is **In progress** (the system does *not* check that construction or finance are finished — that is a management decision). |
| Close the project | Project is **Completed**; permission *close project*. |

---

## 4. The scenario we follow

**Madar Development Co.** buys a plot of land and builds **"Al-Noor Residence"**, a small residential building with 3 floors and 6 apartments. All amounts are in **USD**.

| Person | Function | Member role in the project |
|---|---|---|
| **Sara Haddad** | Company Admin | (not a member — she approves) |
| **Omar Nasser** | Project Manager | Project manager |
| **Lina Mansour** | Financial Manager | Finance manager |
| **Khaled Salem** | Design Manager | Design manager |
| **Hassan Qasem** | Site Engineer | Site engineer |
| **Rana Abbas** | Legal / Contracts Officer | Coordinator |
| **Yousef Darwish** | Sales Manager | Sales manager |
| **Bayt Design Office** | Engineering office (Party) | — |
| **Sanad Construction** | Contractor (Party) | — |
| **Fadi Karam** | Buyer (Party) | — |

Money plan used throughout:

| Budget category | Item | Planned |
|---|---|---|
| Land | Land purchase | 1,200,000 |
| Design | Engineering design fees | 90,000 |
| Works | Structure and finishing | 2,100,000 |
| Permits | Permits and utilities | 60,000 |
| | **Approved budget total** | **3,450,000** |

---

## 5. Stage by stage

### Stage 0 — Before the project: buying the land (optional but recommended)

**Goal:** Record how the company acquires the land and hand it over to a project.
**Who:** Company Admin, Property Manager.
**Where:** **Property Acquisitions** (not a project tab).

**Scenario.** Sara records an acquisition of type *Cash purchase*, agreed value 1,200,000 USD, seller *Mr. Samer Odeh*. She attaches the plot as an acquisition property and the seller as a party (role *Seller*, 100%). She starts due diligence, adds a checklist (title deed verified, survey), and Rana clears the case. Sara approves, then completes the acquisition.

| Acquisition status | Meaning |
|---|---|
| Draft → Under due diligence → Approved → Completed | Normal path; **Cancelled** is possible before completion (reason required). |

**Rules:** *Start due diligence* needs at least one property and one party. *Approve* needs every due-diligence case cleared. Only a **Completed** acquisition can be converted to a project.

**Hand-over to the project.** On the completed acquisition Sara presses **Convert to Project**, enters the project name and type, and keeps **Create Initial Budget** on. The system creates the project in **Planning**, links the land, and (because an agreed value exists) creates a first draft budget with a **Land Cost** category and a **Land Purchase** item of 1,200,000.

> If you do not buy land through an acquisition, skip to Stage 1 and create the project directly.

---

### Stage 1 — Create the project (status: Planning)

**Goal:** Open the project file.
**Who:** Company Admin or a user with *create project*.
**Where:** **Projects → New project**.

**Steps.** Fill in: **Name**, **Project Type** (Residential, Commercial, Mixed-use, Industrial, Hospitality, Land development, Other), **Currency**, **Start Date**, **Expected Completion Date**, **Description** (a super admin also chooses the **Company**).

**Rules that stop you**
- The project starts as **Planning** automatically.
- The **Currency is locked** the moment any budget, commitment, actual cost or payment exists, because all financial records depend on it.

**Scenario.** If the project came from the acquisition it already exists; Omar opens it and checks the name "Al-Noor Residence", type *Residential*, currency *USD*.

---

### Stage 2 — Prepare the project (still Planning)

Three tabs are prepared in any order. While the project is **Closed** or **Cancelled** none of this can be edited.

#### 2.1 Project Properties tab

**Goal:** Say which land/building the project is built on.
**Buttons:** **Attach Property**, **Details**, **Edit**, **Detach**.

**Steps (scenario).** Omar presses **Attach Property**, selects *Plot 14 – Al-Noor Hill*, types the role "primary land" and a note.

**Rules**
- Only properties of the **same company**; the same property cannot be attached twice (a **detached** property cannot be re-attached; its history is kept).
- **Edit** changes only the role and notes of an **active** link.
- **Detach** asks for confirmation; the link stays in history but no longer counts as active.
- When the project is **Approved or In progress**, attaching/detaching notifies the team.
- Permissions: *attach project property* / *detach project property*.

#### 2.2 Project Members tab

**Goal:** Build the internal team.
**Buttons:** **Assign Member**, **Details**, **Edit**, **End assignment**.

**Steps (scenario).** Omar assigns Lina as *Finance manager*, Khaled as *Design manager*, Hassan as *Site engineer*, Yousef as *Sales manager*, each with a start date. Every assignee receives a notification: "You were assigned to project Al-Noor Residence as …".

**Rules**
- Users must belong to the **same company**; a user who is already an **active** member cannot be added again.
- **Edit** works only on active assignments (role, start date, notes).
- **End assignment** keeps the record as history and notifies the member; a person whose assignment ended can later be assigned again.
- Permission: *manage project members*.
- Why it matters: project **notifications** (approval, cancellation, design, budget, construction events) go to the **active members**. Construction issues can only be assigned to active members.

#### 2.3 Planning Structure tab

**Goal:** Describe what will be built before it exists: **Building → Floor → Planned Unit → Specification**.
**Buttons:** **Add Building**, **View structure**, **Edit building**, and grouped actions for **Floors**, **Planned units** and **Specifications** (Add, Edit, Delete; plus **Approve Planned Unit** and **Cancel Planned Unit**), **Delete building**.

**Steps (scenario).** Omar adds *Building A* (type *Residential tower*, code A). He adds floors 1, 2 and 3. On each floor he adds two planned units (A-101 … A-302), type *Apartment*, planned area 120 m², and adds specifications such as *Bedrooms = 3*. When a planned unit is final, Omar uses **Approve Planned Unit**.

**Planned unit statuses:** Planned → **Approved** → (Cancelled), or Planned → Cancelled. After an actual unit is created from it, it shows **Converted to actual unit**.

**Rules**
- A **building** can be deleted only when it has **no floors**; a **floor** only when it has **no planned units**; a **planned unit** only while **Planned** and with **no specifications**. (Delete from the bottom up.)
- **Edit planned unit**, **specifications** and **add specification** work only while the planned unit is **Planned**. Approved or cancelled units keep their planning record.
- Approving or cancelling a planned unit needs its own permission (*approve planned unit* / *cancel planned unit*).

---

### Stage 3 — Approve the project (Planning → Approved)

**Goal:** Formally authorize the project.
**Who:** Company Admin (permission *approve project*).
**Where:** Project page header → **Approve**.

**Scenario.** Sara opens the project and presses **Approve**. If no property were attached the system would refuse with *"Attach at least one active property before approving this project."* Because Plot 14 is attached, the project becomes **Approved**. The approver and time are recorded, and the active members receive "Project Al-Noor Residence is now Approved".

**After approval you can:** create a construction plan, create more budgets, design packages and financial records (these were already possible in Planning), and later press **Start Project**.

**Cancel instead?** From Planning, Approved or In progress, **Cancel Project** asks for a **Reason** and moves the project to **Cancelled** (final). Members are notified.

---

### Stage 4 — Design and engineering (Design Packages tab)

**Goal:** Obtain approved engineering drawings from an outside engineering office.
**Who:** Design Manager (creates, assigns, submits), a reviewer (reviews findings), Company Admin or authorized user (approves).
**Where:** **Design Packages** tab.

A **design package** is one deliverable (e.g. "Architectural design"). Its status moves like this:

```
Planned ─assign office─▶ Assigned ─first scope item started─▶ In progress ─submit─▶ Submitted
   ─start review─▶ Under review ─┬─ review has open findings ─▶ Revision required ─resubmit─▶ Resubmitted ─start review─▶ Under review
                                 └─ no blocking findings ─approve─▶ Approved ─close─▶ Closed
```

**Steps (scenario)**
1. **Create Design Package** — Khaled creates *Architectural design* (discipline *Architectural design*, target submission date). Status: **Planned**.
2. **Assign / replace office** — he selects *Bayt Design Office*. Status: **Assigned**. (The office must be an **active** party; assigning a new office replaces the previous assignment and keeps its history.)
3. **Add scope item** — e.g. "Floor plans", "Facades", "Structural drawings". Each starts **Pending**.
4. **Update scope status** — moves an item **Pending → In progress → Ready** (or **Cancelled**). When the first item starts, the package becomes **In progress**.
5. **Add document version** — uploads drawing files as numbered versions (allowed only while the package is Assigned, In progress or Revision required).
6. **Create formal submission** — selects the document versions. **Rules:** an office must be assigned, **every active scope item must be Ready (or Cancelled)**, and at least one document version must be attached. Status: **Submitted**. A notification tells the team it is ready for review.
7. **Start review** — a reviewer is chosen. Status: **Under review**.
8. **Add finding** — the reviewer records issues with a severity (*Informational, Minor, Major, Critical*), linked to a scope item or document if needed. Only the assigned reviewer can add findings, and only while the review is in progress. Major/critical findings notify the team.
9. **Complete review** — if any finding is still **Open**, the package becomes **Revision required**.
10. **Waive finding** (optional) — formally waives an open finding with a recorded reason (history is kept).
11. **Request revision** — the manager selects the open findings to fix. Only one active revision may exist at a time. The revision moves **Draft → In progress → Ready** with **Start / mark revision ready**.
12. **Resubmit ready revision** — creates a new submission round from the ready revision. Status: **Resubmitted**, then review starts again (steps 7–9).
13. **Approve exact submission** — the approver picks one **reviewed** submission. **Rules:** the package is Under review, at least one review is completed, **no open/addressed Major or Critical finding remains** (they must be accepted or waived), and the submission keeps its exact document versions. Status: **Approved**.
14. **Close package** — only from **Approved**. Status: **Closed**.

**Locks:** Approved and Closed packages keep their formal history and **cannot be structurally changed**. Design packages cannot be changed on a Closed or Cancelled project.

**Money link:** the design contract with the engineering office is later committed and paid in Stage 6, using the *Engineering design fees* budget item.

---

### Stage 5 — Plan the money (Budget Planning tab)

**Goal:** Approve the budget that every later commitment and cost is measured against.
**Who:** Financial Manager prepares; the user with *approve project budget* approves.
**Where:** **Budget Planning** tab.

Budget statuses:

```
Draft ─Submit─▶ Pending approval ─Approve─▶ Approved ─(a newer budget is approved)─▶ Superseded
                      │  └─Return to draft─▶ Draft
          Cancel (Draft or Pending approval, reason required) ─▶ Cancelled
Approved/Superseded ─Create revision─▶ a new Draft (copy of the budget)
```

**Steps (scenario)**
1. **Create budget** — Lina creates *Budget V1*. **Rule:** a project can have only **one Draft** budget at a time.
2. **Add category** — Land, Design, Works, Permits (categories can have a parent category).
3. **Add budget item** — e.g. "Structure and finishing — 2,100,000". Items carry a planned amount; the system gives each item a *budget line* that financial records will point to.
4. Edit/Delete category or item — **only while the budget is Draft**; a category can be deleted only after its child categories and items are removed.
5. **Submit** — **Rule:** the budget needs **at least one item**. Status: **Pending approval**; the team is notified.
6. **Approve** — Sara (or the budget approver) approves. Status: **Approved**. The previously approved budget (if any) becomes **Superseded**, so there is always exactly **one current baseline**. Or **Return to draft** (the budget needs changes) or **Cancel** (reason required).
7. **Create revision** — later, when the plan changes, Lina presses **Create revision** on the approved budget. A copy opens as a new **Draft** (version 2). It becomes the baseline only after it is submitted and approved.

**Rules that stop you**
- Structural changes only in **Draft**. Financial records cannot change on a Closed/Cancelled project.
- **New commitments and actual costs must use an item from the current approved budget** — so nothing can be committed before a budget is approved (unless you have the special permission for *unbudgeted* commitments).

**The Project Variance panel** (top of the project page) reads from this stage:

| Figure | How it is calculated |
|---|---|
| **Approved Budget Total** | Sum of planned amounts of the **approved** budget only |
| **Committed Obligations** | Sum of **committed** commitments only (drafts, released and cancelled are excluded) |
| **Approved Actual Costs** | Sum of **approved** actual costs only (drafts and pending are excluded) |
| **Budget Variance** | Approved budget − committed obligations − approved actual costs. Costs that belong to a commitment appear in both figures, so treat the variance as a cautious (conservative) number. |

---

### Stage 6 — Commit, record costs and pay (three finance tabs)

#### 6.1 Financial Commitments tab (a promise to pay)

**Goal:** Reserve budget for a contract or order before the invoice arrives.
**Statuses:** Draft → **Committed** → Released / Cancelled / (Fulfilled).
**Buttons:** **Create commitment**, **Edit**, **Commit**, **Amend**, **Approve pending amendment**, **Release**, **Cancel**.

**Scenario.**
1. Lina presses **Create commitment**: vendor *Bayt Design Office*, description "Design contract", amount 90,000 USD, budget item *Engineering design fees*. Status: **Draft** (editable).
2. She presses **Commit**. Status: **Committed**; the budget capacity is now reserved and the team is notified.
3. For construction: vendor *Sanad Construction*, 2,000,000 against *Structure and finishing* (planned 2,100,000). Committed normally.
4. Later a waterproofing change adds 150,000. The total on that item would become 2,150,000 — **over the planned 2,100,000**. At **Commit**, the system requires (a) the special permission *approve over-budget commitment* and (b) a written **reason**; the reason is saved on the commitment and the team receives an "Over-budget commitment committed" notification.
5. If a committed amount changes later (a change order), Lina presses **Amend** with an amount change, a budget-amount change and a **reason**. It waits as a **pending amendment**; a user with the approval permission presses **Approve pending amendment**. Only then does the committed value change.
6. If the work is dropped: **Release** (committed only, reason required) or **Cancel** (draft or committed, reason required).

**Rules:** vendor is required; amounts must be positive; the item must come from the **current approved budget** (otherwise only users with *create unbudgeted commitment* can leave it blank); only **Draft** commitments can be edited; only **Committed** ones can be amended or released.

#### 6.2 Actual Costs tab (what really happened)

**Goal:** Record real costs/invoices and have them approved.
**Statuses:** Draft → Pending approval → **Approved** (approved costs can be corrected, never edited).
**Buttons:** **Record actual cost**, **Edit**, **Submit**, **Approve**, **Create corrective offset**.

**Scenario.** Design office invoices the first instalment. Lina presses **Record actual cost**: vendor *Bayt Design Office*, commitment *Design contract*, amount 45,000, date of the invoice. She presses **Submit**. A second person with the approval permission presses **Approve**. Status: **Approved** — it now counts in the variance panel.

**Rules**
- Linked to a **Committed** commitment only; the **vendor must match** the commitment; the budget item must match the commitment's line.
- The cost date **cannot be in the future**.
- Approved costs are locked. To fix a mistake use **Create corrective offset**: a new entry with a **correction reason**, which cannot reduce the cost below money already allocated.

#### 6.3 Payments tab (money going out)

**Goal:** Record real payments and match them to approved costs.
**Statuses:** Completed → (Voided).
**Buttons:** **Record payment**, **Allocate**, **Void**.

**Scenario.** Lina presses **Record payment**: vendor *Bayt Design Office*, 45,000 USD, date, reference "TRF-2027-001", method *Bank transfer*. The payment is saved as **Completed**. She presses **Allocate**, selects the approved 45,000 cost, and allocates 45,000 — the cost is now fully paid.

**Rules**
- A payment can be allocated only to an **Approved** cost of the **same company, project and vendor**.
- Allocation can never exceed the **unallocated payment balance** or the **unpaid balance of the cost**.
- **Void** works only on a **Completed** payment **with no allocations**, and needs a **reason**.
- The payment date cannot be in the future.

> Typical loop: **Commit → Record actual cost → Submit → Approve → Record payment → Allocate.**

---

### Stage 7 — Start the project and run construction

#### 7.1 Start the project (Approved → In progress)

Omar presses **Start Project** (needs *update project*). Status: **In progress**. This unlocks starting site construction and creating actual units.

#### 7.2 Construction tab

**Goal:** Control the site work from plan to completion.
**Who:** Project Manager, Site Engineer.
**Where:** **Construction** tab.

Structure: **Construction plan → Work packages → Tasks**, with **progress updates, inspections, issues and delays**.

Statuses

| Level | Statuses |
|---|---|
| Construction / Work package / Task | Planned → In progress → Completed (or Cancelled). Tasks that need inspection also pass **Awaiting inspection**. |
| Issue | Open → In progress → Resolved → Closed |
| Inspection result | Passed, Passed with notes, Failed |

**Steps (scenario)**
1. **Create Construction** — the project must be **Approved or In progress**, and no other active construction plan may exist. Omar enters planned start, expected completion and the contractor party *Sanad Construction*.
2. **Start construction** — allowed only when the **project is In progress**. Status: **In progress**; notification sent.
3. **Add Work Package** — e.g. "Foundations", "Structure", "Finishing" (name, dates, optionally a budget line from this project).
4. **Package lifecycle → Start work** — the construction must already be started.
5. **Add Task** — e.g. "Pour footings", with an assigned party and an option **requires inspection**.
6. **Task lifecycle → Start work** — the work package must be started first.
7. **Record Progress** — Hassan reports overall progress (0–100%) with the report date. **Progress can only go up**; to lower it, use **Correct Progress** with a **correction reason** (only the current effective update can be corrected).
8. When a task that **requires inspection** reaches 100% it becomes **Awaiting inspection**. **Record Inspection** selects the inspector party, result and date. Only a task awaiting inspection at 100% can be inspected.
9. **Task lifecycle → Complete** — progress must be 100% and, if inspection is required, the **latest inspection must be Passed or Passed with notes**.
10. **Create Issue** — severity *Low, Medium, High, Critical*, optionally assigned to an **active project member**. **Issue lifecycle**: *Start work* → *Resolve* (**resolution notes required**) → *Closed*. A *Critical* issue notifies the team.
11. **Record Delay** — choose a reason (material, contractor, weather, design change, inspection, authority, other) and a **revised end date that is later than the current forecast**; a planned end date must already exist.
12. **Package lifecycle → Complete** — every active task must be Completed and no critical issue may be open.
13. **Complete construction** — every active work package must be Completed and **no active critical issue** may remain. Progress becomes 100%.

**Other rules**
- Delete a construction plan only while it is empty and Planned; delete a work package or task only if it has no history (otherwise **Cancel** it with a reason).
- Completed or Cancelled work cannot be changed. Dates cannot be in the future for progress, inspections, issues and delay reports.
- Overall progress is calculated automatically: tasks → work package → construction.

---

### Stage 8 — From finished building to sales: actual units, listings, reservations, contracts

#### 8.1 Actual Units tab

**Goal:** Turn an approved plan into a real, sellable unit.
**Buttons:** **Create from approved plan**, **Details**, **Change status**.

**Scenario.** When the structure is ready, Omar presses **Create from approved plan** for planned unit *A-101*, picks the property *Plot 14*, unit number *101*, type, **actual area** 118 m² and area unit. The new actual unit starts as **Draft**, and the planned unit becomes **Converted to actual unit**.

**Rules:** the planned unit must be **Approved**; the project must be **In progress, Completed or Closed**; the property must be **actively attached** to the project; one planned unit can have only one actual unit.

**Unit status:** Draft → **Ready** → Reserved / Maintenance / Inactive. **Change status → Ready** requires the property, unit number, type, actual area and area unit. **Inactive** requires a reason; reactivating (back to Draft) requires a reason. Every change is stored in the unit's history; *Ready* and *Inactive* notify the team.

#### 8.2 Listing, offer and reservation (the sales module)

- **Listing** — Yousef creates a listing for the unit (title, sale/rent, asking price > 0, currency). Statuses: Draft → **Published** → Paused / Archived (Archived can be restored to Draft). An **approved planned unit** may also be advertised *before* it is built (off-plan). Inactive units cannot be marketed.
- **Reservation** — Draft → Pending (sent for approval) → **Active** (approved) → expired/cancelled/rejected. Only a **Ready** unit can be reserved. A reservation can also start from an **accepted offer**.

#### 8.3 Contracts (the project's Contracts tab shows them)

**Goal:** Legally bind the sale or lease.
**Who:** Legal/Contracts Officer (prepares, submits), approver (approves), parties (sign), Company Admin or authorized user (activates).
**Where:** **Contracts** module; the **Contracts** tab of the project lists the project's contracts with a **Details** link.

Contract types: **Sale**, **Off-plan sale**, **Lease**. Sale and lease need an **actual unit**; an off-plan sale needs a **planned unit**. A contract can start from an **Active reservation**.

Version statuses: Draft → **Submitted** → **Approved** → **Signed** (after activation); a returned version is **Revision required**; replaced versions are **Superseded**. Contract statuses: Draft, Under review, Revision required, Approved, **Active**.

**Steps (scenario)**
1. **Create contract** — Rana creates a *Sale* contract for unit A-101 with buyer *Fadi Karam* (role *Buyer*, 100%), the seller company, terms (title and text), a **legal template version** of the same contract type, and amount 185,000 USD.
2. **Submit for review** — **Rules:** at least one party, at least one term, a valid template version, and total amount + currency. Version becomes **Submitted** (contract: *Under review*).
3. **Approve version** (or **Request revision** with notes — then Rana edits the draft and resubmits). Approved versions are locked.
4. **Generate official PDF** — creates the one official PDF for the **approved** version (it is never silently replaced). If your legal process also needs an uploaded legal document, use **Upload contract document** after approval; approved versions are locked.
5. **Record signature** — one signature **per party**, tied to the official PDF. The company's own signature uses the authorized signature saved in Company Settings.
6. **Generate signed final PDF** — after every party has signed.
7. **Activate contract** — **Rules:** the version is **Approved**, **all parties signed**, the unit is **not held by another active reservation**, a sale unit has **no other active sale**, and a lease has valid dates that **do not overlap** another active lease on the same unit. On success the contract is **Active** and the version **Signed**.
8. **Create amendment** — later changes are made as a **new draft version** (an amendment needs an **Active** contract and a **Signed** version). The active contract stays Active while the amendment is reviewed.

**Payment schedule** (Payment Schedules tab of the contract)
- **Create payment schedule** — needs an **approved or signed** version. Choose a generated plan (down payment + equal instalments, interval in months) or manual. **The installments must add up exactly to the contract amount.** Only one active schedule per contract.
- **Edit installments** (draft only) → **Submit schedule for approval** → **Approve and issue schedule** (needs an **Active** contract). Issued installments are history and cannot be deleted.
- **Record payment** — receipts are allocated to instalments (kinds: down payment, instalment, deposit, other); a receipt cannot exceed the instalment balance and the date cannot be in the future.
- **Reverse / refund payment**, **Reschedule remaining balance**, **Cancel schedule** (issued only, reason required, **not allowed if receipts exist**).

---

### Stage 9 — Finish, close (or cancel) the project

**Complete Project** (In progress → Completed). Needs *update project*.
**Close Project** (Completed → Closed). Needs *close project*.

> The system lets you complete a project without checking that construction, payments or contracts are finished. Use this **closing checklist** before pressing the buttons:
> 1. Construction is **Completed** and no critical issue is open.
> 2. Every design package is **Approved/Closed**.
> 3. No **Pending approval** budget, commitment amendment or actual cost remains.
> 4. Every approved cost is paid and allocated (or knowingly open).
> 5. Contracts are **Active** with their payment schedules issued, and receipts match.
> 6. All actual units that should be sold are **Ready/Reserved** and contracted.

When the project is **Closed** (or **Cancelled**): planning records, design packages, budgets, commitments, costs and payments become **read-only**. Nothing is deleted — the full history stays for audit.

**Cancelling** (before completion) needs a **Reason**. It does not automatically cancel the children, so cancel open design packages, commitments or construction plans yourself if needed.

**Deleting a project.** The **Delete** button works only for a project that is still in **Planning** *and* has no history. A project with contracts, payments, financial commitments, actual costs, construction records, design packages, actual units, or any budget that is no longer a draft **cannot be deleted** — the system explains exactly what blocks it. The correct way to stop such a project is **Cancel** (or **Close** after completion). For a deletable project, its draft budgets, buildings, floors, planned units, specifications, members and property links are removed together.

---

## 6. Rules that apply everywhere

1. **History is never erased.** Approved, issued, signed, completed and closed records are locked. Mistakes are fixed with a *new* controlled record (revision, corrective offset, amendment, reversal), never by editing the old one.
2. **Reasons are mandatory** whenever something is cancelled, released, voided, waived, returned, corrected, or goes over budget.
3. **No future dates** for invoices, payments, progress, inspections, issues and delays.
4. **Separate steps, separate people.** The person who prepares usually is not the person who approves (budget, actual cost, contract). Permissions decide who can press each button; buttons you cannot use are hidden.
5. **Every refusal explains itself.** The system shows a notification with the reason (a missing item, a wrong status, a limit). Read it first — it normally tells you what to do next.
6. **Notifications go to the active project members** (and to the person who acted). Keep the Members tab current.
7. **One company only.** You never see or link another company's data.
8. **Money is always in a currency.** The project currency is fixed once financial history exists; amounts must be positive; allocations can never exceed balances.

---

## 7. Status reference

| Object | Statuses (in order) |
|---|---|
| Project | Planning → Approved → In progress → Completed → Closed · Cancelled |
| Planned unit | Planned → Approved → Cancelled · Converted to actual unit |
| Design package | Planned → Assigned → In progress → Submitted → Under review → (Revision required → Resubmitted → Under review) → Approved → Closed |
| Scope item | Pending → In progress → Ready · Cancelled |
| Revision | Draft → In progress → Ready · Cancelled |
| Review finding | Open → Waived (by the *Waive finding* action) |
| Budget | Draft → Pending approval → Approved → Superseded · Cancelled |
| Commitment | Draft → Committed → Released · Cancelled · Fulfilled |
| Commitment amendment | Pending approval → Approved |
| Actual cost | Draft → Pending approval → Approved |
| Payment | Completed → Voided |
| Construction / Work package / Task | Planned → In progress → (Awaiting inspection) → Completed · Cancelled |
| Construction issue | Open → In progress → Resolved → Closed |
| Actual unit | Draft → Ready → Reserved / Maintenance / Inactive |
| Listing | Draft → Published → Paused · Archived |
| Reservation | Draft → Pending → Active · Rejected · Cancelled · Expired |
| Contract | Draft → Under review → (Revision required) → Approved → Active |
| Contract version | Draft → Submitted → Approved → Signed · Revision required · Superseded |
| Payment schedule | Draft → Pending approval → Issued · Superseded · Cancelled |

---

## 8. "Why can't I…?" — quick answers

| You see / want | Why | What to do |
|---|---|---|
| No **Approve** button on a project | The project is not in Planning, or you lack *approve project* | Ask an authorized user. |
| "Attach at least one active property…" | No active property linked | Add one in **Project Properties**. |
| Can't edit a budget item | The budget is not **Draft** | Use **Create revision**. |
| Can't submit a budget | It has no items | Add at least one budget item. |
| Can't commit — "item must be from the current approved budget" | No approved budget, or the item belongs to an old version | Approve the budget first. |
| Commit asks for a reason / is refused | The commitment goes over the planned amount | Provide a reason; you also need *approve over-budget commitment*. |
| Can't record an actual cost against a commitment | Commitment is not **Committed**, or the vendor differs | Commit it first, or use the same vendor. |
| Allocation refused | Cost not approved, different vendor, or amount exceeds balance | Approve the cost, check the vendor, lower the amount. |
| Can't void a payment | It already has allocations | Reverse the allocation first. |
| Can't submit a design package | No office assigned, scope items not Ready, or no documents | Complete those three. |
| Can't approve a design package | Open major/critical findings | Accept or **Waive** them first. |
| Can't create construction | Project not Approved/In progress, or an active plan exists | Approve the project or finish the existing plan. |
| Can't start construction | The **project** is not In progress | Press **Start Project** first. |
| Can't complete a task | Progress < 100% or inspection not passed | Record progress/inspection. |
| Can't complete construction | A work package unfinished or a critical issue open | Finish packages, resolve critical issues. |
| Can't create an actual unit | Planned unit not Approved, project not In progress, or property not attached | Approve the planned unit, start the project, attach the property. |
| Can't set a unit to Ready | Missing area/type/number | Complete the unit data. |
| Can't activate a contract | Version not approved, a party hasn't signed, unit reserved/sold/overlapping | Check each item in the message. |
| Can't delete a project | It has history or is not in Planning | **Cancel** or **Close** it instead. |
| Everything is read-only | The project is Closed or Cancelled | By design — history is preserved. |

---

## 9. One-page summary of the Al-Noor Residence timeline

| When | Stage | Result |
|---|---|---|
| Week 1 | Acquisition completed and converted | Project in **Planning**, land linked, initial budget draft |
| Week 2 | Properties, team, planning structure | 1 building, 3 floors, 6 planned units approved |
| Week 3 | Project **Approved** | Sara approves; team notified |
| Weeks 3–8 | Design | Package **Approved** after one revision round, then **Closed** |
| Week 4 | Budget V1 approved | 3,450,000 baseline |
| Weeks 5–6 | Commitments | Design 90,000; Works 2,000,000; later +150,000 over budget with reason |
| Month 3 | **Start Project** + construction plan | Site work begins |
| Months 3–12 | Construction, costs, payments | Progress, inspections, delays, invoices approved and paid |
| Month 10 | Actual units created and made Ready | Units listed and reserved |
| Month 11 | Sale contract signed and **Activated**; payment schedule issued | Receipts recorded |
| Month 13 | **Complete Project**, checklist reviewed, **Close Project** | Project **Closed**, history preserved |
