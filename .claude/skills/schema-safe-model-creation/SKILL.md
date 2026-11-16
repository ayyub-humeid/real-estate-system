---
name: schema-safe-model-creation
description: Rules and pitfalls for creating Eloquent records without hitting MySQL 1364 "Field X doesn't have a default value" errors. Use before any Model::create() in services/pages, and when writing tests for creation flows.
---

# Schema-Safe Model Creation

## The core rule
**Before any `Model::create([...])`, check the table's migration for NOT NULL columns without defaults and include every one of them.** MySQL throws `SQLSTATE[HY000]: 1364 Field 'x' doesn't have a default value` when a required column is missing from the insert.

## Real errors hit in this project (do not repeat)

### 1. `project_budgets.version_number` — 1364 on acquisition→project conversion
- **Cause:** `version_number` is `unsignedInteger` NOT NULL with no default and a unique `(project_id, version_number)`. The conversion code created a `ProjectBudget` without it.
- **Fix:** compute under a lock, same as `BudgetingService::createBudget()`:
  ```php
  $next = ((int) ProjectBudget::withoutGlobalScopes()->where('project_id', $project->id)
      ->lockForUpdate()->max('version_number')) + 1;
  ```

### 2. `budget_items.budget_line_id` + `planned_amount` — would have been the NEXT 1364
- **Cause:** both are NOT NULL with no default. The conversion seeded an item with `unit_price`/`currency`/`sort_order` — columns that don't even exist on `budget_items` (silently dropped because not in `$fillable`), while omitting the two required ones.
- **Fix:** create a `BudgetLine` first, then the item with `budget_line_id` and `planned_amount` (pattern: `BudgetingService::addItem()`, lines ~47-50):
  ```php
  $line = BudgetLine::create(['company_id' => ..., 'project_id' => ...]);
  BudgetItem::create(['company_id' => ..., 'budget_category_id' => ..., 'budget_line_id' => $line->id,
      'planned_amount' => $amount, 'quantity' => 1, 'unit_cost' => $amount, ...]);
  ```

### 3. `unit_status_histories.changed_at` — 1364
- **Cause:** status-history insert omitted `changed_at` (and `unit_id`).
- **Fix:** include `changed_at => now()` (and `created_at => now()`) in the create array.

### 4. `unit_reservations.transaction_type` — 1364 in tests
- **Cause:** direct `::create()` in a test omitted `transaction_type`.
- **Fix:** include it (`'transaction_type' => 'sale'`).

## Related pitfalls (same family)

- **`$fillable` vs `forceFill`:** columns like `status` are often NOT fillable. `Model::create(['status' => ...])` silently drops them → later guard fails (e.g. `pending_required` instead of reaching the real check). In tests, set them with `forceFill([...])->save()`.
- **Enum backing values:** never pass `SomeEnum::Draft` (the enum object) where a string column is expected — use `SomeEnum::Draft->value`. Error: `"draft" is not a valid backing value` happens the other way (passing the enum *name*).
- **Model casts:** if a column isn't cast to an enum, `$model->status` is a string — comparing `$unit->status !== UnitStatus::Reserved` fails. Coerce: `$unit->status instanceof UnitStatus ? $unit->status->value : (string) $unit->status`.
- **Translated validation messages:** `ValidationException::withMessages([$field => __('...')])` stores the *translated* string. In tests, assert on the field key (`$e->errors()` has `unit_id`), not on English message text.

## Workflow rules

1. When adding a new `create()` path (service or Filament action), grep the target table's migration and list every NOT NULL column without `->default(...)` / `->nullable()`.
2. Prefer putting creation logic in a **Service** (e.g. `PropertyAcquisitionService::convertToProject()`), not in a Filament page — services are directly unit-testable without Livewire.
3. Add a feature test for every new creation flow; several 1364 bugs above were only found because a test exercised the path.
4. Never pass non-existent columns (e.g. `unit_price`, `currency`, `sort_order` on `budget_items`) — check `$fillable` AND the migration; mismatches mean silent data loss.
