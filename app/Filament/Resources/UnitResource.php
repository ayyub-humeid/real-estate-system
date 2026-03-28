<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Filament\Resources\UnitResource\RelationManagers;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationGroup = '🏠 Properties';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'property:id,name',
                'primaryImage:id,imageable_type,imageable_id,path',
                'features',
                'maintenanceRequests',
            ]);
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
        return $form->schema([

            Forms\Components\Section::make('Unit Details')
                ->schema([
                    self::companyField(),

                    Forms\Components\Select::make('property_id')
                        ->label('Property')
                        ->relationship('property', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false)
                        ->prefixIcon('heroicon-m-building-office-2'),

                    Forms\Components\TextInput::make('unit_number')
                        ->label('Unit Number')
                        ->required()
                        ->maxLength(50)
                        ->prefixIcon('heroicon-m-hashtag'),

                    Forms\Components\TextInput::make('type')
                        ->label('Type')
                        ->datalist([
                            'Apartment',
                            'Studio',
                            'Villa',
                            'Office',
                            'Shop',
                            'Warehouse',
                        ])
                        ->autocomplete(false)
                        ->prefixIcon('heroicon-m-tag'),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'available'   => 'Available',
                            'occupied'    => 'Occupied',
                            'maintenance' => 'Maintenance',
                            'reserved'    => 'Reserved',
                        ])
                        ->required()
                        ->default('available')
                        ->native(false),

                    Forms\Components\TextInput::make('rent_price')
                        ->label('Rent Price')
                        ->required()
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0),

                ])
                ->columns(2),

            Forms\Components\Section::make('Gallery')
                ->description('Add images for this unit')
                ->schema([
                    Forms\Components\Repeater::make('images')
                        ->relationship('images')
                        ->schema([
                            Forms\Components\FileUpload::make('path')
                                ->label('Photo')
                                ->image()
                                ->imageEditor()
                                ->directory('images/units')
                                ->disk('public')
                                ->maxSize(5120)
                                ->required(),
                            Forms\Components\Toggle::make('is_primary')
                                ->label('Primary')
                                ->default(false),
                            Forms\Components\TextInput::make('order')
                                ->numeric()
                                ->default(0),
                        ])
                        ->grid(2)
                        ->defaultItems(0)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => is_string($state['path'] ?? null) ? $state['path'] : null)
                        ->columnSpanFull(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('property.name')
                    ->label('Property')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-building-office-2'),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->visible(fn () => auth()->user()->isSuperAdmin()),

                Tables\Columns\TextColumn::make('unit_number')
                    ->label('Unit #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—'),

                Tables\Columns\TextColumn::make('rent_price')
                    ->label('Rent')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'available',
                        'danger'  => 'occupied',
                        'warning' => 'maintenance',
                        'info'    => 'reserved',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

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
                    ->visible(fn () => auth()->user()->isSuperAdmin()),

                Tables\Filters\SelectFilter::make('property')
                    ->relationship('property', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'available'   => 'Available',
                        'occupied'    => 'Occupied',
                        'maintenance' => 'Maintenance',
                        'reserved'    => 'Reserved',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'apartment' => 'Apartment',
                        'studio'    => 'Studio',
                        'villa'     => 'Villa',
                        'office'    => 'Office',
                        'shop'      => 'Shop',
                        'warehouse' => 'Warehouse',
                    ])
                    ->native(false),
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
            ->emptyStateHeading('No units yet')
            ->emptyStateDescription('Create your first unit or add one from a property.')
            ->emptyStateIcon('heroicon-o-home');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FeaturesRelationManager::class,
            RelationManagers\MaintenanceRequestsRelationManager::class,
            RelationManagers\ImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'view'   => Pages\ViewUnit::route('/{record}'),
            'edit'   => Pages\EditUnit::route('/{record}/edit'),
        ];
    }
}
