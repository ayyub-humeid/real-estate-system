<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationGroup = '👥 People';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'user:id,name,email,phone,company_id,role',
                'company:id,name',
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // SECTION 1: Account Information (saves to users table)
                Forms\Components\Section::make('Account Information')
                    ->description('Login credentials for this employee')
                    ->schema([
                        Forms\Components\TextInput::make('user.name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('user.email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(
                                table: User::class,
                                column: 'email',
                                ignoreRecord: true,
                                modifyRuleUsing: function ($rule, $record) {
                                    return $record ? $rule->ignore($record->user_id) : $rule;
                                }
                            )
                            ->maxLength(255),

                        Forms\Components\TextInput::make('user.phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('user.password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn(string $context) => $context === 'create')
                            ->dehydrated(fn($state) => filled($state))
                            ->minLength(8)
                            ->helperText(
                                fn(string $context) =>
                                $context === 'create'
                                    ? 'Employee will use this to login'
                                    : 'Leave empty to keep current password'
                            ),

                        Forms\Components\Select::make('user.role')
                            ->label('System Role')
                            ->options([
                                'company_admin' => 'Company Admin',
                                'property_manager' => 'Property Manager',
                            ])
                            ->default('property_manager')
                            ->required()
                            ->native(false),

                        // Company assignment (super admin sees selector, others get auto-fill)
                        Forms\Components\Select::make('user.company_id')
                            ->label('Company')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn() => auth()->user()->role === 'super_admin')
                            ->visible(fn() => auth()->user()->role === 'super_admin')
                            ->default(fn() => auth()->user()->company_id ?? null),

                        Forms\Components\Hidden::make('user.company_id')
                            ->default(fn() => auth()->user()->company_id)
                            ->visible(fn() => auth()->user()->role !== 'super_admin')
                            ->dehydrated(),

                        Forms\Components\FileUpload::make('avatar')
                            ->label('Employee Photo')
                            ->image()
                            ->avatar()
                            ->directory('employees-avatars')
                            ->imageEditor(),
                    ])
                    ->columns(2),

                // SECTION 2: Employment Details (saves to employees table)
                Forms\Components\Section::make('Employment Details')
                    ->schema([
                        Forms\Components\TextInput::make('employee_id')
                            ->label('Employee ID')
                            ->placeholder('EMP-001')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('position')
                            ->label('Position / Job Title')
                            ->maxLength(255)
                            ->placeholder('Property Manager, Accountant, etc.'),

                        Forms\Components\Select::make('department')
                            ->options([
                                'management' => 'Management',
                                'operations' => 'Operations',
                                'finance' => 'Finance',
                                'maintenance' => 'Maintenance',
                                'sales' => 'Sales / Leasing',
                                'customer_service' => 'Customer Service',
                                'other' => 'Other',
                            ])
                            ->native(false)
                            ->searchable(),

                        Forms\Components\DatePicker::make('hire_date')
                            ->native(false)
                            ->default(now()),

                        Forms\Components\TextInput::make('salary')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->minValue(0)
                            ->visible(fn() => in_array(auth()->user()->role, ['super_admin', 'company_admin'])),
                    ])
                    ->columns(2),

                // SECTION 3: Emergency Contact
                Forms\Components\Section::make('Emergency Contact')
                    ->schema([
                        Forms\Components\TextInput::make('emergency_contact_name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('emergency_contact_phone')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('emergency_contact_relationship')
                            ->maxLength(255)
                            ->placeholder('Father, Mother, Spouse, etc.'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                // SECTION 4: Status & Notes
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'on_leave' => 'On Leave',
                                'terminated' => 'Terminated',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
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

                Tables\Columns\ImageColumn::make('avatar')
                    ->circular()
                    ->defaultImageUrl(fn($record) => "https://ui-avatars.com/api/?name=" . urlencode($record->user->name ?? 'E') . "&background=6366f1&color=fff"),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),

                Tables\Columns\TextColumn::make('employee_id')
                    ->label('Emp. ID')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('position')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('department')
                    ->badge()
                    ->toggleable()
                    ->formatStateUsing(fn(?string $state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—'),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('hire_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('salary')
                    ->money('USD')
                    ->sortable()
                    ->toggleable()
                    ->visible(fn() => in_array(auth()->user()->role, ['super_admin', 'company_admin'])),

                Tables\Columns\TextColumn::make('user.role')
                    ->label('Role')
                    ->badge()
                    ->colors([
                        'danger' => 'super_admin',
                        'warning' => 'company_admin',
                        'success' => 'property_manager',
                    ])
                    ->formatStateUsing(fn(string $state): string =>
                        str_replace('_', ' ', ucwords($state, '_'))
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                        'warning' => 'on_leave',
                        'danger' => 'terminated',
                    ])
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
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'on_leave' => 'On Leave',
                        'terminated' => 'Terminated',
                    ])
                    ->native(false)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('department')
                    ->options([
                        'management' => 'Management',
                        'operations' => 'Operations',
                        'finance' => 'Finance',
                        'maintenance' => 'Maintenance',
                        'sales' => 'Sales / Leasing',
                        'customer_service' => 'Customer Service',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn() => auth()->user()->role === 'super_admin'),
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
            ->defaultSort('created_at', 'desc')
            ->paginated([15, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'view' => Pages\ViewEmployee::route('/{record}'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
