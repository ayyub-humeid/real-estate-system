<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            'contract:id,contract_number',
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
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('payment_directory.actions.details'))->tooltip(__('payment_directory.help.details')),
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
            $payment->contract_id !== null => $payment->contract?->contract_number ?: __('payment_directory.labels.unavailable_record'),
            $payment->project_id !== null => $payment->project?->name ?: __('payment_directory.labels.unavailable_record'),
            default => '—',
        };
    }
}
