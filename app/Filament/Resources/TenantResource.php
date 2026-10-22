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
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = Tenant::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = '👥 People';
    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('tenant.resource.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tenant.resource.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return '👥 '.__('tenant.resource.navigation');
    }

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
            ;
        ;
    }

    /**
     * Reusable company field logic
     */
    private static function companyField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('company_id')
            ->label(__('tenant.fields.company'))
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
                Forms\Components\Section::make(__('tenant.sections.basic'))
                    ->description(__('tenant.help.saved_to_user'))
                    ->schema([
                        self::companyField(),

                        Forms\Components\TextInput::make('user.name')
                            ->label(__('tenant.fields.full_name'))
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
                            ->label(__('tenant.fields.email'))
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
                            ->label(__('tenant.fields.phone'))
                            ->tel()
                            ->maxLength(255),

                        // 🔥 Password field (only for creation)
                        Forms\Components\TextInput::make('user.password')
                            ->label(__('tenant.fields.password'))
                            ->password()
                            ->revealable()
                            ->required(fn(string $context) => $context === 'create')
                            ->dehydrated(fn($state) => filled($state))
                            ->minLength(8)
                            ->helperText(
                                fn(string $context) =>
                                $context === 'create'
                                    ? __('tenant.help.password_create')
                                    : __('tenant.help.password_keep')
                            ),

                        Forms\Components\FileUpload::make('avatar')
                            ->label(__('tenant.fields.photo'))
                            ->image()
                            ->avatar()
                            ->directory('tenants-avatars')
                            ->imageEditor()
                        // ->columnSpanFull(),
                    ])
                    ->columns(2),

                // 🔥 SECTION 2: Employment Information (saves to tenants table)
                Forms\Components\Section::make(__('tenant.sections.employment'))
                    ->schema([
                        Forms\Components\TextInput::make('employer_name')
                            ->label(__('tenant.fields.employer_name'))
                            ->maxLength(255),

                        Forms\Components\TextInput::make('job_title')
                            ->label(__('tenant.fields.job_title'))
                            ->maxLength(255),

                        Forms\Components\TextInput::make('employer_phone')
                            ->label(__('tenant.fields.employer_phone'))
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('monthly_income')
                            ->label(__('tenant.fields.monthly_income'))
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->minValue(0),

                        Forms\Components\Textarea::make('employer_address')
                            ->label(__('tenant.fields.employer_address'))
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('employment_start_date')
                            ->label(__('tenant.fields.employment_start'))
                            ->native(false)
                            ->maxDate(now()),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // 🔥 SECTION 3: Emergency Contact
                Forms\Components\Section::make(__('tenant.sections.emergency'))
                    ->schema([
                        Forms\Components\TextInput::make('emergency_contact_name')
                            ->label(__('tenant.fields.contact_name'))
                            ->maxLength(255),

                        Forms\Components\TextInput::make('emergency_contact_phone')
                            ->label(__('tenant.fields.contact_phone'))
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('emergency_contact_relationship')
                            ->label(__('tenant.fields.relationship'))
                            ->maxLength(255)
                            ->placeholder(__('tenant.help.relationship_placeholder')),
                    ])
                    ->columns(3)
                    ->collapsible(),

                // 🔥 SECTION 4: Previous Address
                Forms\Components\Section::make(__('tenant.sections.previous_address'))
                    ->schema([
                        Forms\Components\Textarea::make('previous_address')
                            ->label(__('tenant.fields.previous_address'))
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('previous_landlord_name')
                            ->label(__('tenant.fields.previous_landlord'))
                            ->maxLength(255),

                        Forms\Components\TextInput::make('previous_landlord_phone')
                            ->label(__('tenant.fields.landlord_phone'))
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('previous_tenancy_start')
                            ->label(__('tenant.fields.tenancy_start'))
                            ->native(false),

                        Forms\Components\DatePicker::make('previous_tenancy_end')
                            ->label(__('tenant.fields.tenancy_end'))
                            ->native(false),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // 🔥 SECTION 5: Identification
                Forms\Components\Section::make(__('tenant.sections.identification'))
                    ->schema([
                        Forms\Components\Select::make('id_type')
                            ->label(__('tenant.fields.id_type'))
                            ->options([
                                'national_id' => __('tenant.id_types.national_id'),
                                'passport' => __('tenant.id_types.passport'),
                                'driver_license' => __('tenant.id_types.driver_license'),
                            ])
                            ->native(false),

                        Forms\Components\TextInput::make('id_number')
                            ->label(__('tenant.fields.id_number'))
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('id_expiry_date')
                            ->label(__('tenant.fields.id_expiry'))
                            ->native(false)
                            ->minDate(now()),
                    ])
                    ->columns(3)
                    ->collapsible(),

                // 🔥 SECTION 6: Move-in Details
                Forms\Components\Section::make(__('tenant.sections.move_in'))
                    ->schema([
                        Forms\Components\DatePicker::make('move_in_date')
                            ->label(__('tenant.fields.move_in_date'))
                            ->native(false),

                        Forms\Components\TextInput::make('number_of_occupants')
                            ->label(__('tenant.fields.occupants'))
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(10),

                        Forms\Components\Toggle::make('has_pets')
                            ->label(__('tenant.fields.has_pets'))
                            ->live(),

                        Forms\Components\Textarea::make('pet_details')
                            ->label(__('tenant.fields.pet_details'))
                            ->visible(fn(Forms\Get $get) => $get('has_pets'))
                            ->rows(2)
                            ->columnSpanFull()
                            ->placeholder(__('tenant.help.pet_placeholder')),
                    ])
                    ->columns(3)
                    ->collapsible(),

                // 🔥 SECTION 7: References (Repeater for multiple references)
                Forms\Components\Section::make(__('tenant.sections.references'))
                    ->schema([
                        Forms\Components\Repeater::make('references')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('tenant.fields.name'))
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('relationship')
                                    ->label(__('tenant.fields.relationship'))
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('phone')
                                    ->label(__('tenant.fields.phone'))
                                    ->tel()
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('email')
                                    ->label(__('tenant.fields.email'))
                                    ->email()
                                    ->maxLength(255),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel(__('tenant.actions.add_reference'))
                            ->collapsible()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // 🔥 SECTION 8: Background Check
                Forms\Components\Section::make(__('tenant.sections.background_check'))
                    ->schema([
                        Forms\Components\Select::make('background_check_status')
                            ->label(__('tenant.fields.status'))
                            ->options([
                                'pending' => __('tenant.statuses.pending'),
                                'approved' => __('tenant.statuses.approved'),
                                'rejected' => __('tenant.statuses.rejected'),
                                'not_required' => __('tenant.statuses.not_required'),
                            ])
                            ->default('pending')
                            ->native(false)
                            ->live(),

                        Forms\Components\DatePicker::make('background_check_date')
                            ->label(__('tenant.fields.check_date'))
                            ->visible(
                                fn(Forms\Get $get) =>
                                in_array($get('background_check_status'), ['approved', 'rejected'])
                            )
                            ->native(false),

                        Forms\Components\Textarea::make('background_check_notes')
                            ->label(__('tenant.fields.notes'))
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
                Forms\Components\Section::make(__('tenant.sections.additional'))
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label(__('tenant.fields.status'))
                            ->options([
                                'active' => __('tenant.statuses.active'),
                                'inactive' => __('tenant.statuses.inactive'),
                                'blacklisted' => __('tenant.statuses.blacklisted'),
                            ])
                            ->default('active')
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('tenant.fields.notes'))
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
                    ->label(__('ID'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\ImageColumn::make('avatar')
                    ->circular()
                    ->defaultImageUrl(fn($record) => "https://ui-avatars.com/api/?name=" . urlencode($record->user->name) . "&background=0D8ABC&color=fff"),

                // 🔥 WHY: user.name is already eager loaded - zero extra queries
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('tenant.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('user.email')
                    ->label(__('tenant.fields.email'))
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),

                Tables\Columns\TextColumn::make('user.phone')
                    ->label(__('tenant.fields.phone'))
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
                    ->label(__('tenant.fields.leases'))
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-document-text'),

                // 🔥 WHY: active_leases_count from withCount() - NO extra query
                Tables\Columns\TextColumn::make('active_leases_count')
                    ->label(__('tenant.fields.active_leases'))
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('background_check_status')
                    ->label(__('tenant.sections.background_check'))
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'gray' => 'not_required',
                    ])
                    ->formatStateUsing(fn (?string $state): string => $state ? __('tenant.statuses.'.$state) : __('—'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('tenant.fields.status'))
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                        'danger' => 'blacklisted',
                    ])
                    ->formatStateUsing(fn (?string $state): string => $state ? __('tenant.statuses.'.$state) : __('—'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('tenant.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => __('tenant.statuses.active'),
                        'inactive' => __('tenant.statuses.inactive'),
                        'blacklisted' => __('tenant.statuses.blacklisted'),
                    ])
                    ->native(false)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('background_check_status')
                    ->label(__('tenant.sections.background_check'))
                    ->options([
                        'pending' => __('tenant.statuses.pending'),
                        'approved' => __('tenant.statuses.approved'),
                        'rejected' => __('tenant.statuses.rejected'),
                    ])
                    ->native(false),

                Tables\Filters\Filter::make('has_active_lease')
                    ->label(__('tenant.fields.active_leases'))
                    ->query(fn (Builder $query) => $query->hasActiveLease())
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
