# Defensive Quality, Translation & Performance Guardrails

## Purpose

This is a mandatory cross-system checklist created from real production and
manual-review failures. It supplements:

- `00-domain-rules-and-global-conventions.md`
- `00-ui-localization-and-resource-quality-guardrails.md`
- `AGENTS.md`

It applies to every new or changed Laravel service, Filament resource, page,
relation manager, action, enum, notification, query, and translation.

---

## 1. Translation must always resolve to text

Laravel translation files are nested arrays. Calling a group key can return an
array, which will crash any API that requires `string`, such as
`getNavigationLabel(): string`, a Filament label, a heading, or a notification
title.

### Required rules

1. Use **leaf keys only** in user-facing code:

   ```php
   // Correct: resolves to text.
   __('contract_finance.navigation.payments')

   // Forbidden: can resolve to the whole PHP translation array.
   __('contract_finance')
   ```

2. Never use a translation-file name, section name, or an unknown dynamic
   prefix as a label.
3. Dynamic translation keys must append a validated known value:

   ```php
   __('contract_finance.schedule_statuses.' . $status->value)
   ```

   Prefer an enum `getLabel()` method where an enum exists.
4. Every newly introduced leaf key must exist in both `lang/en` and `lang/ar`.
5. Do not pass an array returned by `config()`, a translation group, a JSON
   attribute, or an enum object directly to a string-only Filament API.
6. New or changed Resources, Pages, Relation Managers, Enums, and
   Notifications must have a regression test that executes their label methods
   in both English and Arabic and asserts `is_string(...)`.

### Safe review pattern

Before adding a typed label, inspect the exact key in both locale files. A
valid translation is a scalar string, not an intermediate array. If a key is
not guaranteed to be scalar, create a specific leaf key; do not cast an array
to string and do not hide the problem with a generic fallback.

---

## 2. Localize every static user-facing value

The existing UI-localization guardrail remains mandatory. In addition, audit:

- relation-manager titles, model labels, plural labels, empty states, table
  actions, bulk actions, modal titles, confirmation text, repeater action
  labels, helper text, placeholders, and validation titles;
- raw database state values, enum backing values, source/type/method values,
  class-derived labels, and unknown placeholders;
- database notifications, broadcast notifications, queued notifications, and
  scheduler/reminder messages.

No code is complete merely because the main Resource is translated. The view,
every tab, and every action modal must be translated in Arabic and English.

---

## 3. Validate early, validate again on the server

UI prevention improves usability; server validation protects data integrity.
Both are required.

### Before submission / in the form

- Dependent selects show only records valid for the selected company, parent,
  status, project, contract, or workflow state.
- Use reactive visibility, disabled state, date boundaries, numeric limits,
  unique checks where appropriate, and clear translated helper text.
- Hide actions that cannot currently be performed. Do not show an action that
  is predictably invalid solely to let the server reject it.

### On the server / in the domain service

- Repeat every security, tenant, state-transition, amount, uniqueness, and
  relationship validation inside the service/Policy/database transaction.
- Convert expected invalid input into field-specific `ValidationException`
  messages before a SQL constraint becomes the user-facing error.
- Use database unique constraints and locks as the final concurrency barrier;
  never rely on UI filtering alone.

---

## 4. No silent failures or raw technical errors

Every business action must have one clear outcome.

```text
Success          → toast after transaction commit + refreshed UI
Validation error → mapped, translated field/business message
Authorization    → translated, explicit unauthorized message
Unexpected error → report exception + persistent safe notification
```

Forbidden outcomes:

- no visible response;
- a swallowed exception;
- a raw SQL, PHP, container, enum, translation-key, or class-name error;
- a success notification if a related operation/rendering step failed;
- a toast followed by rethrowing the same handled exception into Livewire.

When a form action catches an exception, it must either map and display it or
let the framework handle it consistently. Do not do both.

---

## 5. Explain consequential actions before they run

For a consequential workflow action (submit, approve, activate, issue,
reschedule, cancel, reverse, close, request revision, or an action that freezes
history), provide a concise translated tooltip and, where helpful, a modal
description that answers:

```text
What will this action change?
What will remain immutable afterward?
What must be true before it can succeed?
What should the user do if it cannot proceed?
```

Do not make the user discover an obviously unavailable target only at the final
workflow action. Filter known-unavailable units, reservations, contracts,
parties, documents, schedules, and child records out of dependent selects.
The service must still re-check under a transaction/lock because another user
can change availability after the form loads.

---

## 6. Performance and N+1 protection

Before declaring a screen complete, inspect every table, infolist, tab, and
notification recipient query.

### Required query discipline

- Eager load only relations shown by the current screen with `with()`.
- Use `withCount()`, `withSum()`, `exists()`, or aggregate queries instead of
  loading child collections simply to calculate a count or total.
- Use `loadMissing()` only when an already-loaded record needs a relationship.
- Paginate relationship tables; do not load a whole company collection into a
  `Select` unless it is deliberately bounded/searchable.
- Add indexes for company scope plus the actual filter/order/join pattern.
- Never solve a single screen by adding broad relationships to model `$with`.
- Do not call relationships, queries, policies, or service summaries inside a
  per-row closure unless the needed data was preloaded or aggregated first.

For important high-volume tables, add a focused test or review assertion for
the expected relation loading/query shape.

---

## 7. Filament and Livewire safety checks

- Closure parameters must use names/types Filament can resolve. Never invent
  parameters such as `$id` or `$q` unless that exact component API supplies
  them.
- Normalize enum-or-string form state before comparing it:

  ```php
  $status = $state instanceof StatusEnum
      ? $state
      : StatusEnum::tryFrom((string) $state);
  ```

- A policy call must receive every record/context argument required by its
  policy method.
- Visibility is only UX. The domain service and Policy must re-check the
  same permission, company, ownership, and allowed state.
- Relation-manager creates explicitly inherit `company_id` from the owner
  record through both required mutation hooks described in `AGENTS.md`.

---

## 8. Required pre-merge / pre-deploy audit

For every changed domain:

1. Run static syntax checks for changed PHP files.
2. Run targeted workflow, authorization, tenant-isolation, translation, and
   regression tests on MySQL and PostgreSQL where persistence changes.
3. Clear relevant Laravel/Filament caches after adding a Resource, Relation
   Manager, Page, Policy, translation, or service class.
4. Verify new PHP classes have the correct `<?php`, namespace, PSR-4 path,
   and imports; then confirm they are included in the committed diff.
5. Manually test Arabic and English for list, create, view, edit, each tab,
   empty state, modal, and action visibility.
6. Record every newly found defect as a regression test and add its prevention
   rule here or to the domain specification when it is reusable.

---

## 9. Completion gate

A feature is not ready if any visible route can expose an untranslated static
label, a raw technical exception, an invalid choice that the UI could have
filtered, an N+1 query on a normal list/detail screen, or a silent failure.

Keep the solution small: reuse shared translations, enums, services, Policies,
and UI patterns. Do not create a second translation, validation, notification,
or performance framework for one module.
