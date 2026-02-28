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
    protected static ?string $model = RentalRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = '💼 Operations';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'Rental Request';
    protected static ?string $pluralModelLabel = 'Rental Requests';

    // ✅ PERFORMANCE: Eager load all relationships at once
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
            ->visible(fn () => auth()->user()->isSuperAdmin());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([

                // ── Left column (2/3 width) ──────────────────────────────
                Forms\Components\Group::make([

                    Forms\Components\Section::make('Request Details')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\Textarea::make('description')
                                ->rows(4)
                                ->columnSpanFull(),
                        ])->columns(1),

                    Forms\Components\Section::make('Preferred Unit')
                        ->description('What is the tenant looking for?')
                        ->schema([
                            Forms\Components\Select::make('preferred_type')
                                ->options(function (Forms\Get $get) {
                                    $companyId = $get('company_id') ?? auth()->user()->company_id;
                                    
                                    if (! $companyId) {
                                        return [];
                                    }

                                    return \App\Models\Unit::query()
                                        ->whereHas('property', fn ($q) => $q->where('company_id', $companyId))
                                        ->whereNotNull('type')
                                        ->distinct()
                                        ->pluck('type', 'type')
                                        ->toArray();
                                })
                                ->searchable()
                                ->placeholder('Select unit type')
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

                    Forms\Components\Section::make('Admin Response')
                        ->schema([
                            Forms\Components\Select::make('unit_id')
                                ->label('Assigned Unit (Optional)')
                                ->options(fn (Forms\Get $get) => 
                                    \App\Models\Unit::query()
                                        ->whereHas('property', fn ($q) => 
                                            $q->where('company_id', $get('company_id') ?? auth()->user()->company_id)
                                        )
                                        ->with('property')
                                        ->get()
                                        ->mapWithKeys(fn ($u) => [$u->id => "{$u->property->name} - Unit {$u->unit_number}"])
                                )
                                ->searchable()
                                ->placeholder('Select a unit'),

                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Admin Notes / Response')
                                ->rows(3)
                                ->columnSpanFull(),
                        ])->columns(1),

                ])->columnSpan(2),

                // ── Right column (1/3 width) ─────────────────────────────
                Forms\Components\Group::make([

                    Forms\Components\Section::make('Assignment')
                        ->schema([
                            Forms\Components\Select::make('tenant_id')
                                ->label('Tenant')
                                ->options(fn (Forms\Get $get) => 
                                    \App\Models\Tenant::query()
                                        ->where('company_id', $get('company_id') ?? auth()->user()->company_id)
                                        ->with('user')
                                        ->get()
                                        ->mapWithKeys(fn ($t) => [
                                            $t->id => ($t->user->name ?? 'N/A') . ' (' . ($t->user->email ?? '') . ')'
                                        ])
                                )
                                ->required()
                                ->searchable()
                                ->placeholder('Select a tenant'),

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
                    ->placeholder('None')
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
