<?php
// app/Filament/Resources/TenantResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = '👥 People';
    protected static ?int $navigationSort = 1;

    // 🔥 PERFORMANCE: Eager Loading Strategy
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // ✅ WHY: Eager load user relationship to avoid N+1
            // When displaying 15 tenants, without this = 15 extra queries
            // With this = 1 query loads ALL users at once
            ->with([
                'user:id,name,email,phone,company_id', // ✅ Select only needed columns
                'company:id,name', // ✅ Load company name
            ])
            // ✅ WHY: Count leases in database, not PHP
            // Loading all leases = ~10KB per tenant
            // Using withCount = 4 bytes per tenant
            ->withCount([
                'leases',
                'leases as active_leases_count' => fn($q) => $q->where('status', 'active'),
            ])
            // ✅ WHY: Calculate total payments in database
            // Instead of loading all leases + all payments (heavy!)
            // Let database do the aggregation (fast!)
            // ->withSum('leases.payments as total_paid', 'paid_amount')
            ->withSum('payments as total_paid', 'paid_amount');
        ;
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
            ->visible(fn () => auth()->user()->isSuperAdmin());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // 🔥 SECTION 1: Basic Information (saves to users table)
                Forms\Components\Section::make('Basic Information')
                    ->description('This information will be saved in the user account')
                    ->schema([
                        self::companyField(),

                        Forms\Components\TextInput::make('user.name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, ?Tenant $record) {
                                // ✅ WHY: Only set during creation, not edit
                                // Editing user data should update user record, not create new
                                if (!$record && $state) {
                                    $set('user.email', strtolower(str_replace(' ', '.', $state)) . '@example.com');
                                }
                            }),

                        Forms\Components\TextInput::make('user.email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(
                                table: User::class,
                                column: 'email',
                                ignoreRecord: true,
                                modifyRuleUsing: function ($rule, $record) {
                                    // إذا كنا في حالة تعديل، تجاهل إيميل المستخدم الحالي المرتبط بهذا المستأجر
                                    return $record ? $rule->ignore($record->user_id) : $rule;
                                }
                            )->maxLength(255)
                            ->live(debounce: 500)
                            ->afterStateUpdated(
                                fn($state, Forms\Set $set) =>
                                $set('user.email', strtolower($state))
                            ),

                        Forms\Components\TextInput::make('user.phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(255),

                        // 🔥 Password field (only for creation)
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
                                    ? 'Tenant will use this to login'
                                    : 'Leave empty to keep current password'
                            ),

                        Forms\Components\Hidden::make('user.role')
                            ->default('tenant'),

                        Forms\Components\FileUpload::make('avatar')
                            ->label('Tenant Photo')
                            ->image()
                            ->avatar()
                            ->directory('tenants-avatars')
                            ->imageEditor()
                        // ->columnSpanFull(),
                    ])
                    ->columns(2),

                // 🔥 SECTION 2: Employment Information (saves to tenants table)
                Forms\Components\Section::make('Employment Information')
                    ->schema([
                        Forms\Components\TextInput::make('employer_name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('job_title')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('employer_phone')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('monthly_income')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->minValue(0),

                        Forms\Components\Textarea::make('employer_address')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('employment_start_date')
                            ->native(false)
                            ->maxDate(now()),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // 🔥 SECTION 3: Emergency Contact
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

                // 🔥 SECTION 4: Previous Address
                Forms\Components\Section::make('Previous Address')
                    ->schema([
                        Forms\Components\Textarea::make('previous_address')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('previous_landlord_name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('previous_landlord_phone')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('previous_tenancy_start')
                            ->native(false),

                        Forms\Components\DatePicker::make('previous_tenancy_end')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // 🔥 SECTION 5: Identification
                Forms\Components\Section::make('Identification')
                    ->schema([
                        Forms\Components\Select::make('id_type')
                            ->options([
                                'national_id' => 'National ID',
                                'passport' => 'Passport',
                                'driver_license' => 'Driver License',
                            ])
                            ->native(false),

                        Forms\Components\TextInput::make('id_number')
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('id_expiry_date')
                            ->native(false)
                            ->minDate(now()),
                    ])
                    ->columns(3)
                    ->collapsible(),

                // 🔥 SECTION 6: Move-in Details
                Forms\Components\Section::make('Move-in Details')
                    ->schema([
                        Forms\Components\DatePicker::make('move_in_date')
                            ->native(false),

                        Forms\Components\TextInput::make('number_of_occupants')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(10),

                        Forms\Components\Toggle::make('has_pets')
                            ->label('Has Pets')
                            ->live(),

                        Forms\Components\Textarea::make('pet_details')
                            ->label('Pet Details')
                            ->visible(fn(Forms\Get $get) => $get('has_pets'))
                            ->rows(2)
                            ->columnSpanFull()
                            ->placeholder('Type, breed, size, etc.'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                // 🔥 SECTION 7: References (Repeater for multiple references)
                Forms\Components\Section::make('References')
                    ->schema([
                        Forms\Components\Repeater::make('references')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('relationship')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Add Reference')
                            ->collapsible()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // 🔥 SECTION 8: Background Check
                Forms\Components\Section::make('Background Check')
                    ->schema([
                        Forms\Components\Select::make('background_check_status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'not_required' => 'Not Required',
                            ])
                            ->default('pending')
                            ->native(false)
                            ->live(),

                        Forms\Components\DatePicker::make('background_check_date')
                            ->visible(
                                fn(Forms\Get $get) =>
                                in_array($get('background_check_status'), ['approved', 'rejected'])
                            )
                            ->native(false),

                        Forms\Components\Textarea::make('background_check_notes')
                            ->visible(
                                fn(Forms\Get $get) =>
                                in_array($get('background_check_status'), ['approved', 'rejected'])
                            )
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // 🔥 SECTION 9: Status & Notes
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'blacklisted' => 'Blacklisted',
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
                    ->defaultImageUrl(fn($record) => "https://ui-avatars.com/api/?name=" . urlencode($record->user->name) . "&background=0D8ABC&color=fff"),

                // 🔥 WHY: user.name is already eager loaded - zero extra queries
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

                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone')
                    ->searchable()
                    ->icon('heroicon-m-phone')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('monthly_income')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('move_in_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                // 🔥 WHY: leases_count from withCount() - NO extra query
                Tables\Columns\TextColumn::make('leases_count')
                    ->label('Leases')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-document-text'),

                // 🔥 WHY: active_leases_count from withCount() - NO extra query
                Tables\Columns\TextColumn::make('active_leases_count')
                    ->label('Active')
                    ->badge()
                    ->color('success'),

                // 🔥 WHY: total_paid from withSum() - computed in database
                Tables\Columns\TextColumn::make('total_paid')
                    ->label('Total Paid')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('background_check_status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'gray' => 'not_required',
                    ])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                        'danger' => 'blacklisted',
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
                        'blacklisted' => 'Blacklisted',
                    ])
                    ->native(false)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('background_check_status')
                    ->label('Background Check')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->native(false),

                Tables\Filters\Filter::make('has_active_lease')
                    ->label('Has Active Lease')
                   ->query(fn($q) => $q->whereHas('leases', fn($q) => $q->where('status', 'active'))) // ✅ direct

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
            ->defaultSort('created_at', 'desc')
            ->paginated([15, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            // We'll add LeaseRelationManager later
        TenantResource\RelationManagers\PaymentsRelationManager::class,
        TenantResource\RelationManagers\LeasesRelationManager::class,

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
