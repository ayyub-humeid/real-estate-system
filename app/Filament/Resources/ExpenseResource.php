<?php

namespace App\Filament\Resources;

use App\Enums\Currency;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Filament\Exports\ExpenseExporter;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Filament\Support\ExpenseActions;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = Expense::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = '💼 Operations';
    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('expense_domain.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('expense_domain.plural_label');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) return true;

        return (bool) $user->company?->hasFeature('expense_tracking')
            && $user->can('view_any_expense');
    }

    // ✅ PERFORMANCE: Eager load all relationships
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'company:id,name',
                'property:id,name',
                'unit:id,unit_number',
                'employee.user:id,name',
                'party:id,name',
                'creator:id,name',
            ]);
    }

    /** The company the expense is recorded for: chosen by a super admin, otherwise the user's own. */
    private static function companyId(Forms\Get $get): ?int
    {
        $id = $get('company_id') ?: auth()->user()->company_id;

        return $id ? (int) $id : null;
    }

    private static function defaultCurrency(?int $companyId): string
    {
        $currency = $companyId
            ? CompanySetting::withoutGlobalScopes()->where('company_id', $companyId)->value('currency')
            : null;

        return Currency::tryFrom((string) $currency)?->value ?? Currency::USD->value;
    }

    /** @return array<int, string> */
    private static function employeeOptions(?int $companyId): array
    {
        return Employee::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->with('user:id,name')
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [
                $employee->id => collect([$employee->full_name, $employee->position])->filter()->implode(' · '),
            ])
            ->all();
    }

    public static function form(Form $form): Form
    {
        // Figures of a paid or cancelled expense are history and cannot be edited.
        $locked = fn (?Expense $record): bool => (bool) $record?->isLocked();
        $isPaid = fn (Forms\Get $get): bool => $get('status') === ExpenseStatus::Paid->value;
        $isSalary = fn (Forms\Get $get): bool => $get('category') === ExpenseCategory::Salaries->value;

        return $form->schema([
            Forms\Components\Grid::make(3)->schema([

                // ── Left column (2/3) ────────────────────────────────────
                Forms\Components\Group::make([

                    Forms\Components\Section::make(__('expense_domain.sections.details'))
                        ->schema([
                            Forms\Components\Select::make('company_id')
                                ->label(__('Company'))
                                ->relationship('company', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state): void {
                                    foreach (['property_id', 'unit_id', 'employee_id', 'party_id'] as $field) {
                                        $set($field, null);
                                    }
                                    $set('currency', static::defaultCurrency($state ? (int) $state : null));
                                })
                                ->visible(fn () => auth()->user()->isSuperAdmin())
                                ->disabled($locked)
                                ->columnSpanFull(),

                            Forms\Components\Select::make('category')
                                ->label(__('expense_domain.fields.category'))
                                ->options(ExpenseCategory::options())
                                ->required()
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->disabled($locked),

                            Forms\Components\TextInput::make('title')
                                ->label(__('expense_domain.fields.title'))
                                ->required()
                                ->maxLength(255)
                                ->disabled($locked),

                            Forms\Components\Select::make('employee_id')
                                ->label(__('expense_domain.fields.employee_id'))
                                ->options(fn (Forms\Get $get): array => static::employeeOptions(static::companyId($get)))
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->visible($isSalary)
                                ->required()
                                ->helperText(__('expense_domain.help.employee'))
                                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state): void {
                                    $employee = $state ? Employee::withoutGlobalScopes()->with('user')->find($state) : null;

                                    if (! $employee) {
                                        return;
                                    }

                                    if ($employee->salary) {
                                        $set('amount', $employee->salary);
                                    }

                                    if (blank($get('title'))) {
                                        $set('title', __('expense_domain.salary_title', ['name' => $employee->full_name]));
                                    }
                                })
                                ->disabled($locked),

                            Forms\Components\DatePicker::make('salary_month')
                                ->label(__('expense_domain.fields.salary_month'))
                                ->displayFormat('m/Y')
                                ->default(now()->startOfMonth()->toDateString())
                                ->visible($isSalary)
                                ->required()
                                ->helperText(__('expense_domain.help.salary_month'))
                                ->disabled($locked),

                            Forms\Components\Select::make('party_id')
                                ->label(__('expense_domain.fields.party_id'))
                                ->relationship('party', 'name', fn (Builder $query, Forms\Get $get) => $query->where('company_id', static::companyId($get)))
                                ->searchable()
                                ->preload()
                                ->visible(fn (Forms\Get $get): bool => ! $isSalary($get))
                                ->helperText(__('expense_domain.help.party'))
                                ->disabled($locked),

                            Forms\Components\TextInput::make('amount')
                                ->label(__('expense_domain.fields.amount'))
                                ->required()
                                ->numeric()
                                ->minValue(0.01)
                                ->disabled($locked),

                            Forms\Components\Select::make('currency')
                                ->label(__('expense_domain.fields.currency'))
                                ->options(Currency::options())
                                ->default(fn (): string => static::defaultCurrency(auth()->user()->company_id))
                                ->required()
                                ->native(false)
                                ->disabled($locked),

                            Forms\Components\DatePicker::make('expense_date')
                                ->label(__('expense_domain.fields.expense_date'))
                                ->required()
                                ->default(now()->toDateString())
                                ->disabled($locked),

                            Forms\Components\TextInput::make('reference_number')
                                ->label(__('expense_domain.fields.reference_number'))
                                ->maxLength(100),

                            Forms\Components\Textarea::make('description')
                                ->label(__('expense_domain.fields.description'))
                                ->rows(3)
                                ->disabled($locked)
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('notes')
                                ->label(__('expense_domain.fields.notes'))
                                ->rows(2)
                                ->columnSpanFull(),
                        ])->columns(2),

                    Forms\Components\Section::make(__('expense_domain.sections.location'))
                        ->description(__('expense_domain.help.location'))
                        ->schema([
                            Forms\Components\Select::make('property_id')
                                ->label(__('expense_domain.fields.property_id'))
                                ->relationship('property', 'name', fn (Builder $query, Forms\Get $get) => $query->where('company_id', static::companyId($get)))
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn (Forms\Set $set) => $set('unit_id', null))
                                ->disabled($locked),

                            Forms\Components\Select::make('unit_id')
                                ->label(__('expense_domain.fields.unit_id'))
                                ->relationship('unit', 'unit_number', fn (Builder $query, Forms\Get $get) => $query
                                    ->with('property')
                                    ->where('company_id', static::companyId($get))
                                    ->when($get('property_id'), fn ($query, $id) => $query->where('property_id', $id)))
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->displayName())
                                ->searchable()
                                ->preload()
                                ->disabled($locked),
                        ])->columns(2),

                ])->columnSpan(2),

                // ── Right column (1/3) ───────────────────────────────────
                Forms\Components\Group::make([

                    Forms\Components\Section::make(__('expense_domain.sections.payment'))
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->label(__('expense_domain.fields.status'))
                                ->options(fn (string $operation): array => $operation === 'create'
                                    ? ExpenseStatus::creationOptions()
                                    : ExpenseStatus::options())
                                ->default(ExpenseStatus::Pending->value)
                                ->required()
                                ->native(false)
                                ->live()
                                ->helperText(__('expense_domain.help.status'))
                                // Afterwards the status changes only through "Mark as paid" and "Cancel".
                                ->disabledOn(['edit', 'view']),

                            Forms\Components\Select::make('payment_method')
                                ->label(__('expense_domain.fields.payment_method'))
                                ->options(PaymentMethod::options())
                                ->native(false)
                                ->required($isPaid)
                                ->visible(fn (Forms\Get $get, ?Expense $record): bool => $isPaid($get) || filled($record?->payment_method))
                                ->disabled($locked),

                            Forms\Components\DatePicker::make('paid_at')
                                ->label(__('expense_domain.fields.paid_at'))
                                ->default(now()->toDateString())
                                ->maxDate(now()->endOfDay())
                                ->required($isPaid)
                                ->visible(fn (Forms\Get $get, ?Expense $record): bool => $isPaid($get) || filled($record?->paid_at))
                                ->disabled($locked),

                            Forms\Components\Textarea::make('cancellation_reason')
                                ->label(__('expense_domain.fields.cancellation_reason'))
                                ->visible(fn (?Expense $record): bool => filled($record?->cancellation_reason))
                                ->disabled(),

                            Forms\Components\FileUpload::make('receipt_path')
                                ->label(__('expense_domain.fields.receipt_path'))
                                ->acceptedFileTypes(['image/*', 'application/pdf'])
                                ->maxSize(5120)
                                ->openable()
                                ->downloadable()
                                ->directory('expenses/receipts'),
                        ]),

                ])->columnSpan(1),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->label(__('expense_domain.fields.expense_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label(__('expense_domain.fields.title'))
                    ->description(fn (Expense $record): ?string => $record->employee
                        ? $record->employee->full_name.($record->salary_month ? ' · '.$record->salary_month->format('m/Y') : '')
                        : $record->party?->name)
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('category')
                    ->label(__('expense_domain.fields.category'))
                    ->badge()
                    ->color(fn (?string $state): string => ExpenseCategory::tryFrom((string) $state)?->getColor() ?? 'gray')
                    ->formatStateUsing(fn (?string $state): string => ExpenseCategory::labelFor($state)),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('expense_domain.fields.amount'))
                    ->money(fn (Expense $record): string => $record->currency)
                    ->sortable()
                    // Currencies are never added together, and cancelled expenses do not count.
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()
                        ->label(__('expense_domain.fields.total'))
                        ->using(fn (\Illuminate\Database\Query\Builder $query): string => $query
                            ->where('status', '!=', ExpenseStatus::Cancelled->value)
                            ->selectRaw('currency, SUM(amount) as total')
                            ->groupBy('currency')
                            ->orderBy('currency')
                            ->get()
                            ->map(fn (object $row): string => number_format((float) $row->total, 2).' '.$row->currency)
                            ->implode(' | '))),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('expense_domain.fields.status'))
                    ->badge()
                    ->color(fn (?string $state): string => ExpenseStatus::tryFrom((string) $state)?->getColor() ?? 'gray')
                    ->formatStateUsing(fn (?string $state): string => ExpenseStatus::labelFor($state)),

                Tables\Columns\TextColumn::make('property.name')
                    ->label(__('expense_domain.fields.property_id'))
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unit.unit_number')
                    ->label(__('expense_domain.fields.unit_id'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Created By'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('expense_domain.fields.category'))
                    ->options(ExpenseCategory::options())
                    ->multiple(),

                Tables\Filters\SelectFilter::make('property')
                    ->label(__('expense_domain.fields.property_id'))
                    ->relationship('property', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('expense_period')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label(__('expense_domain.filters.from')),
                        Forms\Components\DatePicker::make('until')->label(__('expense_domain.filters.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('expense_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('expense_date', '<=', $date))),

                Tables\Filters\Filter::make('this_month')
                    ->label(__('expense_domain.filters.this_month'))
                    ->query(fn (Builder $query): Builder => $query->whereBetween('expense_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()->label(__('export.action'))->exporter(ExpenseExporter::class)->color('gray')->authorize(fn (): bool => ExpenseExporter::canExport()),
            ])
            ->actions([
                ExpenseActions::markPaid(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    ExpenseActions::cancel(),
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Expense $record): bool => $record->status === ExpenseStatus::Pending->value),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('expense_domain.empty.heading'))
            ->emptyStateDescription(__('expense_domain.empty.description'))
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
