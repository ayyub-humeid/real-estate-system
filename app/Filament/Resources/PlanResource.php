<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Filament\Resources\PlanResource\RelationManagers;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlanResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = '🏢 Core';
    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Plan Details'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Plan Name'))
                            ->required()
                            ->maxLength(191)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null),
                        Forms\Components\TextInput::make('slug')
                            ->label(__('Slug'))
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(191)
                            ->unique(Plan::class, 'slug', ignoreRecord: true)
                            ->helperText(__('Auto-generated from the plan name. Cannot be changed manually.'))
                            ->placeholder(__('Will be generated after you type a name...')),
                        Forms\Components\Textarea::make('description')
                            ->label(__('Description'))
                            ->columnSpanFull()
                            ->rows(3),
                        Forms\Components\TextInput::make('price')
                            ->label(__('Price'))
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01),
                        Forms\Components\Select::make('billing_cycle')
                            ->label(__('Billing Cycle'))
                            ->required()
                            ->options([
                                'monthly' => __('Monthly'),
                                'yearly' => __('Yearly'),
                            ])
                            ->default('monthly'),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Active State'))
                            ->default(true)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make(__('Features & Limits'))
                    ->description(__('Set the precise limits and capabilities for this plan. Leave limits empty for "unlimited".'))
                    ->schema([
                        Forms\Components\Group::make()
                            ->statePath('features')
                            ->schema([
                                Forms\Components\Fieldset::make(__('Resource Limits (Count)'))
                                    ->schema([
                                        Forms\Components\TextInput::make('max_properties')
                                            ->label(__('Max Properties'))
                                            ->numeric()
                                            ->placeholder(__('Unlimited')),
                                        Forms\Components\TextInput::make('max_units')
                                            ->label(__('Max Units'))
                                            ->numeric()
                                            ->placeholder(__('Unlimited')),
                                        Forms\Components\TextInput::make('max_employees')
                                            ->label(__('Max Employees'))
                                            ->numeric()
                                            ->placeholder(__('Unlimited')),
                                        Forms\Components\TextInput::make('max_users')
                                            ->label(__('Max Admin Users'))
                                            ->numeric()
                                            ->placeholder(__('Unlimited')),
                                    ])->columns(2),

                                Forms\Components\Fieldset::make(__('Feature Flags (Access)'))
                                    ->schema([
                                        Forms\Components\Toggle::make('maintenance_tracking')
                                            ->label(__('Maintenance Tracking'))
                                            ->default(false),
                                        Forms\Components\Toggle::make('rental_requests')
                                            ->label(__('Rental Requests Portal'))
                                            ->default(false),
                                        Forms\Components\Toggle::make('expense_tracking')
                                            ->label(__('Expense Tracking'))
                                            ->default(false),
                                        Forms\Components\Toggle::make('document_management')
                                            ->label(__('Document Management'))
                                            ->default(false),
                                        Forms\Components\Toggle::make('advanced_reports')
                                            ->label(__('Analytics & Reports'))
                                            ->default(false),
                                        Forms\Components\Toggle::make('export_data')
                                            ->label(__('Data Exporting (PDF/Excel)'))
                                            ->default(false),
                                    ])->columns(3),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Plan Name'))
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('Price'))
                    ->money()
                    ->sortable()
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label(__('Billing Cycle'))
                    ->searchable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'monthly' ? __('Monthly') : ($state === 'yearly' ? __('Yearly') : __($state))),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active State'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateHeading(__('No plans found'))
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Status'))
                    ->placeholder(__('All Plans'))
                    ->trueLabel(__('Active'))
                    ->falseLabel(__('Inactive')),
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
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'view'   => Pages\ViewPlan::route('/{record}'),
            'edit'   => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
