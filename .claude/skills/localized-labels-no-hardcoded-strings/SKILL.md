---
name: localized-labels-no-hardcoded-strings
description: Never hardcode user-visible text in Filament resources, relation managers, widgets, services, or enums. Use when adding or editing any label, helper text, validation message, notification, badge, currency symbol, or widget stat in this bilingual (ar/en) app.
---

# No hardcoded user-visible strings

The app is Arabic-first (`APP_LOCALE=ar`) with English. Every string a user can see must go through translation, in BOTH `lang/en` and `lang/ar`.

## What counts as "user-visible" (all of these were missed before)
- Form `label()`, `placeholder()`, `helperText()`, `hint()`, `suffix()/prefix()`
- Validation closures: `$fail('...')`, `ValidationException::withMessages([...])` — including those thrown from **Services**
- Notification `title()` / `body()`, modal headings, empty states
- Enum labels (`getLabel()`), e.g. `Currency` returned `'Israeli Shekel (₪)'` for every locale
- Widget stat labels/descriptions, chart headings and dataset labels
- Default values that contain words (`$ref . ' - Project'`)
- Currency symbols/codes: no `->prefix('$')` or `->money('USD')`; derive from the record's currency (`Currency::tryFrom($code)?->getSymbol()`, `->money(fn () => $code)`)

## Rules
1. **Use namespaced PHP keys, not bare words.** Module files: `__('acquisition.parties.select_role_first')`, `__('dashboard.kpis.active_projects')`, enum labels in `lang/*/enums.php`.
2. **Never `__('Dashboard')`-style single-word keys when a `lang/*/dashboard.php` exists.** On Windows (case-insensitive FS) the PHP file array wins and `(string) __()` throws `Array to string conversion`. Use `trans_safe()` (app/helpers.php) when a key could be a bare word.
3. **Dynamic values via placeholders**, never string interpolation: `__('x.allocation_hint', ['role' => $label, 'allocated' => $n])`. Translate the *role/status value* too (`AcquisitionPartyRole::labelFor($role)`), not the raw DB value.
4. **Add keys to en AND ar in the same change.** Arabic text must be real Arabic, not a copy of English.
5. **Services throw translated messages** (`__('acquisition.errors.x')`). Tests must assert `__('acquisition.errors.x')` or error *field keys*, never English literals (test locale is `ar`).
6. **Relation managers are not exempt**: audit the resource, every relation manager, its tabs, header actions, row actions, and modals — forms, tables, notifications.

## Verify before declaring done
- Grep the touched files for string literals passed to label/helperText/title/body/fail/withMessages without `__(`.
- Switch locale in a test (`app()->setLocale('ar')`) and assert labels differ from English; for enums assert every case has a label in both locales (see `tests/Feature/AcquisitionLocalizationTest.php`).
- Run `php artisan optimize:clear` after adding lang files.
