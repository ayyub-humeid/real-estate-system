<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = '💼 Operations';
    protected static ?int $navigationSort = 5;

    // ✅ PERFORMANCE: Eager load all relationships
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'company:id,name',
                'property:id,name',
                'unit:id,unit_number',
                'creator:id,name',
            ]);
    }

    /**
     * Reusable company field logic:
     * - super_admin → searchable Select
     * - regular admin → auto-filled Hidden
     */
    private static function companyField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('company_id')
            ->label('Company')
            ->relationship('company', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->visible(fn () => auth()->user()->isSuperAdmin());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([

                // ── Left column (2/3) ────────────────────────────────────
                Forms\Components\Group::make([

                    Forms\Components\Section::make('Expense Details')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Forms\Components\Select::make('category')
                                ->options(Expense::CATEGORIES)
                                ->required()
                                ->native(false),

                            Forms\Components\TextInput::make('amount')
                                ->required()
                                ->numeric()
                                ->prefix('$')
                                ->minValue(0),

                            Forms\Components\DatePicker::make('expense_date')
                                ->required()
                                ->default(now())
                                ->native(false),

                            Forms\Components\TextInput::make('reference_number')
                                ->maxLength(100)
                                ->placeholder('Invoice or reference #'),

                            Forms\Components\Textarea::make('description')
                                ->rows(3)
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('notes')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])->columns(2),

                    Forms\Components\Section::make('Property / Unit (Optional)')
                        ->description('Link this expense to a specific property or unit')
                        ->schema([
                            Forms\Components\Select::make('property_id')
                                ->label('Property')
                                ->options(fn (Forms\Get $get) => 
                                    \App\Models\Property::query()
                                        ->where('company_id', $get('company_id') ?? auth()->user()->company_id)
                                        ->pluck('name', 'id')
                                )
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn (Forms\Set $set) => $set('unit_id', null)),

                            Forms\Components\Select::make('unit_id')
                                ->label('Unit')
                                ->options(fn (Forms\Get $get) => 
                                    \App\Models\Unit::query()
                                        ->when($get('property_id'), fn ($q, $id) => $q->where('property_id', $id))
                                        ->unless($get('property_id'), fn ($q) => 
                                            $q->whereHas('property', fn ($qp) => 
                                                $qp->where('company_id', $get('company_id') ?? auth()->user()->company_id)
                                            )
                                        )
                                        ->pluck('unit_number', 'id')
                                )
                                ->searchable()
                                ->preload(),
                        ])->columns(2),

                ])->columnSpan(2),

                // ── Right column (1/3) ───────────────────────────────────
                Forms\Components\Group::make([

                    Forms\Components\Section::make('Payment Info')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->options([
                                    'pending'   => 'Pending',
                                    'paid'      => 'Paid',
                                    'cancelled' => 'Cancelled',
                                ])
                                ->default('pending')
                                ->required()
                                ->native(false)
                                ->live(),

                            Forms\Components\Select::make('payment_method')
                                ->options([
                                    'cash'          => 'Cash',
                                    'bank_transfer' => 'Bank Transfer',
                                    'cheque'        => 'Cheque',
                                    'card'          => 'Card',
                                ])
                                ->native(false)
                                ->visible(fn (Forms\Get $get) => $get('status') === 'paid'),

                            Forms\Components\DatePicker::make('paid_at')
                                ->label('Date Paid')
                                ->native(false)
                                ->visible(fn (Forms\Get $get) => $get('status') === 'paid'),

                            Forms\Components\FileUpload::make('receipt_path')
                                ->label('Receipt')
                                ->image()
                                ->directory('expenses/receipts')
                                ->disk('public'),

                            // ── Company field ──
                            self::companyField(),

                            Forms\Components\Hidden::make('created_by')
                                ->default(fn () => auth()->id()),
                        ]),

                ])->columnSpan(1),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->colors([
                        'warning' => 'maintenance',
                        'info'    => 'utilities',
                        'primary' => 'salaries',
                        'success' => 'insurance',
                        'danger'  => 'taxes',
                        'gray'    => 'other',
                    ]),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money()),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger'  => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('property.name')
                    ->label('Property')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expense_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'paid'      => 'Paid',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('category')
                    ->options(Expense::CATEGORIES),

                Tables\Filters\SelectFilter::make('property')
                    ->relationship('property', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn (Builder $q) => $q->whereMonth('expense_date', now()->month))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('expense_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view'   => Pages\ViewExpense::route('/{record}'),
            'edit'   => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
