<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RentalRequestResource\Pages;
use App\Models\RentalRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RentalRequestResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = RentalRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = '💼 Operations';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'Rental Request';
    protected static ?string $pluralModelLabel = 'Rental Requests';

    // ✅ PERFORMANCE: Eager load all relationships at once
    public static function shouldRegisterNavigation(): bool
{
    $user = auth()->user();
    if ($user->isSuperAdmin()) return true;
    return $user->company->hasFeature('rental_requests');
}

public static function canViewAny(): bool
{
    $user = auth()->user();
    if ($user->isSuperAdmin()) return true;
    
    return $user->company->hasFeature('rental_requests') 
        && $user->can('view_any_rental::request');
}
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'tenant:id,user_id',
                'tenant.user:id,name,email',
                'unit:id,unit_number,property_id',
                'unit.property:id,name',
                'company:id,name',
                'reviewer:id,name',
            ]);
    }

    /**
     * Reusable company field logic:
     * - super_admin → sees a searchable Select to pick any company
     * - regular admin → hidden field auto-filled with their own company
     */
    private static function companyField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('company_id')
            ->label('Company')
            ->relationship('company', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->visible(fn () => auth()->user()->isSuperAdmin());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([

                // ── Left column (2/3 width) ──────────────────────────────
                Forms\Components\Group::make([

                    Forms\Components\Section::make(__('Request Details'))
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\Textarea::make('description')
                                ->rows(4)
                                ->columnSpanFull(),
                        ])->columns(1),

                    Forms\Components\Section::make(__('Preferred Unit'))
                        ->description(__('What is the tenant looking for?'))
                        ->schema([
                            Forms\Components\Select::make('preferred_type')
                                ->options(function (Forms\Get $get) {
                                    return \App\Models\Unit::query()
                                        ->whereNotNull('type')
                                        ->distinct()
                                        ->pluck('type', 'type')
                                        ->toArray();
                                })
                                ->searchable()
                                ->placeholder(__('Select unit type'))
                                ->native(false),

                            Forms\Components\TextInput::make('max_budget')
                                ->label('Max Budget')
                                ->numeric()
                                ->prefix('$'),

                            Forms\Components\DatePicker::make('desired_move_in')
                                ->native(false)
                                ->minDate(now()),

                            Forms\Components\TextInput::make('duration_months')
                                ->label('Duration (Months)')
                                ->numeric()
                                ->minValue(1),
                        ])->columns(2),

                    Forms\Components\Section::make(__('Admin Response'))
                        ->schema([
                            Forms\Components\Select::make('unit_id')
                                ->label('Assigned Unit (Optional)')
                                ->relationship('unit', 'unit_number', function (Builder $query, Forms\Get $get) {
                                    $query->with('property');
                                    if ($get('company_id')) {
                                        $query->where('company_id', $get('company_id'));
                                    }
                                })
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->property->name} - Unit {$record->unit_number}")
                                ->searchable()
                                ->placeholder(__('Select a unit')),

                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Admin Notes / Response')
                                ->rows(3)
                                ->columnSpanFull(),
                        ])->columns(1),

                ])->columnSpan(2),

                // ── Right column (1/3 width) ─────────────────────────────
                Forms\Components\Group::make([

                    Forms\Components\Section::make(__('Assignment'))
                        ->schema([
                            Forms\Components\Select::make('tenant_id')
                                ->label('Tenant')
                                ->relationship('tenant', 'id')
                                ->getOptionLabelFromRecordUsing(fn ($record) => ($record->user->name ?? 'N/A') . ' (' . ($record->user->email ?? '') . ')')
                                ->required()
                                ->searchable()
                                ->placeholder(__('Select a tenant')),

                            Forms\Components\Select::make('status')
                                ->options([
                                    'pending'   => 'Pending',
                                    'approved'  => 'Approved',
                                    'rejected'  => 'Rejected',
                                    'cancelled' => 'Cancelled',
                                ])
                                ->default('pending')
                                ->required()
                                ->native(false)
                                ->live(),

                            Forms\Components\Select::make('priority')
                                ->options([
                                    'low'    => 'Low',
                                    'medium' => 'Medium',
                                    'high'   => 'High',
                                ])
                                ->default('medium')
                                ->required()
                                ->native(false),

                            // ── Company field (super_admin vs regular) ──
                            self::companyField(),
                        ]),

                ])->columnSpan(1),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.user.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('unit.unit_number')
                    ->label('Assigned Unit')
                    ->placeholder(__('None'))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('preferred_type')
                    ->label('Prefers')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('max_budget')
                    ->money('USD')
                    ->label('Budget'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                        'gray'    => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->colors([
                        'gray'    => 'low',
                        'info'    => 'medium',
                        'danger'  => 'high',
                    ]),

                Tables\Columns\TextColumn::make('desired_move_in')
                    ->date()
                    ->sortable()
                    ->label('Move-in'),

                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'approved'  => 'Approved',
                        'rejected'  => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low'    => 'Low',
                        'medium' => 'Medium',
                        'high'   => 'High',
                    ]),
                Tables\Filters\Filter::make('pending_only')
                    ->label('Pending Only')
                    ->query(fn (Builder $q) => $q->where('status', 'pending'))
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRentalRequests::route('/'),
            'create' => Pages\CreateRentalRequest::route('/create'),
            'view'   => Pages\ViewRentalRequest::route('/{record}'),
            'edit'   => Pages\EditRentalRequest::route('/{record}/edit'),
        ];
    }
}
