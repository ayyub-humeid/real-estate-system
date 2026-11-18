---
name: status-enums-not-strings
description: Use backed PHP enums for statuses, types and roles instead of raw strings or model constants. Use when writing queries, widgets, policies, services, forms, or tables that filter on or display a status/type/role value.
---

# Status/type/role values are enums

Raw strings (`where('status', 'committed')`) break silently when a value is renamed, and model `STATUS_*` constants are not translatable or reusable in Filament. Use a backed enum in `app/Enums` (existing pattern: `ContractStatus`, `UnitStatus`, `Currency`).

## Pattern
```php
enum FinancialCommitmentStatus: string implements HasLabel
{
    use \App\Enums\Concerns\HasTranslatedLabel; // getLabel(), labelFor(), options(), values()
    case Draft = 'draft';
    case Committed = 'committed';
}
```
- Labels live in `lang/{en,ar}/enums.php` under `enums.<enum_snake_name>.<value>` (the trait derives the key from the class name).
- Add `HasColor` + `getColor()` for badge colors; add helpers like `isClosed()` / `activeValues()` for groups used in dashboards.

## Usage rules
- Queries: `->where('status', Enum::Case->value)`, `->whereIn('status', Enum::activeValues())`.
- Forms: `->options(Enum::options())`. Tables: `->formatStateUsing(fn ($s) => Enum::labelFor($s))->color(fn ($s) => Enum::tryFrom((string) $s)?->getColor() ?? 'gray')`.
- Comparisons: `$record->status === Enum::Approved->value`.
- Use `tryFrom()` for DB values — never `from()` on data that might contain legacy values.

## Do NOT (learned the hard way)
- Do not add `STATUS_*` constants to models as the fix; they were tried and rejected.
- Do not add an enum **cast** to an existing model column without grepping every `=== 'x'`, `->status`, Blade and test usage: a cast turns the attribute into an object and breaks them, and unknown legacy values throw. Keep the column a string and use `->value` unless a dedicated migration of all usages is requested.
- Do not rename enum values; they are DB values. Add cases instead.
- Do not leave a new enum without translations: every case needs en + ar (test it by looping `cases()`).
