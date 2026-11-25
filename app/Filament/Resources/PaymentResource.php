<?php

namespace App\Filament\Resources;

use App\Enums\ContractPaymentScheduleStatus;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\{Contract, ContractPaymentSchedule, Payment};
use App\Services\ContractFinancialService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = '💼 Operations';
    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return __('payment_directory.labels.payment');
    }
    public static function getPluralModelLabel(): string
    {
        return __('payment_directory.labels.payments');
    }
    public static function getNavigationLabel(): string
    {
        return __('payment_directory.labels.payments');
    }
    public static function getNavigationGroup(): ?string
    {
        return __('💼 Operations');
    }

    /** The badge intentionally means "needs review", not a mixed-currency total. */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'project:id,name,currency',
            'contract:id,contract_number,contract_type,unit_id,planned_unit_id,property_id,project_id',
            'contract.unit:id,unit_number',
            'contract.plannedUnit:id,code',
            'contract.property:id,name',
            'contract.project:id,name',
            'contractPaymentSchedule:id,contract_id,revision_number',
            'party:id,name',
            'recordedBy:id,name',
            'reversalOf:id,reference_number',
            'contractAllocations.installment:id,contract_payment_schedule_id,sequence,due_date,currency',
            'allocations.allocatable',
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading(__('payment_directory.headings.directory'))
            ->description(fn(): string => self::directoryDescription())
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')->label(__('payment_directory.fields.payment_date'))->date()->sortable(),
                Tables\Columns\TextColumn::make('direction')->label(__('payment_directory.fields.direction'))->badge()
                    ->formatStateUsing(fn(?string $state): string => self::direction($state))
                    ->color(fn(?string $state): string => $state === 'incoming' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('amount')->label(__('payment_directory.fields.amount'))->money(fn(Payment $record): string => $record->currency)->sortable(),
                Tables\Columns\TextColumn::make('purpose')->label(__('payment_directory.fields.purpose'))
                    ->state(fn(Payment $record): string => self::purpose($record))
                    ->description(fn(Payment $record): string => self::linkedRecord($record))->wrap(),
                Tables\Columns\TextColumn::make('party.name')->label(__('payment_directory.fields.party'))->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('status')->label(__('payment_directory.fields.status'))->badge()
                    ->formatStateUsing(fn(?string $state): string => self::status($state))
                    ->color(fn(?string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed', 'voided' => 'danger',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('reference_number')->label(__('payment_directory.fields.reference'))->placeholder('—')->copyable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('payment_date')
                    ->label(__('payment_directory.fields.payment_date'))
                    ->form([
                        Forms\Components\DatePicker::make('from')->label(__('payment_directory.filters.date_from'))->native(false),
                        Forms\Components\DatePicker::make('until')->label(__('payment_directory.filters.date_until'))->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('payment_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('payment_date', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = __('payment_directory.filters.date_from').': '.\Carbon\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = __('payment_directory.filters.date_until').': '.\Carbon\Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                Tables\Filters\SelectFilter::make('contract')
                    ->label(__('payment_directory.fields.contract'))
                    ->relationship('contract', 'contract_number')
                    ->getOptionLabelFromRecordUsing(fn (Contract $record): string => $record->displayNameWithNumber())
                    ->searchable()
                    ->native(false),
                Tables\Filters\SelectFilter::make('direction')->label(__('payment_directory.fields.direction'))->options([
                    'incoming' => __('payment_directory.directions.incoming'),
                    'outgoing' => __('payment_directory.directions.outgoing'),
                ]),
                Tables\Filters\SelectFilter::make('status')->label(__('payment_directory.fields.status'))->options([
                    'pending' => self::status('pending'),
                    'completed' => self::status('completed'),
                    'failed' => self::status('failed'),
                    'voided' => self::status('voided'),
                ]),
                Tables\Filters\SelectFilter::make('payment_scope')->label(__('payment_directory.fields.source'))->options([
                    'contract_receipt' => __('payment_directory.purposes.contract_receipt'),
                    'project_cost' => __('payment_directory.purposes.project_cost'),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('recordPayment')
                    ->label(__('payment_directory.actions.record_payment'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->tooltip(__('payment_directory.help.record_payment'))
                    ->visible(fn (): bool => auth()->user()?->can('record_contract_payment')
                        && auth()->user()?->can('allocate_contract_payment'))
                    ->modalHeading(__('payment_directory.actions.record_payment'))
                    ->modalDescription(__('payment_directory.descriptions.record_payment'))
                    ->modalSubmitActionLabel(__('payment_directory.actions.save_payment'))
                    ->form(self::recordPaymentForm())
                    ->action(fn (array $data) => self::handleRecordPayment($data)),

                Tables\Actions\ExportAction::make()
                    ->label(__('payment_export.action'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->tooltip(__('payment_export.action_help'))
                    ->exporter(PaymentExporter::class)
                    ->authorize(fn (): bool => PaymentExporter::canExport())
                    ->modalHeading(__('payment_export.modal_heading'))
                    ->modalDescription(__('payment_export.modal_description'))
                    ->visible(fn (): bool => auth()->user()?->can('view_any_payment') ?? false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('payment_directory.actions.details'))->tooltip(__('payment_directory.help.details')),
            ])
            ->bulkActions([
                Tables\Actions\ExportBulkAction::make()
                    ->label(__('payment_export.action'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->exporter(PaymentExporter::class)
                    ->modalHeading(__('payment_export.modal_heading'))
                    ->modalDescription(__('payment_export.modal_description')),
            ])
            ->emptyStateHeading(__('payment_directory.empty.heading'))
            ->emptyStateDescription(__('payment_directory.empty.description'))
            ->defaultSort('payment_date', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('payment_directory.headings.overview'))->schema([
                Infolists\Components\TextEntry::make('payment_date')->label(__('payment_directory.fields.payment_date'))->date(),
                Infolists\Components\TextEntry::make('direction')->label(__('payment_directory.fields.direction'))->badge()->formatStateUsing(fn(?string $state): string => self::direction($state))->color(fn(?string $state): string => $state === 'incoming' ? 'success' : 'danger'),
                Infolists\Components\TextEntry::make('amount')->label(__('payment_directory.fields.amount'))->money(fn(Payment $record): string => $record->currency),
                Infolists\Components\TextEntry::make('currency')->label(__('payment_directory.fields.currency')),
                Infolists\Components\TextEntry::make('status')->label(__('payment_directory.fields.status'))->badge()->formatStateUsing(fn(?string $state): string => self::status($state)),
                Infolists\Components\TextEntry::make('party.name')->label(__('payment_directory.fields.party'))->placeholder('—'),
                Infolists\Components\TextEntry::make('payment_method')->label(__('payment_directory.fields.payment_method'))->formatStateUsing(fn(?string $state): string => $state ? __('contract_finance.payment_methods.' . $state) : '—'),
                Infolists\Components\TextEntry::make('reference_number')->label(__('payment_directory.fields.reference'))->placeholder('—'),
                Infolists\Components\TextEntry::make('recordedBy.name')->label(__('payment_directory.fields.recorded_by'))->placeholder('—'),
            ])->columns(3),
            Infolists\Components\Section::make(__('payment_directory.headings.purpose'))->schema([
                Infolists\Components\TextEntry::make('payment_purpose')->label(__('payment_directory.fields.purpose'))->state(fn(Payment $record): string => self::purpose($record)),
                Infolists\Components\TextEntry::make('linked_record')->label(__('payment_directory.fields.linked_record'))->state(fn(Payment $record): string => self::linkedRecord($record)),
                Infolists\Components\TextEntry::make('reversalOf.reference_number')->label(__('payment_directory.fields.reversal_of'))->placeholder('—')->visible(fn(Payment $record): bool => $record->reversal_of_payment_id !== null),
            ])->columns(3),
            Infolists\Components\Section::make(__('payment_directory.headings.contract_allocations'))
                ->visible(fn(Payment $record): bool => $record->contractAllocations->isNotEmpty())
                ->schema([Infolists\Components\RepeatableEntry::make('contract_allocation_details')->label('')->state(fn(Payment $record): array => $record->contractAllocations->map(fn($item): array => [
                    'installment' => '#' . $item->installment?->sequence,
                    'due_date' => $item->installment?->due_date?->toDateString(),
                    'amount' => $item->allocated_amount,
                    'currency' => $item->currency,
                ])->all())->schema([
                    Infolists\Components\TextEntry::make('installment')->label(__('payment_directory.fields.installment')),
                    Infolists\Components\TextEntry::make('due_date')->label(__('payment_directory.fields.due_date'))->date(),
                    Infolists\Components\TextEntry::make('amount')->label(__('payment_directory.fields.allocated_amount')),
                    Infolists\Components\TextEntry::make('currency')->label(__('payment_directory.fields.currency')),
                ])->columns(4)]),
            Infolists\Components\Section::make(__('payment_directory.headings.project_allocations'))
                ->visible(fn(Payment $record): bool => $record->allocations->isNotEmpty())
                ->schema([Infolists\Components\RepeatableEntry::make('project_allocation_details')->label('')->state(fn(Payment $record): array => $record->allocations->map(fn($item): array => [
                    'actual_cost' => $item->allocatable?->name ?? __('payment_directory.labels.unavailable_record'),
                    'amount' => $item->payment_amount,
                    'cost_amount' => $item->actual_cost_amount,
                    'project_amount' => $item->project_amount,
                ])->all())->schema([
                    Infolists\Components\TextEntry::make('actual_cost')->label(__('payment_directory.fields.actual_cost')),
                    Infolists\Components\TextEntry::make('amount')->label(__('payment_directory.fields.allocated_amount')),
                    Infolists\Components\TextEntry::make('cost_amount')->label(__('payment_directory.fields.cost_amount')),
                    Infolists\Components\TextEntry::make('project_amount')->label(__('payment_directory.fields.project_amount')),
                ])->columns(4)]),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPayments::route('/'), 'view' => Pages\ViewPayment::route('/{record}')];
    }

    /** Contracts that can receive a payment: those with an issued schedule. */
    private static function payableContractsQuery(): Builder
    {
        return Contract::query()
            ->whereHas('paymentSchedules', fn(Builder $query): Builder => $query->where('status', ContractPaymentScheduleStatus::Issued->value))
            ->with(['unit:id,unit_number', 'plannedUnit:id,code', 'property:id,name', 'project:id,name']);
    }

    /** The one schedule a payment can be applied to. Only an issued schedule accepts receipts. */
    private static function issuedSchedule(mixed $contractId): ?ContractPaymentSchedule
    {
        if (blank($contractId)) {
            return null;
        }

        return ContractPaymentSchedule::query()
            ->where('contract_id', $contractId)
            ->where('status', ContractPaymentScheduleStatus::Issued->value)
            ->latest('revision_number')
            ->first();
    }

    private static function contractLabels(Builder $query): array
    {
        return $query->get()->mapWithKeys(fn(Contract $contract): array => [
            $contract->id => $contract->displayNameWithNumber(),
        ])->all();
    }

    /**
     * A deliberately short form: settle one installment fast. Splitting a single
     * payment across several installments stays on the contract's schedule screen,
     * where the full picture is visible.
     */
    private static function recordPaymentForm(): array
    {
        return [
            Forms\Components\Select::make('contract_id')
                ->label(__('payment_directory.fields.contract'))
                ->placeholder(__('payment_directory.placeholders.choose_contract'))
                ->helperText(__('payment_directory.help.contract_choice'))
                ->options(fn(): array => self::contractLabels(self::payableContractsQuery()->latest('id')->limit(100)))
                ->getSearchResultsUsing(fn(string $search): array => self::contractLabels(
                    self::payableContractsQuery()
                        ->where(fn(Builder $query): Builder => $query
                            ->where('contract_number', 'like', "%{$search}%")
                            ->orWhereHas('unit', fn(Builder $unit): Builder => $unit->where('unit_number', 'like', "%{$search}%")))
                        ->limit(50)
                ))
                ->getOptionLabelUsing(fn($value): ?string => self::payableContractsQuery()->find($value)?->displayNameWithNumber())
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                    $schedule = self::issuedSchedule($state);
                    $set('installment_id', null);
                    $set('amount', null);
                    $set('party_id', null);
                    $set('currency', $schedule?->currency);
                })
                ->columnSpanFull(),

            Forms\Components\Select::make('party_id')
                ->label(__('payment_directory.fields.payer'))
                ->placeholder(__('payment_directory.placeholders.choose_payer'))
                ->options(function (Forms\Get $get): array {
                    $schedule = self::issuedSchedule($get('contract_id'));

                    return $schedule?->contractVersion?->parties()->with('party:id,name')->get()
                        ->mapWithKeys(fn($row): array => [$row->party_id => $row->party?->name])
                        ->filter()
                        ->all() ?? [];
                })
                ->searchable()
                ->required()
                ->visible(fn(Forms\Get $get): bool => filled($get('contract_id'))),

            Forms\Components\Select::make('installment_id')
                ->label(__('payment_directory.fields.installment_to_settle'))
                ->placeholder(__('payment_directory.placeholders.choose_installment'))
                ->helperText(__('payment_directory.help.installment_choice'))
                ->options(function (Forms\Get $get): array {
                    $schedule = self::issuedSchedule($get('contract_id'));
                    if (! $schedule) {
                        return [];
                    }

                    return collect(app(ContractFinancialService::class)->outstandingInstallments($schedule))
                        ->mapWithKeys(fn(array $item): array => [
                            $item['id'] => __('payment_directory.installment_option', [
                                'sequence' => $item['sequence'],
                                'amount' => $item['outstanding'],
                                'currency' => $item['currency'],
                                'due' => $item['due_date'] ?? '—',
                            ]),
                        ])
                        ->all();
                })
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set): void {
                    $schedule = self::issuedSchedule($get('contract_id'));
                    if (! $schedule) {
                        return;
                    }
                    $installment = collect(app(ContractFinancialService::class)->outstandingInstallments($schedule))
                        ->firstWhere('id', (int) $state);
                    $set('amount', $installment['outstanding'] ?? null);
                    $set('currency', $installment['currency'] ?? $schedule->currency);
                })
                ->visible(fn(Forms\Get $get): bool => filled($get('contract_id')))
                ->columnSpanFull(),

            Forms\Components\TextInput::make('amount')
                ->label(__('payment_directory.fields.amount'))
                ->helperText(__('payment_directory.help.amount'))
                ->numeric()
                ->minValue(0.0001)
                ->suffix(fn(Forms\Get $get): ?string => $get('currency'))
                ->required()
                ->visible(fn(Forms\Get $get): bool => filled($get('installment_id'))),

            Forms\Components\DatePicker::make('payment_date')
                ->label(__('payment_directory.fields.payment_date'))
                ->default(today())
                ->maxDate(today())
                ->native(false)
                ->required()
                ->visible(fn(Forms\Get $get): bool => filled($get('installment_id'))),

            Forms\Components\Select::make('payment_method')
                ->label(__('payment_directory.fields.payment_method'))
                ->options(collect(['cash', 'bank_transfer', 'cheque', 'card', 'other'])
                    ->mapWithKeys(fn(string $method): array => [$method => __('contract_finance.payment_methods.' . $method)])
                    ->all())
                ->native(false)
                ->required()
                ->visible(fn(Forms\Get $get): bool => filled($get('installment_id'))),

            Forms\Components\TextInput::make('reference_number')
                ->label(__('payment_directory.fields.reference'))
                ->helperText(__('payment_directory.help.reference'))
                ->maxLength(191)
                ->visible(fn(Forms\Get $get): bool => filled($get('installment_id'))),

            Forms\Components\Hidden::make('currency'),
            Forms\Components\Hidden::make('idempotency_key')->default(fn(): string => Str::uuid()->toString()),
        ];
    }

    private static function handleRecordPayment(array $data): void
    {
        try {
            $schedule = self::issuedSchedule($data['contract_id'] ?? null);

            if (! $schedule) {
                Notification::make()->danger()
                    ->title(__('payment_directory.errors.no_issued_schedule_title'))
                    ->body(__('payment_directory.errors.no_issued_schedule_body'))
                    ->persistent()->send();

                return;
            }

            $payment = app(ContractFinancialService::class)->recordReceipt(auth()->user(), $schedule, [
                'party_id' => $data['party_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?: $schedule->currency,
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'allocations' => [[
                    'installment_id' => (int) $data['installment_id'],
                    'allocated_amount' => $data['amount'],
                    'currency' => $data['currency'] ?: $schedule->currency,
                ]],
            ]);

            Notification::make()->success()
                ->title(__('payment_directory.notifications.payment_recorded'))
                ->body(__('payment_directory.notifications.payment_recorded_body', [
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'contract' => $schedule->contract?->displayNameWithNumber() ?? '',
                ]))
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()->danger()
                ->title(__('contract_finance.errors.unable_to_update'))
                ->body(collect($exception->errors())->flatten()->first())
                ->persistent()->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()
                ->title(__('contract_finance.errors.unauthorized_title'))
                ->body(__('contract_finance.errors.not_authorized'))
                ->persistent()->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->danger()
                ->title(__('contract_finance.errors.unexpected_error'))
                ->body(__('contract_finance.errors.try_again_or_support'))
                ->persistent()->send();
        }
    }

    private static function direction(?string $value): string
    {
        return __('payment_directory.directions.' . ($value ?: 'outgoing'));
    }
    private static function status(?string $value): string
    {
        return __('payment_directory.statuses.' . ($value ?: 'pending'));
    }

    private static function directoryDescription(): string
    {
        // Count records only: summing multi-currency payments here would be misleading.
        $metrics = static::getEloquentQuery()
            ->selectRaw("COUNT(*) as total_count, SUM(CASE WHEN direction = 'incoming' THEN 1 ELSE 0 END) as incoming_count, SUM(CASE WHEN direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing_count, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->first();

        return __('payment_directory.descriptions.summary', [
            'total' => (int) ($metrics?->total_count ?? 0),
            'incoming' => (int) ($metrics?->incoming_count ?? 0),
            'outgoing' => (int) ($metrics?->outgoing_count ?? 0),
            'pending' => (int) ($metrics?->pending_count ?? 0),
        ]);
    }

    private static function purpose(Payment $payment): string
    {
        return match (true) {
            $payment->reversal_of_payment_id !== null => __('payment_directory.purposes.reversal'),
            $payment->contract_id !== null => __('payment_directory.purposes.contract_receipt'),
            $payment->project_id !== null => __('payment_directory.purposes.project_cost'),
            default => __('payment_directory.purposes.other'),
        };
    }
    private static function linkedRecord(Payment $payment): string
    {
        return match (true) {
            $payment->reversal_of_payment_id !== null => $payment->reversalOf?->reference_number ?: __('payment_directory.labels.original_payment'),
            $payment->contract_id !== null => $payment->contract?->displayNameWithNumber() ?: __('payment_directory.labels.unavailable_record'),
            $payment->project_id !== null => $payment->project?->name ?: __('payment_directory.labels.unavailable_record'),
            default => '—',
        };
    }
}
