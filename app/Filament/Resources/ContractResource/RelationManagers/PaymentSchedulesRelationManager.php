<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Enums\{ContractInstallmentKind, ContractPaymentScheduleStatus, ContractVersionStatus};
use App\Models\{ContractInstallment, ContractPaymentSchedule, ContractVersion, User};
use App\Services\ContractFinancialService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentSchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentSchedules';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('contract_finance.navigation.payment_schedule');
    }

    public static function getModelLabel(): string
    {
        return __('contract_finance.fields.schedule');
    }

    public static function getPluralModelLabel(): string
    {
        return __('contract_finance.navigation.payment_schedule');
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
            ->modifyQueryUsing(fn ($query) => $query->with(['contractVersion', 'assignedTo'])->withCount('installments'))
            ->heading(__('contract_finance.navigation.payment_schedule'))
            ->description(function (): string {
                $summary = app(ContractFinancialService::class)->summary($this->getOwnerRecord());
                return __('contract_finance.fields.total_amount').': '.$summary['scheduled'].' · '.__('contract_finance.fields.received').': '.$summary['received'].' · '.__('contract_finance.fields.remaining').': '.$summary['outstanding'].' · '.__('contract_finance.fields.overdue').': '.$summary['overdue'];
            })
            ->emptyStateHeading(__('contract_finance.empty.schedules_heading'))
            ->emptyStateDescription(__('contract_finance.empty.schedules_description'))
            ->columns([
                Tables\Columns\TextColumn::make('revision_number')->label(__('contract_finance.fields.revision'))->prefix('V')->badge(),
                Tables\Columns\TextColumn::make('status')->label(__('contract_finance.fields.status'))->badge()
                    ->formatStateUsing(fn ($state): string => ($state instanceof ContractPaymentScheduleStatus ? $state : ContractPaymentScheduleStatus::tryFrom((string) $state))?->getLabel() ?? (string) $state)
                    ->color(fn ($state): ?string => ($state instanceof ContractPaymentScheduleStatus ? $state : ContractPaymentScheduleStatus::tryFrom((string) $state))?->color()),
                Tables\Columns\TextColumn::make('total_amount')->label(__('contract_finance.fields.total_amount'))->money(fn (ContractPaymentSchedule $record): string => $record->currency),
                Tables\Columns\TextColumn::make('installments_count')->label(__('contract_finance.navigation.installments'))->badge(),
                Tables\Columns\TextColumn::make('assignedTo.name')->label(__('contract_finance.fields.assigned_to'))->placeholder('—'),
                Tables\Columns\TextColumn::make('issued_at')->label(__('contract_finance.fields.issued_at'))->dateTime()->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createSchedule')
                    ->label(__('contract_finance.actions.create_schedule'))
                    ->icon('heroicon-o-calendar-days')
                    ->color('primary')
                    ->tooltip(__('contract_finance.action_help.create_schedule'))
                    ->visible(fn (): bool => auth()->user()->can('create_contract_payment_schedule'))
                    ->modalHeading(__('contract_finance.actions.create_schedule'))
                    ->modalDescription(__('contract_finance.descriptions.generation_preview'))
                    ->form($this->createScheduleForm())
                    ->action(function (array $data): void {
                        $version = ContractVersion::withoutGlobalScopes()->findOrFail($data['contract_version_id']);
                        $this->run(function () use ($version, $data): void {
                            $service = app(ContractFinancialService::class);
                            if (($data['generation_mode'] ?? null) === 'manual') {
                                $service->createDraft(auth()->user(), $this->getOwnerRecord(), $version, $data);
                            } else {
                                $service->createGeneratedDraft(auth()->user(), $this->getOwnerRecord(), $version, $data);
                            }
                        }, __('contract_finance.notifications.schedule_created'));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('contract_finance.actions.details'))
                    ->tooltip(__('contract_finance.action_help.details'))
                    ->infolist(function (Infolists\Infolist $infolist): Infolists\Infolist {
                        /** @var ContractPaymentSchedule $schedule */
                        $schedule = $infolist->getRecord();
                        $currency = $schedule->currency;
                        $financialService = app(ContractFinancialService::class);
                        $financialSummary = $financialService->scheduleFinancialSummary($schedule);
                        $financialDetails = $financialService->installmentFinancialDetails($schedule);

                        return $infolist->schema([
                        Infolists\Components\Section::make(__('contract_finance.fields.schedule'))->schema([
                            Infolists\Components\TextEntry::make('revision_number')->label(__('contract_finance.fields.revision'))->prefix('V'),
                            Infolists\Components\TextEntry::make('status')->label(__('contract_finance.fields.status'))->badge()->formatStateUsing(fn ($state): string => ($state instanceof ContractPaymentScheduleStatus ? $state : ContractPaymentScheduleStatus::tryFrom((string) $state))?->getLabel() ?? (string) $state),
                            Infolists\Components\TextEntry::make('total_amount')->label(__('contract_finance.fields.total_amount'))->money($currency),
                            Infolists\Components\TextEntry::make('assignedTo.name')->label(__('contract_finance.fields.assigned_to'))->placeholder('—'),
                        ])->columns(4),
                        Infolists\Components\Section::make(__('contract_finance.fields.collection_summary'))->schema([
                            Infolists\Components\TextEntry::make('financial_summary.scheduled')
                                ->label(__('contract_finance.fields.scheduled_amount'))
                                ->state($financialSummary['scheduled'])
                                ->suffix(' '.$currency),
                            Infolists\Components\TextEntry::make('financial_summary.paid')
                                ->label(__('contract_finance.fields.paid_amount'))
                                ->state($financialSummary['paid'])
                                ->suffix(' '.$currency)
                                ->color('success'),
                            Infolists\Components\TextEntry::make('financial_summary.outstanding')
                                ->label(__('contract_finance.fields.remaining'))
                                ->state($financialSummary['outstanding'])
                                ->suffix(' '.$currency)
                                ->color('warning'),
                            Infolists\Components\TextEntry::make('financial_summary.paid_count')
                                ->label(__('contract_finance.fields.settled_installments'))
                                ->state($financialSummary['paid_count'].' / '.$financialSummary['installment_count']),
                        ])->columns(4),
                        Infolists\Components\RepeatableEntry::make('financial_installments')
                            ->label(__('contract_finance.navigation.installments'))
                            ->state($financialDetails)
                            ->schema([
                            Infolists\Components\TextEntry::make('sequence')->label(__('contract_finance.fields.sequence')),
                            Infolists\Components\TextEntry::make('kind')->label(__('contract_finance.fields.kind'))->formatStateUsing(fn ($state): string => ($state instanceof ContractInstallmentKind ? $state : ContractInstallmentKind::tryFrom((string) $state))?->getLabel() ?? (string) $state),
                            Infolists\Components\TextEntry::make('amount')->label(__('contract_finance.fields.amount'))->suffix(' '.$currency),
                            Infolists\Components\TextEntry::make('paid')->label(__('contract_finance.fields.paid_amount'))->suffix(' '.$currency)->color('success'),
                            Infolists\Components\TextEntry::make('outstanding')->label(__('contract_finance.fields.remaining'))->suffix(' '.$currency)->color('warning'),
                            Infolists\Components\TextEntry::make('due_date')->label(__('contract_finance.fields.due_date'))->date(),
                            Infolists\Components\TextEntry::make('payment_status')->label(__('contract_finance.fields.payment_status'))->badge()
                                ->formatStateUsing(fn (string $state): string => __('contract_finance.payment_statuses.'.$state))
                                ->color(fn (string $state): string => match ($state) {
                                    'paid' => 'success', 'partially_paid' => 'warning', 'unpaid' => 'gray', 'cancelled' => 'danger', default => 'info',
                                }),
                        ])->columns(4),
                    ]);
                    }),
                Tables\Actions\Action::make('editDraftInstallments')
                    ->label(__('contract_finance.actions.edit_installments'))
                    ->icon('heroicon-o-pencil-square')
                    ->tooltip(__('contract_finance.action_help.edit_installments'))
                    ->visible(fn (ContractPaymentSchedule $record): bool => $record->status === ContractPaymentScheduleStatus::Draft && auth()->user()->can('update_contract_payment_schedule'))
                    ->modalHeading(__('contract_finance.actions.edit_installments'))
                    ->modalDescription(__('contract_finance.descriptions.draft_editable'))
                    ->fillForm(fn (ContractPaymentSchedule $record): array => [
                        'installments' => $record->installments()->get()->map(fn (ContractInstallment $item): array => [
                            'kind' => $item->kind instanceof ContractInstallmentKind ? $item->kind->value : $item->kind,
                            'amount' => $item->amount, 'due_date' => $item->due_date?->toDateString(),
                        ])->all(),
                    ])
                    ->form([$this->installmentRepeater()])
                    ->action(fn (ContractPaymentSchedule $record, array $data) => $this->run(fn () => app(ContractFinancialService::class)->replaceDraftInstallments(auth()->user(), $record, $data['installments'] ?? []), __('contract_finance.notifications.draft_installments_saved'))),
                Tables\Actions\Action::make('submitSchedule')
                    ->label(__('contract_finance.actions.submit_schedule'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->tooltip(__('contract_finance.action_help.submit_schedule'))
                    ->requiresConfirmation()
                    ->visible(fn (ContractPaymentSchedule $record): bool => $record->status === ContractPaymentScheduleStatus::Draft && auth()->user()->can('submit_contract_payment_schedule'))
                    ->action(fn (ContractPaymentSchedule $record) => $this->run(fn () => app(ContractFinancialService::class)->submit(auth()->user(), $record), __('contract_finance.notifications.schedule_submitted'))),
                Tables\Actions\Action::make('issueSchedule')
                    ->label(__('contract_finance.actions.issue_schedule'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->tooltip(__('contract_finance.action_help.issue_schedule'))
                    ->requiresConfirmation()
                    ->visible(fn (ContractPaymentSchedule $record): bool => $record->status === ContractPaymentScheduleStatus::PendingApproval && auth()->user()->can('approve_contract_payment_schedule'))
                    ->action(fn (ContractPaymentSchedule $record) => $this->run(fn () => app(ContractFinancialService::class)->issue(auth()->user(), $record), __('contract_finance.notifications.schedule_issued'))),
                Tables\Actions\Action::make('recordPayment')
                    ->label(__('contract_finance.actions.record_payment'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->tooltip(__('contract_finance.action_help.record_payment'))
                    ->visible(fn (ContractPaymentSchedule $record): bool => $record->status === ContractPaymentScheduleStatus::Issued
                        && auth()->user()->can('record_contract_payment')
                        && auth()->user()->can('allocate_contract_payment'))
                    ->modalHeading(__('contract_finance.actions.record_payment'))
                    ->form(fn (ContractPaymentSchedule $record): array => $this->paymentForm($record))
                    ->action(fn (ContractPaymentSchedule $record, array $data) => $this->run(
                        fn () => app(ContractFinancialService::class)->recordReceipt(auth()->user(), $record, $this->prepareReceiptData($record, $data)),
                        __('contract_finance.notifications.payment_recorded'),
                    )),
                Tables\Actions\Action::make('reschedule')
                    ->label(__('contract_finance.actions.reschedule'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->tooltip(__('contract_finance.action_help.reschedule'))
                    ->visible(fn (ContractPaymentSchedule $record): bool => $record->status === ContractPaymentScheduleStatus::Issued && auth()->user()->can('reschedule_contract_payment_schedule'))
                    ->modalHeading(__('contract_finance.actions.reschedule'))
                    ->form([
                        Forms\Components\Textarea::make('reason')->label(__('contract_finance.fields.reason'))->required(),
                        Forms\Components\Select::make('assigned_to')->label(__('contract_finance.fields.assigned_to'))->options(fn () => User::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->orderBy('name')->pluck('name', 'id'))->searchable(),
                        $this->installmentRepeater(),
                    ])
                    ->action(fn (ContractPaymentSchedule $record, array $data) => $this->run(fn () => app(ContractFinancialService::class)->reschedule(auth()->user(), $record, $data), __('contract_finance.notifications.schedule_rescheduled'))),
                Tables\Actions\Action::make('cancelSchedule')
                    ->label(__('contract_finance.actions.cancel_schedule'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->tooltip(__('contract_finance.action_help.cancel_schedule'))
                    ->requiresConfirmation()
                    ->visible(fn (ContractPaymentSchedule $record): bool => in_array($record->status, [ContractPaymentScheduleStatus::Draft, ContractPaymentScheduleStatus::PendingApproval, ContractPaymentScheduleStatus::Issued], true) && auth()->user()->can('cancel_contract_payment_schedule'))
                    ->form([Forms\Components\Textarea::make('reason')->label(__('contract_finance.fields.cancellation_reason'))->required()])
                    ->action(fn (ContractPaymentSchedule $record, array $data) => $this->run(fn () => app(ContractFinancialService::class)->cancel(auth()->user(), $record, $data['reason']), __('contract_finance.notifications.schedule_cancelled'))),
            ]);
    }

    private function createScheduleForm(): array
    {
        return [
            Forms\Components\Select::make('contract_version_id')->label(__('contract_finance.fields.contract_version'))
                ->options(fn (): array => $this->getOwnerRecord()->versions()->whereIn('status', [ContractVersionStatus::Approved->value, ContractVersionStatus::Signed->value])->orderByDesc('version_number')->get()->mapWithKeys(fn (ContractVersion $version) => [$version->id => __('contract_finance.fields.version').' '.$version->version_number.' — '.$version->total_amount.' '.$version->currency])->all())
                ->required()->searchable(),
            Forms\Components\Select::make('assigned_to')->label(__('contract_finance.fields.assigned_to'))
                ->options(fn (): array => User::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            Forms\Components\Select::make('generation_mode')->label(__('contract_finance.fields.generation_method'))->options([
                'manual' => __('contract_finance.fields.manual_installments'), 'count' => __('contract_finance.actions.generate_by_count'), 'duration' => __('contract_finance.actions.generate_by_duration'),
            ])->default('count')->required()->live(),
            Forms\Components\TextInput::make('number_of_installments')->label(__('contract_finance.fields.number_of_installments'))->helperText(__('contract_finance.guidance.number_of_installments'))->integer()->minValue(1)->visible(fn (Forms\Get $get): bool => $get('generation_mode') === 'count')->required(fn (Forms\Get $get): bool => $get('generation_mode') === 'count'),
            Forms\Components\TextInput::make('duration')->label(__('contract_finance.fields.duration'))->helperText(__('contract_finance.guidance.duration'))->integer()->minValue(1)->visible(fn (Forms\Get $get): bool => $get('generation_mode') === 'duration')->required(fn (Forms\Get $get): bool => $get('generation_mode') === 'duration'),
            Forms\Components\Select::make('duration_unit')->label(__('contract_finance.fields.duration_unit'))->options(['months' => __('contract_finance.fields.months'), 'years' => __('contract_finance.fields.years')])->default('months')->visible(fn (Forms\Get $get): bool => $get('generation_mode') === 'duration')->required(fn (Forms\Get $get): bool => $get('generation_mode') === 'duration'),
            Forms\Components\Select::make('frequency_preset')->label(__('contract_finance.fields.frequency'))->options(['monthly' => __('contract_finance.frequency_presets.monthly'), 'quarterly' => __('contract_finance.frequency_presets.quarterly'), 'semiannual' => __('contract_finance.frequency_presets.semiannual'), 'annually' => __('contract_finance.frequency_presets.annually'), 'custom' => __('contract_finance.frequency_presets.custom')])->default('monthly')->live()->afterStateUpdated(function ($state, Forms\Set $set): void { $set('interval_months', match ($state) { 'monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'annually' => 12, default => null }); })->visible(fn (Forms\Get $get): bool => in_array($get('generation_mode'), ['count', 'duration'], true)),
            Forms\Components\TextInput::make('interval_months')->label(__('contract_finance.fields.interval_months'))->helperText(fn (Forms\Get $get): string => $get('frequency_preset') === 'custom' ? __('contract_finance.guidance.interval_months_custom') : __('contract_finance.guidance.interval_months_preset'))->integer()->minValue(1)->default(1)->disabled(fn (Forms\Get $get): bool => $get('frequency_preset') !== 'custom')->dehydrated()->required(fn (Forms\Get $get): bool => in_array($get('generation_mode'), ['count', 'duration'], true))->visible(fn (Forms\Get $get): bool => in_array($get('generation_mode'), ['count', 'duration'], true)),
            Forms\Components\DatePicker::make('first_installment_date')->label(__('contract_finance.fields.first_installment_date'))->native(false)->required(fn (Forms\Get $get): bool => in_array($get('generation_mode'), ['count', 'duration'], true))->visible(fn (Forms\Get $get): bool => in_array($get('generation_mode'), ['count', 'duration'], true)),
            Forms\Components\TextInput::make('down_payment_amount')->label(__('contract_finance.fields.down_payment_amount'))->helperText(__('contract_finance.guidance.down_payment'))->numeric()->minValue(0)->live(),
            Forms\Components\DatePicker::make('down_payment_due_date')->label(__('contract_finance.fields.down_payment_due_date'))->native(false)->visible(fn (Forms\Get $get): bool => (float) ($get('down_payment_amount') ?? 0) > 0)->required(fn (Forms\Get $get): bool => (float) ($get('down_payment_amount') ?? 0) > 0),
            $this->installmentRepeater()->visible(fn (Forms\Get $get): bool => $get('generation_mode') === 'manual'),
        ];
    }

    private function installmentRepeater(): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make('installments')
            ->label(__('contract_finance.navigation.installments'))
            ->schema([
                Forms\Components\Select::make('kind')->label(__('contract_finance.fields.kind'))->options(collect(ContractInstallmentKind::cases())->mapWithKeys(fn (ContractInstallmentKind $kind) => [$kind->value => $kind->getLabel()])->all())->default(ContractInstallmentKind::Installment->value)->required(),
                Forms\Components\TextInput::make('amount')->label(__('contract_finance.fields.amount'))->numeric()->minValue(0.0001)->required(),
                Forms\Components\DatePicker::make('due_date')->label(__('contract_finance.fields.due_date'))->native(false)->required(),
            ])->columns(3)->defaultItems(0)->addActionLabel(__('contract_finance.actions.add_installment'));
    }

    private function paymentForm(ContractPaymentSchedule $schedule): array
    {
        return [
            Forms\Components\Select::make('allocation_mode')
                ->label(__('contract_finance.fields.payment_application'))
                ->options([
                    'full_installment' => __('contract_finance.payment_application.full_installment'),
                    'custom_allocation' => __('contract_finance.payment_application.custom_allocation'),
                ])
                ->default('full_installment')
                ->helperText(__('contract_finance.guidance.payment_application'))
                ->native(false)
                ->live()
                ->afterStateUpdated(function (Forms\Set $set): void {
                    $set('single_installment_id', null);
                    $set('amount', null);
                    $set('allocations', []);
                }),
            Forms\Components\Select::make('party_id')->label(__('contract_finance.fields.customer_party'))
                ->options(fn (): array => $schedule->contractVersion->parties()->with('party')->get()->mapWithKeys(fn ($party) => [$party->party_id => $party->party?->name])->filter()->all())->searchable()->required(),
            Forms\Components\Select::make('single_installment_id')
                ->label(__('contract_finance.fields.installment_to_settle'))
                ->options(fn (): array => $this->outstandingInstallmentOptions($schedule))
                ->helperText(__('contract_finance.guidance.full_installment_payment'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) use ($schedule): void {
                    $installment = collect(app(ContractFinancialService::class)->outstandingInstallments($schedule))
                        ->firstWhere('id', (int) $state);
                    $set('amount', $installment['outstanding'] ?? null);
                })
                ->visible(fn (Forms\Get $get): bool => $get('allocation_mode') === 'full_installment')
                ->required(fn (Forms\Get $get): bool => $get('allocation_mode') === 'full_installment'),
            Forms\Components\TextInput::make('amount')->label(__('contract_finance.fields.amount'))->numeric()->minValue(0.0001)->disabled(fn (Forms\Get $get): bool => $get('allocation_mode') === 'full_installment')->dehydrated()->helperText(fn (Forms\Get $get): string => $get('allocation_mode') === 'full_installment' ? __('contract_finance.guidance.full_installment_amount') : __('contract_finance.guidance.custom_payment_amount'))->required(),
            Forms\Components\TextInput::make('currency')->label(__('contract_finance.fields.currency'))->default($schedule->currency)->disabled()->dehydrated()->required(),
            Forms\Components\DatePicker::make('payment_date')->label(__('contract_finance.fields.payment_date'))->default(today())->native(false)->required(),
            Forms\Components\Select::make('payment_method')->label(__('contract_finance.fields.payment_method'))->options(collect(['cash', 'bank_transfer', 'cheque', 'card', 'other'])->mapWithKeys(fn (string $method): array => [$method => __('contract_finance.payment_methods.'.$method)])->all())->required(),
            Forms\Components\TextInput::make('reference_number')->label(__('contract_finance.fields.reference_number')),
            Forms\Components\Hidden::make('idempotency_key')->default(fn (): string => Str::uuid()->toString()),
            Forms\Components\Repeater::make('allocations')->label(__('contract_finance.fields.allocations'))->helperText(__('contract_finance.guidance.allocations'))->schema([
                Forms\Components\Select::make('installment_id')->label(__('contract_finance.fields.installment'))->options(fn (): array => $this->outstandingInstallmentOptions($schedule))->required()->searchable(),
                Forms\Components\TextInput::make('allocated_amount')->label(__('contract_finance.fields.allocation_amount'))->numeric()->minValue(0.0001)->required(),
                Forms\Components\Hidden::make('currency')->default($schedule->currency),
            ])->columns(2)
                ->visible(fn (Forms\Get $get): bool => $get('allocation_mode') === 'custom_allocation')
                ->dehydrated(fn (Forms\Get $get): bool => $get('allocation_mode') === 'custom_allocation')
                ->minItems(fn (Forms\Get $get): int => $get('allocation_mode') === 'custom_allocation' ? 1 : 0)
                ->validationMessages(['min' => __('contract_finance.errors.allocation_required')])
                ->addActionLabel(__('contract_finance.actions.allocate_installment')),
        ];
    }

    /** @return array<int, string> */
    private function outstandingInstallmentOptions(ContractPaymentSchedule $schedule): array
    {
        return collect(app(ContractFinancialService::class)->outstandingInstallments($schedule))
            ->mapWithKeys(fn (array $item): array => [$item['id'] => (ContractInstallmentKind::tryFrom($item['kind'])?->getLabel() ?? $item['kind'])
                .' #'.$item['sequence'].' — '.$item['due_date'].' — '.__('contract_finance.fields.remaining').': '.$item['outstanding'].' '.$item['currency']])
            ->all();
    }

    private function prepareReceiptData(ContractPaymentSchedule $schedule, array $data): array
    {
        if (($data['allocation_mode'] ?? null) !== 'full_installment') {
            return $data;
        }

        $installment = collect(app(ContractFinancialService::class)->outstandingInstallments($schedule))
            ->firstWhere('id', (int) ($data['single_installment_id'] ?? 0));

        if (! $installment) {
            throw ValidationException::withMessages([
                'single_installment_id' => __('contract_finance.errors.payment_invalid'),
            ]);
        }

        return [
            ...$data,
            'amount' => $installment['outstanding'],
            'allocations' => [[
                'installment_id' => $installment['id'],
                'allocated_amount' => $installment['outstanding'],
                'currency' => $installment['currency'],
            ]],
        ];
    }

    private function run(callable $operation, string $success): void
    {
        try {
            $operation();
            Notification::make()->success()->title($success)->send();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('contract_finance.errors.unable_to_update'))->body(collect($exception->errors())->flatten()->first())->persistent()->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('contract_finance.errors.unauthorized_title'))->body(__('contract_finance.errors.not_authorized'))->persistent()->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title(__('contract_finance.errors.unexpected_error'))->body(__('contract_finance.errors.try_again_or_support'))->persistent()->send();
        }
    }
}
