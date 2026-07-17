<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Filament\Resources\UnitResource\RelationManagers;
use App\Models\Unit;
use App\Services\PropertyDescriptionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

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

                    Forms\Components\TextInput::make('bedrooms')
                        ->label('Bedrooms')
                        ->numeric()
                        ->minValue(0)
                        ->prefixIcon('heroicon-m-home'),

                    Forms\Components\TextInput::make('bathrooms')
                        ->label('Bathrooms')
                        ->numeric()
                        ->minValue(0)
                        ->prefixIcon('heroicon-m-home'),

                    Forms\Components\TextInput::make('sqft')
                        ->label('Square Feet')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('sqft'),
                        Forms\Components\Toggle::make('is_featured')
                        
                            ->label('Is Featured?')
                            ->default(false)
                    

                ])
                
                ->columns(2),

            Forms\Components\Section::make('Description')
                ->description('Write a description manually or use AI to generate one.')
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->label('Unit Description')
                        ->rows(5)
                        ->placeholder('Describe this unit for potential tenants...')
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            Forms\Components\Section::make('Gallery')
                ->description('Add images for this unit')
                ->schema([
                    Forms\Components\Repeater::make('images')
                        ->relationship('images')
                        ->rules([
                            function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    $primaryCount = collect($value)
                                        ->filter(fn ($item) => !empty($item['is_primary']))
                                        ->count();

                                    if ($primaryCount > 1) {
                                        $fail('Only one image can be set as primary.');
                                    }
                                };
                            },
                        ])
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
                    Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Is Featured')
                    ->sortable()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),


                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('bedrooms')
                    ->label('Beds')
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-home')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('bathrooms')
                    ->label('Baths')
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-home')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sqft')
                    ->label('Sqft')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn (?int $state) => $state ? number_format($state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

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

                // ── AI Description Generator ─────────────────────────────
                Action::make('generateAiDescription')
                    ->label('✨ Generate Description')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->hidden(fn (Unit $record) => ! empty($record->description))
                    ->requiresConfirmation(false)
                    ->modalHeading('AI-Generated Description')
                    ->modalDescription('Review the description below. Click "Approve & Save" to use it, or "Cancel" to keep the existing description.')
                    ->modalWidth('2xl')
                    ->form(function (Unit $record): array {
                        /** @var PropertyDescriptionService $service */
                        $service = app(PropertyDescriptionService::class);

                        try {
                            $generated = $service->generate($record);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('AI Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            $generated = '';
                        }

                        return [
                            Forms\Components\Placeholder::make('ai_preview_label')
                                ->label('')
                                ->content('The AI generated the following description based on the unit details and amenities:'),

                            Forms\Components\Textarea::make('ai_description')
                                ->label('Generated Description')
                                ->default($generated)
                                ->rows(7)
                                ->columnSpanFull()
                                ->readOnly(),
                        ];
                    })
                    ->modalSubmitActionLabel('✅ Approve & Save')
                    ->action(function (Unit $record, array $data): void {
                        if (empty($data['ai_description'])) {
                            Notification::make()
                                ->title('Nothing to save')
                                ->warning()
                                ->send();
                            return;
                        }

                        /** @var PropertyDescriptionService $service */
                        $service = app(PropertyDescriptionService::class);
                        $service->saveDescription($record, $data['ai_description']);

                        Notification::make()
                            ->title('Description saved!')
                            ->body('The AI-generated description has been applied to this unit.')
                            ->success()
                            ->send();
                    }),

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