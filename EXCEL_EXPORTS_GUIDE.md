# Excel Exports Architecture

## How Excel Exports Work

The system uses **Filament's native export pipeline** (openspout) for high-performance, queued Excel generation. No separate library is needed.

### The Pattern

Every export follows this structure:

```
1. Create an Exporter class       → app/Filament/Exports/YourExporter.php
2. Define columns & formatting    → formatStateUsing() transforms values
3. Add translations               → lang/en/your_export.php + lang/ar/
4. Add action to resource         → PlanResource::table()->headerActions([ExportAction::make()])
5. Queue worker processes it      → php artisan queue:work
6. User gets notified             → Bell icon with download link
```

---

## Example: Payment Export

**File structure:**
```
app/Filament/Exports/
  ├── PaymentExporter.php          (main class)
  └── Contracts/
      └── HasNumericExportColumns.php  (interface for money columns)

app/Jobs/
  └── CreateNumericAwareXlsxFile.php   (fixes Filament's text-cell bug)

app/Providers/
  └── AppServiceProvider.php        (binds custom XLSX writer)

lang/en/payment_export.php
lang/ar/payment_export.php
```

---

## Key Design Decisions

### 1. **Amounts Are Numbers, Not Formatted Text**

❌ **Wrong** (breaks Excel totals):
```php
'amount' => '$1,200.50'  // Text → SUM() = $0
```

✅ **Right** (summable):
```php
// Two columns:
'amount' => 1200.50      // Number
'currency' => 'USD'      // Label
```

**Why:** Excel's `SUM()` function ignores text cells. If you format money into one cell, it becomes text and all totals will be zero — a silent financial error.

**Implementation:**
- Exporter returns raw numbers: `fn ($state): ?float => (float) $state`
- Currency stays in a separate column
- `CreateNumericAwareXlsxFile` casts only declared columns to real numbers
- Identifiers like `reference_number` ("000912") stay as text so leading zeros are preserved

### 2. **Selective Column Visibility**

```php
ExportColumn::make('amount')
    ->enabledByDefault(true),    // Always shown
    
ExportColumn::make('id')
    ->enabledByDefault(false),   // Hidden by default; user can toggle
```

The user opens the export modal, sees 14 essential columns, and can optionally show 6 more (IDs, timestamps, audit columns).

### 3. **Filters Are Applied**

When the user clicks "Export", the **current filters are included**. If they filtered by `payment_date > Nov 1`, the Excel file contains only those rows.

```php
// In the exporter:
public static function modifyQuery(Builder $query): Builder
{
    return $query->with(['contract', 'party', 'recordedBy']);
}

// Filters automatically apply because Filament passes the already-filtered Builder
```

### 4. **Large Exports Are Queued**

Exports are chunked (500 rows at a time) and run in the background:

```php
protected int $chunkSize = 500;

// If exporting 10,000 payments:
// - Queue job runs in background
// - RAM stays flat (chunks in/out)
// - User gets a "started" notification immediately
// - Bell icon updates when file is ready
```

**You must run a queue worker:**
```bash
php artisan queue:work
```

Without it, exports sit in the database queue forever and users never get notified.

---

## How to Add an Export

### Step 1: Create the Exporter

```php
namespace App\Filament\Exports;

use App\Models\YourModel;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;

class YourExporter extends Exporter
{
    protected static ?string $model = YourModel::class;
    protected int $chunkSize = 500;

    public static function modifyQuery(Builder $query): Builder
    {
        // Eager load relationships to avoid N+1 queries
        return $query->with(['relation1', 'relation2']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('column_name')
                ->label(__('translation_key'))
                ->formatStateUsing(fn ($state): ?string => /* transform */),
            // ... more columns
        ];
    }

    public function getFileName(mixed $export): string
    {
        return 'your-export-'.now()->format('Y-m-d').'.xlsx';
    }
}
```

### Step 2: Add Translations

**lang/en/your_export.php:**
```php
return [
    'action' => 'Export to Excel',
    'file_name' => 'your-export-:date',
    'columns' => [
        'column_name' => 'Column Label',
    ],
];
```

**lang/ar/your_export.php:**
```php
return [
    'action' => 'تصدير إلى Excel',
    'file_name' => 'your-export-:date',
    'columns' => [
        'column_name' => 'تسمية العمود',
    ],
];
```

### Step 3: Add Action to Resource

```php
namespace App\Filament\Resources;

use App\Filament\Exports\YourExporter;
use Filament\Actions\ExportAction;

class YourResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([/* ... */])
            ->headerActions([
                ExportAction::make()
                    ->exporter(YourExporter::class),
            ]);
    }
}
```

### Step 4: (Optional) Handle Numeric Columns

If your export has money columns that must be summable:

```php
use App\Filament\Exports\Contracts\HasNumericExportColumns;

class YourExporter extends Exporter implements HasNumericExportColumns
{
    public static function numericColumns(): array
    {
        return ['amount', 'exchange_rate'];  // Only these → numbers
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('amount')
                ->formatStateUsing(fn ($state): ?float => (float) $state),
            // reference_number stays text — leading zeros are safe
        ];
    }
}
```

The system automatically casts these to real numbers in the XLSX file.

---

## The Plans Export: What You See

**File:** `PlanExporter.php`

**Columns (default on):**
- Plan name
- Monthly price
- Annual price
- Trial days
- Status (Active/Inactive)

**Columns (default off, user can toggle):**
- All feature flags: Maintenance Tracking, Rental Requests, Expense Tracking
- All limits: Max projects, properties, units, employees, users
- Advanced flags: Construction Tracking, Due Diligence, Advanced Reports, Export Data

**Sorted by:** Monthly price (Starter → Growth → Business)

**Output:**
```
| Plan      | Monthly | Annual | Trial | Maintenance | Projects | Construction |
|-----------|---------|--------|-------|-------------|----------|--------------|
| Starter   | 99      | 990    | 30    | Yes         | 2        | No           |
| Growth    | 249     | 2490   | 30    | Yes         | 10       | Yes          |
| Business  | 599     | 5990   | 30    | Yes         | Unlimited| Yes          |
```

---

## Common Mistakes

| Mistake | Fix |
|---------|-----|
| Money column shows as text | Use `HasNumericExportColumns` + `CreateNumericAwareXlsxFile` binding |
| `SUM()` returns 0 in Excel | Separate amounts from currency into two columns |
| Cheque number "000912" becomes "912" | Don't cast non-money columns to `(float)` |
| Export notification never arrives | Start queue worker: `php artisan queue:work` |
| Filter is not applied to export | The Builder passed to `modifyQuery()` already has filters; don't override it |
| "Undefined translation key" | Add the key to both `lang/en/` and `lang/ar/` |

---

## Testing an Export

```php
use App\Filament\Exports\YourExporter;
use Filament\Actions\Exports\Models\Export;

$export = Export::create([
    'exporter' => YourExporter::class,
    'user_id' => auth()->id(),
    'total_rows' => 1,
]);

$exporter = new YourExporter(
    $export,
    array_fill_keys(['column1', 'column2'], true), // enabled columns
    []  // filters
);

$row = $exporter($model);  // Returns array for one row
```

---

## Checklist Before Shipping

- [ ] Exporter has `modifyQuery()` with eager loads (avoid N+1)
- [ ] All columns have translation keys
- [ ] Money columns use `HasNumericExportColumns` interface
- [ ] `AppServiceProvider` binds `CreateNumericAwareXlsxFile` if needed
- [ ] Action added to resource as `headerActions`
- [ ] Tests pass on both MySQL and PostgreSQL
- [ ] `php artisan queue:work` is running in development
- [ ] Tried exporting a small dataset to Excel and opening it
- [ ] Tested on both Arabic and English locales
