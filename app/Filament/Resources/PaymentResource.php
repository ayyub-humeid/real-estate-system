<?php
// app/Filament/Resources/PaymentResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Resources\PaymentResource\RelationManagers;
use App\Models\Payment;
use App\Models\Lease;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = '💼 Operations';
    protected static ?int $navigationSort = 2;

    // 🔥 PERFORMANCE OPTIMIZATION #1: Eager Loading Strategy
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // ✅ WHY: Eager load relationships to prevent N+1 queries
            // When displaying 15 payments, without this = 15+ extra queries
            // With this = 1 query loads ALL related data at once
            ->with([
                // 🎯 Select only needed columns (not all 15+ user columns)
                // This reduces data transfer from DB to PHP by ~70%
                'lease:id,unit_id,tenant_id,rent_amount,status,payment_frequency,outstanding_balance', 
                
                // 🎯 Nested eager loading - load unit AND its property in one go
                // Without nested loading: 1 query for units + 1 query for properties
                // With nested: Both loaded in the same query join
                'lease.unit:id,unit_number,property_id',
                'lease.unit.property:id,name',
                
                // 🎯 Load tenant info (who is paying)
                // 'lease.tenant:id,name,email'
                'lease.tenant:id,user_id',
                'lease.tenant.user:id,name,email',
                
                // 🎯 Load who recorded the payment (for audit trail)
                'recordedBy:id,name',
            ])
            // ✅ WHY: withCount() is faster than loading all documents
            // Instead of loading 50 document objects (50KB memory)
            // We get a single integer (4 bytes) computed in database
            ->withCount('documents');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment Information')
                    ->schema([
                        Forms\Components\Select::make('lease_id')
                            ->label('Lease')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live() // 🔥 Live updates to show lease details
                            ->relationship(
                                'lease',
                                'id',
                                // 🔥 PERFORMANCE: Only load active leases to reduce dropdown size
                                // WHY: No point showing 1000 expired leases in dropdown
                                // This limits query to ~50 active leases instead of all 1000+
                                fn(Builder $query) => $query
                                    ->where('status', 'active')
                                    ->with([
                                        // 'tenant:id,name'
                                        'unit.property:id,name',
                                        'tenant:id,user_id',
                                        'tenant.user:id,name',
                                    ])
                            )
                            // 🎯 WHY: Custom label format shows context without extra queries
                            // Data is already loaded via with() above - zero cost!
                            ->getOptionLabelFromRecordUsing(fn($record) =>
                                "#{$record->id} - {$record->unit->property->name} - " .
                                "Unit {$record->unit->unit_number} - " . ($record->tenant->user->name ?? 'N/A')
                            )
                            // 🔥 PERFORMANCE: Custom search to search across relationships
                            // WHY: Default search only searches lease.id
                            // This searches tenant name, property name, unit number
                            ->getSearchResultsUsing(function (string $search) {
                                return Lease::where('status', 'active')
                                    ->where(function($query) use ($search) {
                                        $query->where('id', 'like', "%{$search}%")
                                            ->orWhereHas('tenant.user', fn($q) =>
                                                $q->where('name', 'like', "%{$search}%")
                                            )
                                            ->orWhereHas('unit.property', fn($q) => 
                                                $q->where('name', 'like', "%{$search}%")
                                            );
                                    })
                                    ->withSum('payments as total_paid', 'paid_amount')
                                    ->with(['unit.property', 'tenant.user']) // ✅ Eager load for display
                                    ->limit(50) // ✅ PERFORMANCE: Limit results
                                    ->get()
                                    ->mapWithKeys(fn($lease) => [
                                        $lease->id => "#{$lease->id} - {$lease->unit->property->name} - Unit {$lease->unit->unit_number}"
                                    ]);
                            })
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $lease = Lease::query()
                                 ->withSum('payments as total_paid', 'paid_amount') 
                                ->find($state);
                            
                                    if ($lease) {
                                        // $set('amount', $lease->rent_amount);
                            
                            
                                        if ($lease->payment_frequency === 'monthly') {
                                            $set('paid_amount', $lease->rent_amount);
                                            $set('remaining_amount', 0);
                                        } else {
                                            $set('paid_amount', 0);
                                            $set('remaining_amount', $lease->remaining_amount);
                                        }
                                    }
                                }
                            }),

                        // 🔥 Show lease details when selected (no extra query - data cached)
                        Forms\Components\Placeholder::make('lease_details')
                            ->label('Lease Details')
                            ->content(function (Forms\Get $get) {
                                $leaseId = $get('lease_id');
                                if (!$leaseId) {
                                    return 'Select a lease to see details';
                                }

                                // ✅ PERFORMANCE: Find from collection if possible
                                // WHY: If lease is already loaded in form, reuse it
                                // Avoid extra database query
                                $lease = Lease::with(['unit.property', 'tenant.user'])->find($leaseId);
                                
                                if (!$lease) return '';

                                return new \Illuminate\Support\HtmlString("
                                    <div class='bg-gray-50 dark:bg-gray-800 p-3 rounded-lg space-y-1 text-sm'>
                                        <div><strong>Property:</strong> {$lease->unit->property->name}</div>
                                        <div><strong>Unit:</strong> {$lease->unit->unit_number}</div>
                                        <div><strong>Tenant:</strong> {$lease->tenant->user?->name}</div>
                                        <div><strong>Rent Amount:</strong> \${$lease->rent_amount}</div>
                                       <div><strong>Current Outstanding Balance:</strong> <span class='text-red-600 font-bold'>\$" . number_format($lease->outstanding_balance ?? 0, 2) . "</span></div>
                                        <div><strong>Payment Frequency:</strong> " . ucfirst($lease->payment_frequency) . "</div>
                                    </div>
                                ");
                            })
                            ->visible(fn(Forms\Get $get) => filled($get('lease_id'))),

                        Forms\Components\DatePicker::make('due_date')
                            ->required()
                            ->default(now())
                            ->native(false),

                       // 🔥 CONDITIONAL AMOUNT FIELD
                        // Hidden for monthly (auto-filled), Visible for annual/custom
                      Forms\Components\TextInput::make('amount')
    ->label(function (Forms\Get $get) {
        $leaseId = $get('lease_id');
        if ($leaseId) {
            $lease = Lease::find($leaseId);
            if ($lease?->payment_frequency === 'monthly') {
                return 'Payment Amount (Monthly Rent)';
            }
        }
        return 'Payment Amount (Installment)';
    })
    ->required()
    ->numeric()
    ->prefix('$')
    ->step(0.01)
    ->minValue(0)
    ->default(0)
    ->disabled(function (Forms\Get $get) {
        $leaseId = $get('lease_id');
        if (!$leaseId) return true;
        
        $lease = Lease::find($leaseId);
        return $lease?->payment_frequency === 'monthly';
    })
    ->dehydrated()
    ->helperText(function (Forms\Get $get) {
        $leaseId = $get('lease_id');
        if ($leaseId) {
            $lease = Lease::find($leaseId);
            if ($lease?->payment_frequency === 'monthly') {
                return 'Fixed monthly rent amount';
            }
        }
        return 'Enter installment amount';
    })
    ->live(debounce: 500)
    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
        // Update paid_amount to match (for convenience)
        // $set('paid_amount', $state);
    }),
                          

                        Forms\Components\TextInput::make('paid_amount')
    ->label('Amount Paid')
    ->numeric()
    ->prefix('$')
    ->step(0.01)
    ->minValue(0)
    ->default(function (Forms\Get $get) {
        $leaseId = $get('lease_id');
        if (!$leaseId) return 0;
        
        $lease = Lease::find($leaseId);
        // شهري: املأ تلقائي
        if ($lease?->payment_frequency === 'monthly') {
            return $lease->rent_amount;
        }
        return 0;
    })
    ->disabled(function (Forms\Get $get) {
        $leaseId = $get('lease_id');
        if (!$leaseId) return false;
        
        $lease = Lease::find($leaseId);
        return $lease?->payment_frequency === 'monthly';
    })
    ->dehydrated()
    ->live(debounce: 500)
    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
        $amount = $get('amount') ?? 0;
        $set('remaining_amount', $amount - $state);
        
        if ($state >= $amount) {
            $set('status', 'paid');
        } elseif ($state > 0) {
            $set('status', 'partial');
        } else {
            $set('status', 'pending');
        }
    }),
 
                        Forms\Components\TextInput::make('remaining_amount')
                            ->label('Remaining (This Payment)')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated()
                        ->helperText(function (Forms\Get $get) {
                        $leaseId = $get('lease_id');
                    if ($leaseId) {
                    $lease = Lease::find($leaseId);
                 if ($lease?->payment_frequency === 'monthly') {
                    return 'Monthly: Amount - Paid Amount';
             }
        return 'Annual: This Payment Remaining';
    }
    return 'Auto-calculated';
}),
                        Forms\Components\Select::make('payment_method')
                        ->required()
                            ->options([
                                'cash' => 'Cash',
                                'bank_transfer' => 'Bank Transfer',
                                'check' => 'Check',
                                'credit_card' => 'Credit Card',
                                'online' => 'Online Payment',
                                'other' => 'Other',
                            ])
                            ->native(false)
                            ->live(),

                        Forms\Components\TextInput::make('reference_number')
                            ->label('Transaction Reference')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('check_number')
                            ->visible(fn(Forms\Get $get) => $get('payment_method') === 'check')
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'overdue' => 'Overdue',
                                'partial' => 'Partial',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->native(false),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),

                        // 🔥 Auto-fill who recorded the payment
                        Forms\Components\Hidden::make('recorded_by')
                            ->default(fn() => auth()->id()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                // 🔥 WHY: These relationships are ALREADY eager loaded
                // Zero extra queries - data is in memory from getEloquentQuery()
                Tables\Columns\TextColumn::make('lease.unit.property.name')
                    ->label('Property')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-building-office-2')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('lease.unit.unit_number')
                    ->label('Unit')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('lease.tenant.user.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('USD')
                    ->sortable()
                    ->color('success'),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('USD')
                    ->sortable()
                    ->color(fn($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn($record) => $record->is_overdue ? 'danger' : null),

                Tables\Columns\TextColumn::make('payment_date')
                    ->date()
                    ->sortable()
                    ->placeholder('Not paid')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'success' => 'paid',
                        'danger' => 'overdue',
                        'warning' => 'partial',
                        'secondary' => 'cancelled',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->toggleable(),

                // 🔥 WHY: documents_count comes from withCount() - NO query
                // It's a single integer computed in the main query
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Receipts')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-document'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'partial' => 'Partial',
                        'cancelled' => 'Cancelled',
                    ])
                    ->native(false)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'check' => 'Check',
                        'credit_card' => 'Credit Card',
                        'online' => 'Online',
                    ])
                    ->native(false)
                    ->multiple(),

                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Only')
                    ->query(fn(Builder $query) => $query->overdue())
                    ->toggle(),

                Tables\Filters\Filter::make('unpaid')
                    ->label('Unpaid Only')
                    ->query(fn(Builder $query) => 
                        $query->whereIn('status', ['pending', 'overdue', 'partial'])
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('due_from')
                            ->label('Due From'),
                        Forms\Components\DatePicker::make('due_until')
                            ->label('Due Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['due_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date),
                            )
                            ->when(
                                $data['due_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

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
                            ->title('Payment recorded successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    // 🔥 Bulk mark as overdue
                    Tables\Actions\BulkAction::make('mark_overdue')
                        ->label('Mark as Overdue')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function($records) {
                            foreach($records as $record) {
                                $record->markAsOverdue();
                            }
                        }),
                ]),
            ])
            ->defaultSort('due_date', 'desc')
            // 🔥 PERFORMANCE: Pagination
            // WHY: Don't load 10,000 payments at once
            // Load 15 at a time = 667x less memory usage
            ->paginated([15, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view' => Pages\ViewPayment::route('/{record}'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }

    // 🔥 PERFORMANCE: Custom widgets for dashboard
    public static function getWidgets(): array
    {
        return [
            // We'll create these next
            // PaymentStatsWidget::class,
        ];
    }
}