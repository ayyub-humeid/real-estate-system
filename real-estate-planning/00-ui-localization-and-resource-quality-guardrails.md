# UI Localization & Resource Quality Guardrails

## Purpose

This document records cross-system lessons from production-like manual review.
It applies to every existing and future Filament resource, page, relation
manager, action modal, slide-over, table, infolist, and empty state.

It supplements the global domain conventions; it does not replace them.

---

## 1. Localize every static value

Never expose implementation values, class-derived labels, or raw database
values to a user.

This includes:

- resource, model, plural-model, navigation, tab, section, field, filter,
  action, modal, confirmation, empty-state, helper, toast, and validation
  labels;
- enum and string-backed status, type, source, priority, severity, method,
  role, and workflow-action values;
- relation-manager action labels such as **View CRM follow-up task**,
  **View Unit ownership**, and **View CRM Pipeline stage**.

Required implementation pattern:

    ->formatStateUsing(
        fn (?string $state): string => $state
            ? __('domain.statuses.' . $state)
            : __('—'),
    )

Where a PHP enum exists, prefer its label and color methods over duplicated
match statements. Never render an enum object or its raw backing value directly.

Every supported locale must have a value. English is the source-language
fallback; Arabic must be verified explicitly.

---

## 2. Explicit labels for Filament resources and relation managers

Do not rely on Filament's class-name humanization for any user-facing label.
Each resource and relation manager must explicitly provide localized navigation,
model, plural-model, and relation title labels.

This prevents labels such as crm follow up task, unit ownerships, or project
property from reaching Arabic users.

---

## 3. Reference numbers are server-controlled

References such as acquisition, employee, project, offer, reservation, and
contract numbers are never editable user input.

- Generate them safely in the server/domain layer.
- Use locking, a sequence, or a database-assigned primary key as appropriate;
  never use MAX(id) + 1.
- In create forms show a disabled field with
  “Generated automatically after creation.”
- In edit/view forms show the generated value in the same disabled field.
- Do not trust a submitted reference value.

---

## 4. Modern dark-surface interaction standard

All resource pages, relation tabs, action modals, view modals, create/edit
modals, confirmations, searchable-select dropdowns, and slide-overs must use
the shared Madar dark surface.

Requirements:

- preserve the shared #0D0D0F application canvas and component hierarchy;
- use enough responsive modal padding so forms never feel cramped;
- style dropdown menus and search inputs, not only their closed select;
- use semantic status badges with distinguishable colors;
- give related records a deliberate, readable layout rather than a
  class-generated default;
- ensure the same result in RTL and LTR.

Do not create one-off inline styling for a single relation manager when the
shared admin chrome can correctly solve the issue for all resources.

---

## 5. Mandatory manual UI audit

For each resource and each relation manager, verify both Arabic and English:

1. List: headings, columns, filters, badges, row/bulk actions, empty state.
2. Create: labels, placeholders, dependent selects, validation, notifications.
3. View/details: sections, tabs, nested relation data, status/history labels.
4. Edit: immutable fields, workflow restrictions, validation, success/error
   feedback.
5. Every action modal or slide-over: title, form labels, help, confirmation,
   errors, responsive spacing, and RTL/LTR direction.

For enum-backed records, test every state that can be reached by the current
domain workflow. A passing backend test alone does not satisfy this audit.

---

## 6. Prevent errors before the database

Dependent selects must query only valid records after their parent selection is
known. Server-side validation remains mandatory, but the UI must avoid offering
known-invalid choices whenever practical.

Every workflow/business action must provide a translated, actionable result:

- success notification after commit;
- mapped validation errors on the relevant field;
- clear authorization feedback;
- persistent safe unexpected-error notification;
- no silent no-op, raw SQL exception, class name, enum name, or translation
  key in the UI.

---

## 7. Regression test expectations

For any bug discovered in a resource:

1. add or update a backend regression test for the failing state;
2. add a UI-facing test where practical (Livewire/Filament schema, label,
   action visibility, or validation mapping);
3. add the manual audit case to the phase test scenario;
4. run it on MySQL and PostgreSQL when it changes a persisted workflow.

This document intentionally avoids prescribing new domain tables or workflows;
it enforces presentation, localization, and safe interaction quality.

---

## 8. Contextual help without over-engineering

Project tabs should explain only the next business decision when it is not
obvious from the field label. Use one concise translated helper or empty-state
description; do not add onboarding tours or duplicate documentation.

Examples:

- an optional external/reference code must say that it is optional and who
  supplies it;
- a primary discipline must explain what to choose for a multidisciplinary
  package;
- a target date must identify the expected business milestone;
- a detailed scope is added after the package exists, not forced into its
  creation form.
