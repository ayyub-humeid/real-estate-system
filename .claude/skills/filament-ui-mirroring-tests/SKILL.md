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

## Infolists and links need a rendering test too

A service-level test only proves the data is correct — it never evaluates `->url()`,
`->state()`, or other closures on an infolist/table entry, since those only run when
Filament actually renders the page. A mistake there (wrong route name, wrong resource)
passes every service test and only surfaces live, in front of the user.

- Whenever you add or change an infolist entry, table column, or any `->url()` /
  `->state()` closure, add a test that mounts the real page via `Livewire::test()` and
  asserts `->assertSuccessful()` (plus `->assertSee('expected text')` when useful) —
  not just a service call.
- Before writing `SomeResource::getUrl('view'|'edit'|'index', ...)`, open that
  resource's `getPages()` and confirm the page key actually exists. Several resources
  in this app (e.g. `PartyResource`) have no `view` page, only `edit` — `getUrl('view', ...)`
  throws `RouteNotFoundException` for any user who can actually follow the link, while
  silently returning no URL (and no error) for a user who can't. Grant the real
  permission the `url()` closure checks (not a lesser one) in at least one test, or the
  buggy branch never executes and the mistake ships anyway.

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
