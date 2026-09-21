<?php

namespace App\Filament\Resources\LeaseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $title       = 'Payments / Installments';
    protected static ?string $icon        = 'heroicon-o-banknotes';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->modifyQueryUsing(fn ($query) => $query->with(['recordedBy:id,name']))
            ->header(function () {
                // ✅ Lease-level summary header
                $lease      = $this->getOwnerRecord();
                $rentAmount = (float) $lease->rent_amount;

                $totalPaid = $lease->payments()
                    ->where('type', 'rent')
                    ->where('status', '!=', 'cancelled')
                    ->sum('paid_amount');

                $totalPaid  = (float) $totalPaid;
                $remaining  = max(0, $rentAmount - $totalPaid);
                $percent    = $rentAmount > 0
                    ? min(100, (int) round(($totalPaid / $rentAmount) * 100))
                    : 0;

                $isFullyPaid = $totalPaid >= $rentAmount && $rentAmount > 0;

                $barColor  = $isFullyPaid ? '#10b981' : ($percent >= 75 ? '#f59e0b' : '#3b82f6');
                $statusBadge = $isFullyPaid
                    ? '<span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:9999px;font-size:12px;font-weight:600;">✅ Fully Paid</span>'
                    : '<span style="background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:9999px;font-size:12px;font-weight:600;">⏳ ' . $percent . '% Paid</span>';

                return new HtmlString('
                    <div style="padding:16px 20px;background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <div style="font-size:14px;font-weight:600;color:#374151;">
                                Contract Total:
                                <span style="color:#1d4ed8;">$' . number_format($rentAmount, 2) . '</span>
                            </div>
                            ' . $statusBadge . '
                        </div>
                        <div style="display:flex;gap:24px;font-size:13px;margin-bottom:10px;">
                            <div>
                                <span style="color:#6b7280;">Paid: </span>
                                <span style="font-weight:600;color:#059669;">$' . number_format($totalPaid, 2) . '</span>
                            </div>
                            <div>
                                <span style="color:#6b7280;">Remaining: </span>
                                <span style="font-weight:600;color:#dc2626;">$' . number_format($remaining, 2) . '</span>
                            </div>
                            <div>
                                <span style="color:#6b7280;">Installments: </span>
                                <span style="font-weight:600;" class="text-gray-900 dark:text-white">' . $lease->payments()->where('type', 'rent')->where('status', '!=', 'cancelled')->count() . '</span>
                            </div>
                        </div>
                        <div style="background:#e5e7eb;border-radius:9999px;height:10px;overflow:hidden;">
                            <div style="width:' . $percent . '%;height:100%;background:' . $barColor . ';border-radius:9999px;transition:width .3s ease;"></div>
                        </div>
                    </div>
                ');
            })
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'primary' => 'rent',
                        'warning' => 'deposit',
                        'info' => 'fee',
                        'gray' => 'other',
                    ]),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->is_overdue ? 'danger' : null),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Installment')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('USD')
                    ->color('success'),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Balance')
                    ->money('USD')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                // ✅ Per-installment mini progress bar
                Tables\Columns\TextColumn::make('payment_progress')
                    ->label('Progress')
                    ->state(fn ($record) => $record->amount > 0
                        ? min(100, (int) round(($record->paid_amount / $record->amount) * 100))
                        : 0
                    )
                    ->suffix('%')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50  => 'warning',
                        default       => 'danger',
                    }),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Paid On')
                    ->date()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray'    => 'pending',
                        'success' => 'paid',
                        'danger'  => 'overdue',
                        'warning' => 'partial',
                    ]),

                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Ref #')
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Recorded By')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'paid'      => 'Paid',
                        'overdue'   => 'Overdue',
                        'partial'   => 'Partial',
                        'cancelled' => 'Cancelled',
                    ])
                    ->native(false)
                    ->multiple(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['lease_id']    = $this->getOwnerRecord()->id;
                        $data['recorded_by'] = auth()->id();
                        $data['remaining_amount'] = max(0,
                            (float) ($data['amount'] ?? 0) - (float) ($data['paid_amount'] ?? 0)
                        );
                        return $data;
                    }),

                // ✅ Generate schedule from header
                Tables\Actions\Action::make('generate_schedule')
                    ->label('Generate Schedule')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Payment Schedule')
                    ->modalDescription(fn () =>
                        'This will create ' .
                        $this->getOwnerRecord()->payments()->count() .
                        ' existing installments. New ones will be added for any missing due dates.'
                    )
                    ->visible(fn () => $this->getOwnerRecord()->status === 'active')
                    ->action(function () {
                        $this->getOwnerRecord()->generatePaymentSchedule();

                        \Filament\Notifications\Notification::make()
                            ->title('Payment schedule generated')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                // ✅ Quick record payment
                Tables\Actions\Action::make('record_payment')
                    ->label('Pay')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->is_paid)
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount to Pay')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(fn ($record) => $record->remaining_amount),

                        Forms\Components\Select::make('method')
                            ->label('Payment Method')
                            ->options([
                                'cash'          => 'Cash',
                                'bank_transfer' => 'Bank Transfer',
                                'check'         => 'Check',
                                'credit_card'   => 'Credit Card',
                                'online'        => 'Online',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('reference')
                            ->label('Reference Number'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->recordPayment(
                            (float) $data['amount'],
                            $data['method'],
                            $data['reference'] ?? null
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Payment recorded')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No installments yet')
            ->emptyStateDescription('Click "Generate Schedule" to auto-create installments based on the lease terms.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateActions([
                Tables\Actions\Action::make('generate')
                    ->label('Generate Payment Schedule')
                    ->icon('heroicon-o-calendar')
                    ->visible(fn () => $this->getOwnerRecord()->status === 'active')
                    ->action(function () {
                        $this->getOwnerRecord()->generatePaymentSchedule();

                        \Filament\Notifications\Notification::make()
                            ->title('Payment schedule generated')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('due_date', 'asc');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->required()
                    ->options([
                        'rent' => 'Rent',
                        'deposit' => 'Deposit',
                        'fee' => 'Fee',
                        'other' => 'Other',
                    ])
                    ->default('rent')
                    ->native(false),

                Forms\Components\DatePicker::make('due_date')
                    ->required()
                    ->default(now())
                    ->native(false),

                Forms\Components\TextInput::make('amount')
                    ->label('Installment Amount')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->default(fn () => $this->getOwnerRecord()->rent_amount),

                Forms\Components\TextInput::make('paid_amount')
                    ->label('Amount Paid')
                    ->numeric()
                    ->prefix('$')
                    ->default(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $due = (float) ($get('amount') ?? 0);
                        $paid = (float) ($state ?? 0);
                        $set('remaining_amount', max(0, $due - $paid));

                        if ($paid >= $due && $due > 0) {
                            $set('status', 'paid');
                        } elseif ($paid > 0) {
                            $set('status', 'partial');
                        } else {
                            $set('status', 'pending');
                        }
                    }),

                Forms\Components\TextInput::make('remaining_amount')
                    ->label('Remaining Balance')
                    ->numeric()
                    ->prefix('$')
                    ->readOnly(),

                Forms\Components\DatePicker::make('payment_date')
                    ->native(false),

                Forms\Components\Select::make('payment_method')
                    ->options([
                        'cash'          => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'check'         => 'Check',
                        'credit_card'   => 'Credit Card',
                        'online'        => 'Online',
                    ])
                    ->native(false),

                Forms\Components\TextInput::make('reference_number'),

                Forms\Components\Select::make('status')
                    ->required()
                    ->options([
                        'pending'   => 'Pending',
                        'paid'      => 'Paid',
                        'overdue'   => 'Overdue',
                        'partial'   => 'Partial',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending')
                    ->native(false),

                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}