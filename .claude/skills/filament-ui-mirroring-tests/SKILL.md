---
name: filament-ui-mirroring-tests
description: Write feature tests that mirror the real Filament UI — inspect the form fields and page actions first, then test exactly those via Livewire assertions. Use when adding tests for any Filament resource, page action, or workflow.
---

# Filament UI-Mirroring Tests

## The core rule
**A test must exercise what the user actually sees and clicks.** Before writing a test:

1. Open the Filament page/resource and read `form()` / `getHeaderActions()` / table actions.
2. List every form field name and every action name from that code.
3. Write the test using exactly those names: `assertFormFieldExists('x')`, `fillForm([...])`, `callAction('name', [...])`.

If the test invents field/action names that don't exist in the UI, it's testing fiction — fix the test, not the UI (unless the UI itself is missing something the plan requires).

## Form testing pattern

```php
Livewire::test(CreateSomeResource::class)
    ->assertFormFieldExists('field_one')      // field exists in form()
    ->assertFormFieldExists('field_two')
    ->fillForm([                               // one merged fill() — multiple fill() calls overwrite each other
        'field_one' => 'value',
        'field_two' => $model->id,
    ])
    ->call('create')
    ->assertHasNoFormErrors();
```

- Form state lives in the Livewire public `$data` property — never read `$component->field` (PropertyNotFoundException) and never call `$form->getState()` in a mount-prefill test (it validates).
- To test `mount()` pre-fill from a query string: set `$_GET`, `$_SERVER['QUERY_STRING']`, `app()->instance('request', Request::createFromGlobals())`, then `new ThePage(); $component->mount();` and assert on `$component->data`.
- `assertFormSet('x', ...)` expects an array/Closure — use `assertFormSet(['x' => 'y'])`.

## Action testing pattern

```php
Livewire::test(ViewSomeResource::class, ['record' => $record->getRouteKey()])
    ->callAction('approve')                    // header action name from getHeaderActions()
    ->assertHasNoTableActionErrors();
```

- Chain actions in the real order the UI requires (e.g. `send` before `approve` — the action is hidden until the record reaches the right status).
- Actions with a modal form need data: `callAction('cancel', ['reason' => '...'])`.
- Table actions use `callTableAction('name', $record)`.
- `Livewire::test()` has no `->route()`; HTTP GET to Filament pages returns 403 in tests — always drive pages through Livewire.

## Every page/view you touch must actually be rendered by a test

A service-level test only proves the data is correct — it never evaluates anything
that only runs at render time: a `->url()`, `->state()`, `->formatStateUsing()`, or
other closure on an infolist/table entry, a Blade view, a computed property, a
relation accessed without `loadMissing`, and so on. A mistake there (wrong route name,
null property access, undefined array key, a missing Blade include) passes every
service test and only surfaces live, in front of the user — exactly what already
happened with `PartyResource::getUrl('view', ...)` (no `view` page existed; it 404'd
for any user who could actually follow the link, and silently returned null for
everyone else, so no test caught it until it shipped).

**The rule: whenever you add or change a page, resource, infolist, table, widget, or
custom Blade view — anything that gets rendered — add or extend a test that actually
renders it**, not just a test of the service/model behind it:

- A full Filament page: `Livewire::test(SomePage::class, [...])->assertSuccessful()`.
- A specific infolist/table entry or action: mount the real page and also
  `->assertSee('expected text')` for the value you just added, so the closure that
  produces it actually runs.
- A custom Blade view (e.g. a guide/explainer page): render it the same way, not just
  read the `.blade.php` file and assume it compiles.
- Grant the real permission whatever closure/policy check you're exercising actually
  checks — not a lesser one — in at least one of these tests, or the buggy branch
  never executes and the mistake ships anyway (as it did here: the test's actor lacked
  the permission needed to reach the broken `getUrl()` call).
- Before calling `SomeResource::getUrl('view'|'edit'|'index', ...)`, open that
  resource's `getPages()` and confirm the key actually exists — several resources in
  this app (e.g. `PartyResource`) have no `view` page, only `edit`.

If rendering genuinely can't be done in a test (rare), say so explicitly rather than
silently settling for a service-level test and calling the UI covered.

## Service-level tests (when UI testing is impractical)

Put creation/transition logic in a Service (e.g. `PropertyAcquisitionService`), have the page delegate to it, and test the service directly:

```php
$project = app(PropertyAcquisitionService::class)->convertToProject($actor, $acquisition, [...]);
$this->assertSame(1, $budget->version_number);
```

## Environment rules (learned the hard way)

- Run tests via PowerShell: `php -d memory_limit=512M vendor\bin\phpunit tests/Feature/SomeTest.php` (default 128M memory is exhausted by the full suite).
- `RefreshDatabase` re-migrates the MySQL `realState_test` DB per test class — individual files take ~5-15s; that's normal, don't "optimize" it away.
- Always `actingAs($actor)` and grant the exact permissions the policy checks (`Permission::findOrCreate('create_project', 'web')` etc.) before Livewire tests.
- Non-fillable columns (`status` etc.): set with `forceFill([...])->save()` in test fixtures.
- Enum columns: pass `Enum::Case->value`, never the enum object or its name.
- Validation messages are pre-translated — assert on `$e->errors()` field keys, not English text.
