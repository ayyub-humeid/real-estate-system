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
use Barryvdh\DomPDF\Facade\Pdf;

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
                'tenant:id,user_id',
                'tenant.user:id,name,email,phone',
                'property:id,name', // Load the property if it's a direct property lease
                'unit:id,unit_number,property_id',
                'unit.property:id,name',
            ])
            ->withCount([
                'payments',
                'payments as paid_payments_count' => fn($q) => $q->where('status', 'paid'),
                'payments as pending_payments_count' => fn($q) => $q->whereIn('status', ['pending', 'overdue']),
                'documents',
            ])
            ->withSum(['payments as total_paid' => fn($q) => $q->where('type', 'rent')->where('status', '!=', 'cancelled')], 'paid_amount');
    }

    /**
     * Reusable company field logic
     */
    private static function companyField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('company_id')
            ->label('Company')
            ->relationship('company', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->visible(fn() => auth()->user()->isSuperAdmin());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Lease Information')
                    ->schema([
                        self::companyField(),

                        Forms\Components\Select::make('property_id')
                            ->label('Property (For Whole Property Lease)')
                            ->relationship('property', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->requiredWithout('unit_id')
                            ->helperText('Select if renting the entire property instead of a single unit.'),

                        Forms\Components\Select::make('unit_id')
                            ->label('Unit (For Specific Unit Lease)')
                            ->requiredWithout('property_id')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->relationship(
                                'unit',
                                'unit_number',
                                function (Builder $query, Forms\Get $get) {
                                    return $query
                                        ->where('status', 'available')
                                        ->when($get('property_id'), fn($q, $id) => $q->where('property_id', $id))
                                        ->when(old('unit_id'), fn($q, $id) => $q->orWhere('id', $id))
                                        ->with('property:id,name');
                                }
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn($record) =>
                                $record->property?->name . ' - Unit ' . $record->unit_number
                            )
                            ->helperText('If renting a single unit, select it here.')
                            ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => self::recalculateRentAmount($get, $set)),
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
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => self::recalculateRentAmount($get, $set)),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date (Optional)')
                            ->helperText('Leave empty for open-ended lease')
                            ->native(false)
                            ->afterOrEqual('start_date')
                            ->live()
                            ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => self::recalculateRentAmount($get, $set)),

                        Forms\Components\TextInput::make('rent_amount')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->minValue(0)
                            ->default(0)
                            ->readOnly()
                            ->helperText('Auto-calculated from unit/property price × lease duration.'),

                        Forms\Components\TextInput::make('deposit_amount')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->minValue(0)
                            ->default(0)
                            ->helperText('A separate deposit record will be created. It does not affect installment amounts.'),
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
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('lease_target')
                    ->label('Property / Unit')
                    ->state(function ($record) {
                        if ($record->unit) {
                            return $record->unit->property->name . ' - Unit ' . $record->unit->unit_number;
                        } elseif ($record->property) {
                            return $record->property->name . ' (Whole Property)';
                        }
                        return 'N/A';
                    })
                    ->searchable(['unit.property.name', 'unit.unit_number', 'property.name'])
                    ->icon('heroicon-m-home-modern')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable()
                    ->placeholder('Open-ended')
                    ->color(fn($record) => $record->is_expired ? 'danger' : 'success')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->icon('heroicon-m-banknotes')
                    ->toggleable(isToggledHiddenByDefault: true),

                // 🔥 WHY: total_paid comes from withSum() - computed in DB, not PHP
                // Much faster than loading all payments and summing in PHP
                Tables\Columns\TextColumn::make('total_paid')
                    ->label('Paid')
                    ->money('USD')
                    ->color('success')
                    ->sortable(),
                // In LeaseResource::table() columns array, after 'total_paid':
                Tables\Columns\IconColumn::make('is_fully_paid')
                    ->label('Settled')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->state(fn($record) => $record->is_fully_paid),

                Tables\Columns\TextColumn::make('outstanding_balance')
                    ->label('Outstanding')
                    ->state(fn($record) => $record->outstanding_balance)
                    ->money('USD')
                    ->color('danger'),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->visible(fn() => auth()->user()->isSuperAdmin()),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn() => auth()->user()->isSuperAdmin()),

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
                // ✅ Export PDF Action
                Tables\Actions\Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function ($record) {
                        return response()->streamDownload(function () use ($record) {
                            // Load lease with all required relationships
                            $lease = \App\Models\Lease::with([
                                'company',
                                'unit.property',
                                'property',
                                'tenant.user',
                                'tenant',
                            ])->find($record->id);

                            // Load company settings
                            $settings = \App\Models\CompanySetting::where('company_id', $lease->company_id)->first();

                            // Generate PDF – A4 portrait, single page optimised
                            $pdf = \PDF::loadView('pdf.lease-contract', [
                                'lease'    => $lease,
                                'settings' => $settings,
                            ])
                            ->setPaper('A4', 'portrait')
                            ->setWarnings(false);

                            echo $pdf->output();
                        }, 'lease-' . str_pad($record->id, 6, '0', STR_PAD_LEFT) . '.pdf');
                    }),

                // 🔥 Custom action - Generate payments
                Tables\Actions\Action::make('generate_payments')
                    ->label('Generate Payments')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $result = $record->generatePaymentSchedule();

                        match ($result) {
                            'created' => \Filament\Notifications\Notification::make()
                                ->title('Payment schedule generated successfully')
                                ->body('Installments have been created.' . (
                                    (float) $record->deposit_amount > 0
                                    ? ' A deposit payment of $' . number_format($record->deposit_amount, 2) . ' was also recorded.'
                                    : ''
                                ))
                                ->success()
                                ->send(),

                            'exists' => \Filament\Notifications\Notification::make()
                                ->title('Schedule already exists')
                                ->body('This lease already has ' . $record->payments()->count() . ' installment(s). Delete existing payments first to regenerate.')
                                ->warning()
                                ->send(),

                            default => \Filament\Notifications\Notification::make()
                                ->title('Cannot generate schedule')
                                ->body('The lease must be in "active" status to generate payments.')
                                ->danger()
                                ->send(),
                        };
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

    public static function recalculateRentAmount(Forms\Get $get, Forms\Set $set): void
    {
        $unitId = $get('unit_id');
        $propertyId = $get('property_id');
        $startDate = $get('start_date');
        $endDate = $get('end_date');

        if (!$startDate || !$endDate || (!$unitId && !$propertyId)) {
            return;
        }

        $rentPrice = 0;
        if ($unitId) {
            $unit = \App\Models\Unit::find($unitId);
            if ($unit && $unit->rent_price) {
                $rentPrice = (float) $unit->rent_price;
            }
        } elseif ($propertyId) {
            $property = \App\Models\Property::find($propertyId);
            if ($property && $property->rent_price) {
                $rentPrice = (float) $property->rent_price;
            }
        }

        if ($rentPrice <= 0) {
            return;
        }

        try {
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);

            // full months between dates (integer)
            $monthsCount = (int) $start->diffInMonths($end);

            // if there is an extra partial month, count it as a whole month
            if ($start->copy()->addMonths($monthsCount)->startOfDay()->lt($end->copy()->startOfDay())) {
                $monthsCount++;
            }

            $monthsCount = max(1, $monthsCount);

            $rentAmount = round($monthsCount * $rentPrice, 2);
            $set('rent_amount', $rentAmount);
        } catch (\Exception $e) {
            // fail silently (or log for debugging)
        }
    }
}
