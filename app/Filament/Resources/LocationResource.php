<?php
// app/Filament/Resources/LocationResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LocationResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = Location::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = '🏢 Core';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Location Details'))
                    ->description(__('Define hierarchical location structure'))
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Location Type')
                            ->required()
                            ->options([
                                'country' => '🌍 Country',
                                'city' => '🏙️ City',
                                'district' => '🏘️ District',
                                'neighborhood' => '🏡 Neighborhood',
                            ])
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('parent_id', null)),
                        
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Location')
                            ->relationship(
                                'parent', 
                                'name',
                                modifyQueryUsing: function ($query, Forms\Get $get) {
                                    $currentType = $get('type');
                                    
                                    return match($currentType) {
                                        'city' => $query->where('type', 'country'),
                                        'district' => $query->where('type', 'city'),
                                        'neighborhood' => $query->where('type', 'district'),
                                        default => $query->whereNull('id'),
                                    };
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->visible(fn (Forms\Get $get) => $get('type') !== 'country')
                            ->required(fn (Forms\Get $get) => $get('type') !== 'country')
                            ->helperText(fn (Forms\Get $get) => match($get('type')) {
                                'city' => '📍 Select a country',
                                'district' => '📍 Select a city',
                                'neighborhood' => '📍 Select a district',
                                default => '',
                            }),
                        
                        Forms\Components\TextInput::make('name')
                            ->label('Location Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => 
                                $set('name', ucwords($state))
                            )
                            ->columnSpanFull(),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('latitude')
                                    ->numeric()
                                    ->step(0.00000001)
                                    ->minValue(-90)
                                    ->maxValue(90)
                                    ->placeholder(__('31.95522260'))
                                    ->live(debounce: 500)
                                    ->suffixIcon('heroicon-m-map-pin'),
                                
                                Forms\Components\TextInput::make('longitude')
                                    ->numeric()
                                    ->step(0.00000001)
                                    ->minValue(-180)
                                    ->maxValue(180)
                                    ->placeholder(__('35.30732750'))
                                    ->live(debounce: 500)
                                    ->suffixIcon('heroicon-m-map-pin'),
                            ]),
                        
                        Forms\Components\Placeholder::make('map_preview')
                            ->label('📍 Location on Map')
                            ->content(function (Forms\Get $get) {
                                $lat = $get('latitude');
                                $lng = $get('longitude');
                                
                                if (!$lat || !$lng) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="text-gray-400 text-sm">Enter coordinates to see map preview</div>'
                                    );
                                }
                                
                                return new \Illuminate\Support\HtmlString("
                                    <iframe 
                                        width='100%' 
                                        height='300' 
                                        frameborder='0' 
                                        scrolling='no' 
                                        src='https://www.openstreetmap.org/export/embed.html?bbox=" . 
                                        ($lng - 0.01) . "%2C" . ($lat - 0.01) . "%2C" . 
                                        ($lng + 0.01) . "%2C" . ($lat + 0.01) . 
                                        "&layer=mapnik&marker={$lat}%2C{$lng}' 
                                        style='border: 1px solid #e5e7eb; border-radius: 8px;'>
                                    </iframe>
                                    <div class='mt-2 text-sm text-gray-600'>
                                        📌 Coordinates: {$lat}, {$lng}
                                    </div>
                                ");
                            })
                            ->visible(fn (Forms\Get $get) => 
                                filled($get('latitude')) && filled($get('longitude'))
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_path')
                    ->label('Location')
                    ->searchable(['name'])
                    ->sortable()
                    ->icon('heroicon-m-map-pin')
                    ->weight('medium')
                    ->wrap(),
                
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'danger' => 'country',
                        'warning' => 'city',
                        'success' => 'district',
                        'info' => 'neighborhood',
                    ])
                    ->icons([
                        'country' => 'heroicon-o-globe-alt',
                        'city' => 'heroicon-o-building-office-2',
                        'district' => 'heroicon-o-building-office',
                        'neighborhood' => 'heroicon-o-home',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                
                Tables\Columns\TextColumn::make('latitude')
                    ->toggleable()
                    ->placeholder(__('—'))
                    ->copyable()
                    ->icon('heroicon-m-map-pin'),
                
                Tables\Columns\TextColumn::make('longitude')
                    ->toggleable()
                    ->placeholder(__('—'))
                    ->copyable()
                    ->icon('heroicon-m-map-pin'),
                
                Tables\Columns\TextColumn::make('children_count')
                    ->counts('children')
                    ->label('Sub-locations')
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-o-folder'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'country' => 'Country',
                        'city' => 'City',
                        'district' => 'District',
                        'neighborhood' => 'Neighborhood',
                    ])
                    ->multiple()
                    ->native(false),
                
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent Location')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
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
            ->emptyStateHeading(__('No locations yet'))
            ->emptyStateDescription(__('Start by creating a country, then add cities and districts.'))
            ->emptyStateIcon('heroicon-o-map-pin')
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'view' => Pages\ViewLocation::route('/{record}'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}