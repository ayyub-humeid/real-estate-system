<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\RequiresExportPermission;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Shared behaviour for the exports a company runs on its own lists:
 * a readable file name and a completion notice that reports skipped rows.
 */
abstract class CompanyDataExporter extends Exporter
{
    use RequiresExportPermission;

    /** Translation file holding this export's file_name and column labels, e.g. "unit_export". */
    abstract protected static function translationFile(): string;

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('export.completed', ['count' => number_format($export->successful_rows)]);

        if ($failed = $export->getFailedRowsCount()) {
            $body .= ' '.__('export.failed_rows', ['count' => number_format($failed)]);
        }

        return $body;
    }

    public function getFileName(Export $export): string
    {
        return __(static::translationFile().'.file_name', [
            'date' => now()->format('Y-m-d'),
            'id' => $export->getKey(),
        ]);
    }

    protected static function label(string $column): string
    {
        return __(static::translationFile().'.columns.'.$column);
    }

    /** Money must stay a number in Excel; see HasNumericExportColumns. */
    protected static function number(mixed $state): ?float
    {
        return $state === null || $state === '' ? null : (float) $state;
    }
}
