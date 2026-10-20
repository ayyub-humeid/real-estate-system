<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\{ActualCost, Party, Payment};
use App\Services\BudgetingService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ProjectPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $title = 'Payments';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Payments');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('No payments recorded yet'))
            ->emptyStateDescription(__('Record vendor and contractor payments and allocate them against approved actual costs.'))
            ->columns([
                Tables\Columns\TextColumn::make('party.name')->label(__('Vendor')),
                Tables\Columns\TextColumn::make('amount')->label(__('Amount')),
                Tables\Columns\TextColumn::make('currency')->label(__('Currency')),
                Tables\Columns\TextColumn::make('project_amount')->label(__('Project amount')),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()->colors([
                    'warning' => 'pending',
                    'success' => 'completed',
                    'danger' => ['failed', 'voided'],
                ])->formatStateUsing(fn ($state) => match ($state) {
                    'pending' => __('Pending'),
                    'completed' => __('Completed'),
                    'failed' => __('Failed'),
                    'voided' => __('Voided'),
                    default => $state,
                }),
                Tables\Columns\TextColumn::make('payment_date')->label(__('Payment Date'))->date(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('recordPayment')
                    ->label(__('Record payment'))
                    ->icon('heroicon-o-plus')
                    ->form(fn (): array => [
                        Forms\Components\Select::make('party_id')->label(__('Vendor'))
                            ->options(Party::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->pluck('name', 'id'))
                            ->required()->searchable(),
                        Forms\Components\TextInput::make('amount')->label(__('Amount'))->numeric()->required(),
                        Forms\Components\TextInput::make('currency')->label(__('Currency'))->default($this->getOwnerRecord()->currency)->required(),
                        Forms\Components\TextInput::make('project_amount')->label(__('Project-currency amount'))
                            ->helperText(fn (): string => __('Project currency:') . ' ' . $this->getOwnerRecord()->currency)
                            ->numeric()->required(),
                        Forms\Components\DatePicker::make('payment_date')->label(__('Payment Date'))->default(now())->maxDate(today())->required()
                            ->helperText(__('Payment dates cannot be in the future.')),
                        Forms\Components\TextInput::make('reference_number')->label(__('Reference number')),
                        Forms\Components\Select::make('payment_method')->label(__('Payment method'))->options([
                            'cash' => __('Cash'), 'bank_transfer' => __('Bank transfer'), 'cheque' => __('Cheque'), 'card' => __('Card'),
                        ]),
                    ])
                    ->action(fn (array $data) => $this->run(fn () => app(BudgetingService::class)->recordPayment(auth()->user(), $this->getOwnerRecord(), $data))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('Details'))->icon('heroicon-o-eye')
                    ->tooltip(__('View payment details and cost allocations'))->modalWidth('4xl')
                    ->infolist([
                        \Filament\Infolists\Components\Section::make(__('Payment'))->schema([
                            \Filament\Infolists\Components\TextEntry::make('party.name')->label(__('Vendor')),
                            \Filament\Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()->colors(['warning' => 'pending', 'success' => 'completed', 'danger' => ['failed', 'voided']])
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'pending' => __('Pending'),
                                    'completed' => __('Completed'),
                                    'failed' => __('Failed'),
                                    'voided' => __('Voided'),
                                    default => $state,
                                }),
                            \Filament\Infolists\Components\TextEntry::make('direction')->label(__('Direction'))->badge(),
                            \Filament\Infolists\Components\TextEntry::make('amount')->label(__('Amount'))->money(fn (Payment $record): string => $record->currency),
                            \Filament\Infolists\Components\TextEntry::make('project_amount')->label(__('Project amount'))->money(fn (Payment $record): string => $record->project->currency),
                            \Filament\Infolists\Components\TextEntry::make('payment_date')->label(__('Payment Date'))->date(),
                            \Filament\Infolists\Components\TextEntry::make('payment_method')->label(__('Payment method'))->placeholder(__('—')),
                            \Filament\Infolists\Components\TextEntry::make('reference_number')->label(__('Reference number'))->placeholder(__('—')),
                            \Filament\Infolists\Components\TextEntry::make('void_reason')->label(__('Void reason'))->columnSpanFull()->placeholder(__('—')),
                        ])->columns(3),
                        \Filament\Infolists\Components\Section::make(__('Cost allocations'))->schema([
                            \Filament\Infolists\Components\RepeatableEntry::make('allocations')->label('')->schema([
                                \Filament\Infolists\Components\TextEntry::make('allocatable.name')->label(__('Actual cost')),
                                \Filament\Infolists\Components\TextEntry::make('payment_amount')->label(__('Payment amount')),
                                \Filament\Infolists\Components\TextEntry::make('actual_cost_amount')->label(__('Cost amount')),
                                \Filament\Infolists\Components\TextEntry::make('project_amount')->label(__('Project amount')),
                            ])->columns(4),
                        ])->collapsible(),
                    ]),
                Tables\Actions\Action::make('allocate')->label(__('Allocate'))->icon('heroicon-o-arrow-right-circle')->tooltip(__('Allocate to approved cost'))
                    ->visible(fn (Payment $record): bool => $record->status === 'completed')
                    ->form(fn (Payment $record): array => [
                        Forms\Components\Select::make('actual_cost_id')->label(__('Approved actual cost'))
                            ->options(ActualCost::withoutGlobalScopes()->where('project_id', $record->project_id)->where('party_id', $record->party_id)->where('status', 'approved')->pluck('name', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('payment_amount')->label(__('Payment amount'))->numeric()->required(),
                        Forms\Components\TextInput::make('actual_cost_amount')->label(__('Cost amount'))->numeric()->required(),
                        Forms\Components\TextInput::make('project_amount')->label(__('Project amount'))->numeric()->required(),
                    ])
                    ->action(function (Payment $record, array $data) {
                        $cost = ActualCost::withoutGlobalScopes()->findOrFail($data['actual_cost_id']);
                        unset($data['actual_cost_id']);
                        return $this->run(fn () => app(BudgetingService::class)->allocatePayment(auth()->user(), $record, $cost, $data));
                    }),
                Tables\Actions\Action::make('void')->label(__('Void'))->icon('heroicon-o-no-symbol')->tooltip(__('Void unallocated payment'))->color('danger')
                    ->visible(fn (Payment $record): bool => $record->status === 'completed' && ! $record->allocations()->exists())
                    ->form([Forms\Components\Textarea::make('reason')->label(__('Reason'))->required()])
                    ->action(fn (Payment $record, array $data) => $this->run(fn () => app(BudgetingService::class)->voidPayment(auth()->user(), $record, $data['reason']))),
            ]);
    }

    private function run(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('Cannot update payment'))
                ->body(collect($exception->errors())->flatten()->first())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('Unauthorized'))
                ->body(__('You do not have permission to perform this payment action.'))->send();
        }

        return null;
    }
}
