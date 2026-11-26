<?php

namespace App\Filament\Support;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Services\ExpenseService;
use Filament\Actions\Action as PageAction;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Actions\Action as TableAction;

/** The "mark as paid" and "cancel" buttons, shared by the expense list and the expense page. */
class ExpenseActions
{
    use RunsWorkflowAction;

    /** @param class-string<TableAction|PageAction> $actionClass */
    public static function markPaid(string $actionClass = TableAction::class): MountableAction
    {
        return $actionClass::make('markPaid')
            ->label(__('expense_domain.actions.mark_paid'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Expense $record): bool => $record->canMoveTo(ExpenseStatus::Paid) && auth()->user()->can('pay', $record))
            ->modalHeading(__('expense_domain.actions.mark_paid'))
            ->modalDescription(__('expense_domain.actions.mark_paid_help'))
            ->form([
                Forms\Components\Select::make('payment_method')
                    ->label(__('expense_domain.fields.payment_method'))
                    ->options(PaymentMethod::options())
                    ->required()
                    ->native(false),
                Forms\Components\DatePicker::make('paid_at')
                    ->label(__('expense_domain.fields.paid_at'))
                    ->default(now()->toDateString())
                    ->maxDate(now()->endOfDay())
                    ->required(),
                Forms\Components\TextInput::make('reference_number')
                    ->label(__('expense_domain.fields.reference_number'))
                    ->default(fn (Expense $record): ?string => $record->reference_number)
                    ->maxLength(100),
            ])
            ->action(function (Expense $record, array $data, $livewire): void {
                (new self)->runWorkflowAction(
                    fn () => app(ExpenseService::class)->markPaid(auth()->user(), $record, $data),
                    __('expense_domain.notifications.paid'),
                    onSuccess: fn () => self::refresh($record, $livewire),
                    successBody: __('expense_domain.notifications.paid_body'),
                );
            });
    }

    /** @param class-string<TableAction|PageAction> $actionClass */
    public static function cancel(string $actionClass = TableAction::class): MountableAction
    {
        return $actionClass::make('cancelExpense')
            ->label(__('expense_domain.actions.cancel'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Expense $record): bool => $record->canMoveTo(ExpenseStatus::Cancelled) && auth()->user()->can('cancel', $record))
            ->modalHeading(__('expense_domain.actions.cancel'))
            ->modalDescription(fn (Expense $record): string => $record->status === ExpenseStatus::Paid->value
                ? __('expense_domain.actions.cancel_paid_help')
                : __('expense_domain.actions.cancel_pending_help'))
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label(__('expense_domain.fields.cancellation_reason'))
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(function (Expense $record, array $data, $livewire): void {
                (new self)->runWorkflowAction(
                    fn () => app(ExpenseService::class)->cancel(auth()->user(), $record, $data['reason']),
                    __('expense_domain.notifications.cancelled'),
                    onSuccess: fn () => self::refresh($record, $livewire),
                    successBody: __('expense_domain.notifications.cancelled_body'),
                );
            });
    }

    private static function refresh(Expense $record, object $livewire): void
    {
        $record->refresh();

        if ($livewire instanceof ViewRecord) {
            $livewire->refreshFormData(['status', 'payment_method', 'paid_at', 'reference_number', 'cancellation_reason']);
        }
    }
}
