<?php
// app/Filament/Resources/LeaseResource/RelationManagers/PaymentsRelationManager.php

namespace App\Filament\Resources\LeaseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $title = 'Payments';
    protected static ?string $icon = 'heroicon-o-banknotes';

    // 🔥 PERFORMANCE: Eager load in relation manager too!
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            // ✅ Modify query to eager load relationships
            ->modifyQueryUsing(fn($query) => $query->with(['recordedBy:id,name']))
            ->columns([
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('USD')
                    ->sortable()
                    ->color('success'),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Balance')
                    ->money('USD')
                    ->sortable()
                    ->color(fn($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('payment_date')
                    ->date()
                    ->placeholder('Not paid'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'success' => 'paid',
                        'danger' => 'overdue',
                        'warning' => 'partial',
                    ]),

                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Recorded By')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'partial' => 'Partial',
                    ])
                    ->native(false)
                    ->multiple(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // ✅ Auto-fill lease_id and recorded_by
                        $data['lease_id'] = $this->getOwnerRecord()->id;
                        $data['recorded_by'] = auth()->id();
                        $data['remaining_amount'] = $data['amount'] - ($data['paid_amount'] ?? 0);
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                
                // 🔥 Quick record payment action
                Tables\Actions\Action::make('record_payment')
                    ->label('Record Payment')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => !$record->is_paid)
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Payment Amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(fn($record) => $record->remaining_amount),
                        
                        Forms\Components\Select::make('method')
                            ->label('Payment Method')
                            ->options([
                                'cash' => 'Cash',
                                'bank_transfer' => 'Bank Transfer',
                                'check' => 'Check',
                                'credit_card' => 'Credit Card',
                                'online' => 'Online Payment',
                            ])
                            ->required()
                            ->native(false),
                        
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference Number'),
                    ])
                    ->action(function($record, array $data) {
                        $record->recordPayment(
                            $data['amount'],
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
            ->emptyStateHeading('No payments yet')
            ->emptyStateDescription('Generate payment schedule or add payments manually')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateActions([
                Tables\Actions\Action::make('generate')
                    ->label('Generate Payment Schedule')
                    ->icon('heroicon-o-calendar')
                    ->action(function() {
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
                Forms\Components\DatePicker::make('due_date')
                    ->required()
                    ->default(now())
                    ->native(false),

                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->default(fn() => $this->getOwnerRecord()->rent_amount),

                Forms\Components\DatePicker::make('payment_date')
                    ->native(false),

                Forms\Components\TextInput::make('paid_amount')
                    ->numeric()
                    ->prefix('$')
                    ->default(0),

                Forms\Components\Select::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'check' => 'Check',
                        'credit_card' => 'Credit Card',
                        'online' => 'Online',
                    ])
                    ->native(false),

                Forms\Components\TextInput::make('reference_number'),

                Forms\Components\Select::make('status')
                    ->required()
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'partial' => 'Partial',
                    ])
                    ->default('pending')
                    ->native(false),

                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}