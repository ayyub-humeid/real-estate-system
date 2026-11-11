<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Enums\ContractInstallmentKind;
use App\Models\{ContractInstallment, Payment};
use App\Services\ContractFinancialService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getModelLabel(): string
    {
        return __('Payment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payments');
    }

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('contract_finance.navigation.payments');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view_contract_financials') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['party', 'contractPaymentSchedule'])->withCount('contractAllocations'))
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')->label(__('contract_finance.fields.payment_date'))->date()->sortable(),
                Tables\Columns\TextColumn::make('direction')->label(__('contract_finance.fields.direction'))->badge()->formatStateUsing(fn (string $state): string => $state === 'incoming' ? __('contract_finance.fields.incoming') : __('contract_finance.fields.outgoing'))->color(fn (string $state): string => $state === 'incoming' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('amount')->label(__('contract_finance.fields.amount'))->money(fn (Payment $record): string => $record->currency),
                Tables\Columns\TextColumn::make('party.name')->label(__('contract_finance.fields.customer_party')),
                Tables\Columns\TextColumn::make('reference_number')->label(__('contract_finance.fields.reference_number'))->placeholder('—')->copyable(),
                Tables\Columns\TextColumn::make('contract_allocations_count')->label(__('contract_finance.fields.allocations'))->badge(),
            ])
            ->defaultSort('payment_date', 'desc')
            ->emptyStateHeading(__('contract_finance.empty.payments_heading'))
            ->emptyStateDescription(__('contract_finance.empty.payments_description'))
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('contract_finance.actions.details'))
                    ->tooltip(__('contract_finance.action_help.payment_details'))
                    ->infolist(function (Infolists\Infolist $infolist): Infolists\Infolist {
                        /** @var Payment $payment */
                        $payment = $infolist->getRecord();
                        $allocations = $payment->contractAllocations()
                            ->with('installment')
                            ->orderBy('id')
                            ->get()
                            ->map(fn ($allocation): array => [
                                'installment_label' => '#'.$allocation->installment?->sequence,
                                'installment_kind' => $allocation->installment?->kind instanceof ContractInstallmentKind
                                    ? $allocation->installment->kind->value
                                    : (string) ($allocation->installment?->kind ?? ''),
                                'installment_due_date' => $allocation->installment?->due_date?->toDateString(),
                                'allocated_amount' => $allocation->allocated_amount,
                                'currency' => $allocation->currency,
                            ])
                            ->all();

                        return $infolist->schema([
                            Infolists\Components\Section::make(__('contract_finance.fields.payment'))->schema([
                                Infolists\Components\TextEntry::make('payment_date')->label(__('contract_finance.fields.payment_date'))->date(),
                                Infolists\Components\TextEntry::make('direction')->label(__('contract_finance.fields.direction'))->badge()
                                    ->formatStateUsing(fn (string $state): string => $state === 'incoming' ? __('contract_finance.fields.incoming') : __('contract_finance.fields.outgoing'))
                                    ->color(fn (string $state): string => $state === 'incoming' ? 'success' : 'danger'),
                                Infolists\Components\TextEntry::make('amount')->label(__('contract_finance.fields.amount'))->money($payment->currency),
                                Infolists\Components\TextEntry::make('party.name')->label(__('contract_finance.fields.customer_party'))->placeholder('—'),
                                Infolists\Components\TextEntry::make('payment_method')->label(__('contract_finance.fields.payment_method'))
                                    ->formatStateUsing(fn (?string $state): string => $state ? __('contract_finance.payment_methods.'.$state) : '—'),
                                Infolists\Components\TextEntry::make('reference_number')->label(__('contract_finance.fields.reference_number'))->placeholder('—'),
                            ])->columns(3),
                            Infolists\Components\RepeatableEntry::make('allocation_details')
                                ->label(__('contract_finance.fields.allocations'))
                                ->state($allocations)
                                ->schema([
                                    Infolists\Components\TextEntry::make('installment_label')->label(__('contract_finance.fields.installment')),
                                    Infolists\Components\TextEntry::make('installment_kind')->label(__('contract_finance.fields.kind'))
                                        ->formatStateUsing(fn (string $state): string => ContractInstallmentKind::tryFrom($state)?->getLabel() ?? $state),
                                    Infolists\Components\TextEntry::make('installment_due_date')->label(__('contract_finance.fields.due_date'))->date()->placeholder('—'),
                                    Infolists\Components\TextEntry::make('allocated_amount')->label(__('contract_finance.fields.allocation_amount'))
                                        ->suffix(' '.$payment->currency),
                                ])->columns(4),
                        ]);
                    }),
                Tables\Actions\Action::make('reversePayment')
                    ->label(__('contract_finance.actions.reverse_payment'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->tooltip(__('contract_finance.action_help.reverse_payment'))
                    ->visible(fn (Payment $record): bool => $record->payment_scope === 'contract_receipt' && $record->status === 'completed' && auth()->user()->can('reverse_contract_payment'))
                    ->requiresConfirmation()
                    ->modalHeading(__('contract_finance.actions.reverse_payment'))
                    ->form(fn (Payment $record): array => [
                        Forms\Components\TextInput::make('amount')->label(__('contract_finance.fields.amount'))->numeric()->minValue(0.0001)->required(),
                        Forms\Components\DatePicker::make('payment_date')->label(__('contract_finance.fields.payment_date'))->default(today())->native(false)->required(),
                        Forms\Components\Select::make('payment_method')->label(__('contract_finance.fields.payment_method'))->options(collect(['cash', 'bank_transfer', 'cheque', 'card', 'other'])->mapWithKeys(fn (string $method): array => [$method => __('contract_finance.payment_methods.'.$method)])->all())->required(),
                        Forms\Components\TextInput::make('reference_number')->label(__('contract_finance.fields.reference_number')),
                        Forms\Components\Hidden::make('idempotency_key')->default(fn (): string => Str::uuid()->toString()),
                        Forms\Components\Repeater::make('allocations')->label(__('contract_finance.fields.allocations'))->helperText(__('contract_finance.guidance.reverse_allocations'))->schema([
                            Forms\Components\Select::make('installment_id')->label(__('contract_finance.fields.installment'))->options(fn (): array => $record->contractAllocations()->with('installment')->get()->mapWithKeys(fn ($allocation) => [$allocation->contract_installment_id => '#'.$allocation->installment?->sequence.' — '.$allocation->allocated_amount.' '.$allocation->currency])->all())->required(),
                            Forms\Components\TextInput::make('allocated_amount')->label(__('contract_finance.fields.allocation_amount'))->numeric()->minValue(0.0001)->required(),
                            Forms\Components\Hidden::make('currency')->default($record->currency),
                        ])->minItems(1)->columns(2)->addActionLabel(__('contract_finance.actions.allocate_installment')),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        try {
                            app(ContractFinancialService::class)->reversePayment(auth()->user(), $record, $data);
                            Notification::make()->success()->title(__('contract_finance.notifications.payment_reversed'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title(__('contract_finance.errors.unable_to_update'))->body(collect($exception->errors())->flatten()->first())->persistent()->send();
                        } catch (AuthorizationException) {
                            Notification::make()->danger()->title(__('contract_finance.errors.unauthorized_title'))->body(__('contract_finance.errors.not_authorized'))->persistent()->send();
                        } catch (\Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title(__('contract_finance.errors.unexpected_error'))->body(__('contract_finance.errors.try_again_or_support'))->persistent()->send();
                        }
                    }),
            ]);
    }
}
