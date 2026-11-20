---
name: user-friendly-ux
description: |
  User-friendly UI/UX patterns for real-estate admin panels. Every action, button,
  confirmation dialog, status change, and workflow step must guide the user clearly.
  Apply proactively — don't wait to be asked.
autoApply: true
---

# User-Friendly Application UX Skill

## Core Principles

This is a production SaaS for real-estate companies. Admins are not developers.
Every interaction must be self-explanatory without reading documentation.

## Rules (apply to ALL new and existing code you touch)

### 1. Action Buttons — Say What Happens, Not Just the Verb
- Bad: "Convert", "Approve", "Send"
- Good: "Register as client", "Approve and activate reservation", "Send price to customer"
- The label should tell the user the OUTCOME, not just the action name

### 2. Confirmation Dialogs — Three Lines
Every confirmation modal must have:
1. **What will happen** — one sentence describing the outcome
2. **What changes** — what records are created/modified/status changes
3. **What to do next** — the next step after this action

### 3. Helper Text on Fields and Actions
- Every non-obvious field gets `->helperText()` explaining its purpose
- Workflow actions get `->modalDescription()` explaining the full consequence
- Status fields show what this status means for the workflow, not just the status name

### 4. Error Messages — Actionable
- Bad: "Conversion failed"
- Good: "Two clients share this phone number (Ahmad, Mohammad). Merge them in the Clients page first, then retry."
- Every error must tell the user HOW to fix it

### 5. Success Notifications — Confirm + Guide
After a successful action:
1. Confirm what happened: "Client registered successfully"
2. Guide the next step: "You can now create a reservation or contract for this client"
- Use `->body()` on notifications for the guidance line

### 6. Status Descriptions
- Enum labels should be human-readable business terms, not code terms
- Add `->description()` or tooltip helper text explaining what each status means
- On view pages, show subheadings or hint text explaining the current state and next possible actions

### 7. Empty States and Placeholders
- Empty relation managers: "No reservations yet. Create one from an accepted offer."
- Empty selects: "No ready units available" (not just an empty dropdown)
- Null fields: show "—" or a helpful placeholder, never raw null

### 8. Terminology Consistency
- "Lead" = عميل محتمل (potential client) — someone who enquired
- "Client/Party" = عميل مسجّل (registered client) — approved to do deals
- "Convert lead" = تسجيل كعميل (register as client) — NOT "convert to party"
- Always use business language, never model names (Party, CrmOffer, etc.)

### 9. Visual Hierarchy for Workflow Actions
- Primary action (the happy path): `->color('success')` or `->color('primary')`
- Secondary actions: default color
- Destructive/cancel actions: `->color('danger')`, grouped in an ActionGroup
- Always put the primary action first/most prominent

### 10. Bilingual Consistency
- Every user-facing string uses `__()` with both `lang/en` and `lang/ar` entries
- Helper texts, descriptions, and guidance must be translated in both languages
- Arabic text should be natural Arabic, not literal translations from English
