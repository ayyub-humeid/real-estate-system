<?php
// app/Filament/Resources/LeaseResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaseResource\Pages;
use App\Filament\Resources\LeaseResource\RelationManagers;
use App\Models\Lease;
use App\Models\Unit;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeaseResource extends Resource
{
    protected static ?string $model = Lease::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = '💼 Operations';
    protected static ?int $navigationSort = 1;

    // 🔥 PERFORMANCE: Eager load relationships to avoid N+1
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'tenant:id,user_id',                   // ✅ Only the FK needed
                'tenant.user:id,name,email,phone',      // ✅ User data from correct table
                'unit:id,unit_number,property_id',
                'unit.property:id,name',
            ])
            ->withCount([
                'payments', // ✅ Count without loading all payments
                'payments as paid_payments_count' => fn($q) => $q->where('status', 'paid'),
                'payments as pending_payments_count' => fn($q) => $q->whereIn('status', ['pending', 'overdue']),
                'documents', // ✅ Document count
            ])
            ->withSum('payments as total_paid', 'paid_amount') // ✅ Sum in DB, not PHP
            ->withSum('payments as total_outstanding', 'remaining_amount');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Lease Information')
                    ->schema([
                        Forms\Components\Select::make('unit_id')
                            ->label('Unit')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->relationship(
                                'unit',
                                'unit_number',
                                // 🔥 PERFORMANCE: Only load available units + eager load property
                                function (Builder $query) {
                                    return $query
                                        ->where('status', 'available')
                                        ->when(
                                            old('unit_id'),
                                            fn ($q, $id) => $q->orWhere('id', $id)
                                        )
                                        ->with('property:id,name');
                                }
                            )
                            ->getOptionLabelFromRecordUsing(fn($record) => 
                                $record->property->name . ' - Unit ' . $record->unit_number
                            )
                            ->helperText('Only available units are shown'),

                        Forms\Components\Select::make('tenant_id')
                            ->label('Tenant')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->relationship(
                                'tenant',
                                'id',
                                fn(Builder $query) => $query->with('user:id,name,email')
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn($record) => ($record->user->name ?? 'N/A') . ' (' . ($record->user->email ?? '') . ')'
                            ),

                        Forms\Components\DatePicker::make('start_date')
                            ->required()
                            ->default(now())
                            ->native(false),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date (Optional)')
                            ->helperText('Leave empty for open-ended lease')
                            ->native(false)
                            ->afterOrEqual('start_date'),

                        Forms\Components\TextInput::make('rent_amount')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->minValue(0)
                            ->default(0),

                        Forms\Components\TextInput::make('deposit_amount')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->minValue(0)
                            ->default(0),

                        Forms\Components\Select::make('payment_frequency')
                            ->required()
                            ->options([
                                'monthly' => 'Monthly',
                                'quarterly' => 'Quarterly',
                                'semi_annually' => 'Semi-Annually',
                                'yearly' => 'Yearly',
                            ])
                            ->default('monthly')
                            ->native(false),

                        Forms\Components\Select::make('payment_day')
                            ->label('Payment Day of Month')
                            ->options(array_combine(range(1, 28), range(1, 28)))
                            ->default(1)
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'expired' => 'Expired',
                                'terminated' => 'Terminated',
                                'renewed' => 'Renewed',
                            ])
                            ->default('draft')
                            ->native(false)
                            ->live(),

                        Forms\Components\DatePicker::make('termination_date')
                            ->visible(fn(Forms\Get $get) => $get('status') === 'terminated')
                            ->required(fn(Forms\Get $get) => $get('status') === 'terminated')
                            ->native(false),

                        Forms\Components\Textarea::make('termination_reason')
                            ->visible(fn(Forms\Get $get) => $get('status') === 'terminated')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('special_terms')
                            ->rows(3)
                            ->columnSpanFull(),
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
                    ->searchable()
                    ->toggleable(),

                // 🔥 WHY: We use unit.property.name because it's ALREADY eager loaded
                // No extra query - data is in memory from getEloquentQuery()
                Tables\Columns\TextColumn::make('unit.property.name')
                    ->label('Property')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-building-office-2')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unit.unit_number')
                    ->label('Unit')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-home')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('tenant.user.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('rent_amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable()
                    ->placeholder('Open-ended')
                    ->color(fn($record) => $record->is_expired ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'active',
                        'danger' => 'expired',
                        'warning' => 'terminated',
                        'info' => 'renewed',
                    ])
                    ->sortable(),

                // 🔥 WHY: payments_count comes from withCount() - NO extra query
                // It's a single aggregation in the main query
                Tables\Columns\TextColumn::make('payments_count')
                    ->label('Payments')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-banknotes'),

                // 🔥 WHY: total_paid comes from withSum() - computed in DB, not PHP
                // Much faster than loading all payments and summing in PHP
                Tables\Columns\TextColumn::make('total_paid')
                    ->label('Paid')
                    ->money('USD')
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_outstanding')
                    ->label('Outstanding')
                    ->money('USD')
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'terminated' => 'Terminated',
                    ])
                    ->native(false)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('unit.property_id')
                    ->label('Property')
                    ->relationship('unit.property', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring Soon (30 days)')
                    ->query(fn(Builder $query) => $query->expiringSoon(30))
                    ->toggle(),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn(Builder $query) => $query->expired())
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                // 🔥 Custom action - Generate payments
                Tables\Actions\Action::make('generate_payments')
                    ->label('Generate Payments')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function($record) {
                        $record->generatePaymentSchedule();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Payment schedule generated')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            // 🔥 PERFORMANCE: Paginate instead of loading all
            ->paginated([15, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeases::route('/'),
            'create' => Pages\CreateLease::route('/create'),
            'view' => Pages\ViewLease::route('/{record}'),
            'edit' => Pages\EditLease::route('/{record}/edit'),
        ];
    }
}