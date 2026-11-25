<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\RequiresExportPermission;
use App\Filament\Exports\Contracts\HasNumericExportColumns;
use App\Models\Payment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reconciliation-ready payment export.
 *
 * Amounts are written as raw numbers with the currency in its own column so a
 * finance team can SUM() them in Excel. Formatting money into the cell text
 * would turn every amount into a string and break totals.
 */
class PaymentExporter extends Exporter implements HasNumericExportColumns
{
    use RequiresExportPermission;

    protected static ?string $model = Payment::class;

    protected static function exportPermission(): ?string
    {
        return 'export_payment';
    }

    /** Rows per queued chunk. Keeps memory flat on large exports. */
    protected int $chunkSize = 500;

    /**
     * Money and rate columns only. Identifiers such as reference_number stay text
     * so a cheque number like "000912" keeps its leading zeros.
     */
    public static function numericColumns(): array
    {
        return ['amount', 'project_amount', 'exchange_rate_to_project'];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'contract:id,contract_number,contract_type,unit_id,planned_unit_id,property_id,project_id',
            'contract.unit:id,unit_number',
            'contract.plannedUnit:id,code',
            'contract.property:id,name',
            'contract.project:id,name',
            'project:id,name,currency',
            'party:id,name',
            'recordedBy:id,name',
            'reversalOf:id,reference_number',
        ]);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('payment_date')
                ->label(__('payment_export.columns.payment_date'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d')),

            ExportColumn::make('reference_number')
                ->label(__('payment_export.columns.reference')),

            ExportColumn::make('direction')
                ->label(__('payment_export.columns.direction'))
                ->formatStateUsing(fn (?string $state): string => __('payment_directory.directions.'.($state ?: 'outgoing'))),

            ExportColumn::make('payment_scope')
                ->label(__('payment_export.columns.scope'))
                ->formatStateUsing(fn (?string $state): string => match ($state) {
                    'contract_receipt' => __('payment_directory.purposes.contract_receipt'),
                    'contract_refund'  => __('payment_directory.purposes.reversal'),
                    'project_cost'     => __('payment_directory.purposes.project_cost'),
                    default            => __('payment_directory.purposes.other'),
                }),

            ExportColumn::make('status')
                ->label(__('payment_export.columns.status'))
                ->formatStateUsing(fn (?string $state): string => __('payment_directory.statuses.'.($state ?: 'pending'))),

            ExportColumn::make('party.name')
                ->label(__('payment_export.columns.party')),

            ExportColumn::make('contract.contract_number')
                ->label(__('payment_export.columns.contract_number')),

            ExportColumn::make('contract_subject')
                ->label(__('payment_export.columns.contract_subject'))
                ->state(fn (Payment $record): ?string => $record->contract?->displayName()),

            ExportColumn::make('project.name')
                ->label(__('payment_export.columns.project')),

            // Numeric so Excel can total the column.
            ExportColumn::make('amount')
                ->label(__('payment_export.columns.amount'))
                ->formatStateUsing(fn ($state): ?float => $state === null ? null : (float) $state),

            ExportColumn::make('currency')
                ->label(__('payment_export.columns.currency')),

            ExportColumn::make('payment_method')
                ->label(__('payment_export.columns.payment_method'))
                ->formatStateUsing(fn (?string $state): ?string => $state ? __('contract_finance.payment_methods.'.$state) : null),

            ExportColumn::make('completed_at')
                ->label(__('payment_export.columns.completed_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d H:i')),

            ExportColumn::make('recordedBy.name')
                ->label(__('payment_export.columns.recorded_by')),

            // Multi-currency audit trail: only populated on project-cost payments.
            ExportColumn::make('project_amount')
                ->label(__('payment_export.columns.project_amount'))
                ->formatStateUsing(fn ($state): ?float => $state === null ? null : (float) $state)
                ->enabledByDefault(false),

            ExportColumn::make('exchange_rate_to_project')
                ->label(__('payment_export.columns.exchange_rate'))
                ->formatStateUsing(fn ($state): ?float => $state === null ? null : (float) $state)
                ->enabledByDefault(false),

            ExportColumn::make('reversalOf.reference_number')
                ->label(__('payment_export.columns.reversal_of'))
                ->enabledByDefault(false),

            ExportColumn::make('void_reason')
                ->label(__('payment_export.columns.void_reason'))
                ->enabledByDefault(false),

            ExportColumn::make('id')
                ->label(__('payment_export.columns.record_id'))
                ->enabledByDefault(false),

            ExportColumn::make('created_at')
                ->label(__('payment_export.columns.created_at'))
                ->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d H:i'))
                ->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('payment_export.notifications.completed', [
            'count' => number_format($export->successful_rows),
        ]);

        if ($failed = $export->getFailedRowsCount()) {
            $body .= ' '.__('payment_export.notifications.failed_rows', [
                'count' => number_format($failed),
            ]);
        }

        return $body;
    }

    public function getFileName(Export $export): string
    {
        return __('payment_export.file_name', [
            'date' => now()->format('Y-m-d'),
            'id'   => $export->getKey(),
        ]);
    }
}
