<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyResource\Pages;
use App\Filament\Resources\PropertyResource\RelationManagers;
use App\Models\Property;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PropertyResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = Property::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = '🏠 Properties';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'company:id,name',
                'location:id,name',
                'primaryImage:id,imageable_type,imageable_id,path',
            ])
            ->withCount('units');
    }

    /**
     * Reusable company field logic
     */
    private static function companyField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('company_id')
            ->label(__('Company'))
            ->relationship('company', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->visible(fn () => auth()->user()->isSuperAdmin());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make(__('Property Information'))
                ->description(__('Basic details about the property'))
                ->schema([
                    self::companyField(),

                    Forms\Components\Select::make('location_id')
                        ->label(__('Location'))
                        ->relationship('location', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false)
                        ->prefixIcon('heroicon-m-map-pin'),

                    Forms\Components\TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->prefixIcon('heroicon-m-home'),

                    Forms\Components\Select::make('type')
                        ->label(__('Property Type'))
                        ->options([
                            'Land' => __('Land'),
                            'Apartment' => __('Apartment'),
                            'Villa' => __('Villa'),
                            'House' => __('House'),
                            'Commercial' => __('Commercial'),
                            'Other' => __('Other'),
                        ])
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('space')
                        ->label(__('Space / Area'))
                        ->numeric()
                        ->suffix('m²'),

                    Forms\Components\TextInput::make('rent_price')
                        ->label(__('Rent Price for Whole Property (Optional)'))
                        ->numeric()
                        ->prefix('$')
                        ->maxValue(99999999.99)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('address')
                        ->label(__('Address'))
                        ->required()
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label(__('Description'))
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Gallery'))
                ->description(__('Add images for this property'))
                ->schema([
                    Forms\Components\Repeater::make('images')
                        ->label(__('Gallery'))
                        ->relationship('images')
                        ->rules([
                            function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    $primaryCount = collect($value)
                                        ->filter(fn ($item) => !empty($item['is_primary']))
                                        ->count();

                                    if ($primaryCount > 1) {
                                        $fail(__('Only one image can be set as primary.'));
                                    }
                                };
                            },
                        ])
                        ->schema([
                            Forms\Components\FileUpload::make('path')
                                ->label(__('Photo'))
                                ->image()
                                ->imageEditor()
                                ->directory('images/properties')
                                ->maxSize(5120)
                                ->required(),
                            Forms\Components\Toggle::make('is_primary')
                                ->label(__('Primary'))
                                ->default(false),
                            Forms\Components\TextInput::make('order')
                                ->label(__('Order'))
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
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-building-office-2'),

                Tables\Columns\TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('Property Type'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'Land' => __('Land'),
                        'Apartment' => __('Apartment'),
                        'Villa' => __('Villa'),
                        'House' => __('House'),
                        'Commercial' => __('Commercial'),
                        'Other' => __('Other'),
                        default => $state ?? '—',
                    }),

                Tables\Columns\TextColumn::make('space')
                    ->label(__('Space'))
                    ->numeric()
                    ->suffix(' m²')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('location.name')
                    ->label(__('Location'))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-map-pin'),

                Tables\Columns\TextColumn::make('rent_price')
                    ->label(__('Rent Price'))
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('units_count')
                    ->label(__('Units'))
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('address')
                    ->label(__('Address'))
                    ->limit(40)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company')
                    ->label(__('Company'))
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('location')
                    ->label(__('Location'))
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\Filter::make('with_units')
                    ->label(__('Properties with Units'))
                    ->query(fn (Builder $query) => $query->withUnits())
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
            ->emptyStateHeading(__('No properties yet'))
            ->emptyStateDescription(__('Create your first property to get started.'))
            ->emptyStateIcon('heroicon-o-building-office-2');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UnitsRelationManager::class,
            RelationManagers\ImagesRelationManager::class,
            RelationManagers\OwnershipsRelationManager::class,
            RelationManagers\PropertyAcquisitionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProperties::route('/'),
            'create' => Pages\CreateProperty::route('/create'),
            'view'   => Pages\ViewProperty::route('/{record}'),
            'edit'   => Pages\EditProperty::route('/{record}/edit'),
        ];
    }
}
