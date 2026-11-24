<?php

namespace App\Filament\Exports\Contracts;

/**
 * Marks an exporter whose money and rate columns must reach Excel as real numbers.
 *
 * Filament builds the XLSX by reading back an intermediate CSV, so every cell
 * arrives as a string and openspout writes it as text. Excel then ignores those
 * cells in SUM(), which silently breaks any financial total.
 *
 * Only the columns named here are cast. Casting every numeric-looking value would
 * corrupt identifiers such as a cheque number of "000912".
 */
interface HasNumericExportColumns
{
    /** @return array<int, string> Export column names holding numeric values. */
    public static function numericColumns(): array;
}
