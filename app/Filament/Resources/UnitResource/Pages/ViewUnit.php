<?php

namespace App\Filament\Resources\UnitResource\Pages;

use App\Filament\Resources\UnitResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewUnit extends ViewRecord
{
    protected static string $resource = UnitResource::class;

    public function getContentTabLabel(): ?string
    {
        return 'Overview';
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make()
                    ->schema([
                        Infolists\Components\ImageEntry::make('primaryImage.path')
                            ->label('')
                            ->height(400)
                            ->extraImgAttributes([
                                'class' => 'w-full object-cover rounded-xl shadow-2xl border-4 border-white dark:border-gray-800',
                                'style' => 'width: 100%; height: 400px; object-position: center;',
                            ])
                            ->defaultImageUrl('https://images.unsplash.com/photo-1512917774080-9991f1c4c750?q=80&w=2070&auto=format&fit=crop')
                            ->columnSpanFull(),
                    ])->compact(),

                Infolists\Components\Grid::make(3)
                    ->schema([
                        Infolists\Components\Group::make([
                            Infolists\Components\Section::make('Unit Information')
                                ->schema([
                                    Infolists\Components\TextEntry::make('unit_number')
                                        ->label('Unit Number')
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                        ->weight('bold')
                                        ->icon('heroicon-m-hashtag'),

                                    Infolists\Components\TextEntry::make('maintenance_status')
                                        ->label('Maintenance Alerts')
                                        ->default(function ($record) {
                                            $count = $record->maintenanceRequests
                                                ->whereIn('priority', ['high', 'emergency'])
                                                ->whereNotIn('status', ['resolved', 'cancelled'])
                                                ->count();
                                            return $count > 0 ? "$count Urgent Request(s)" : "No Urgent Issues";
                                        })
                                        ->color(fn ($state) => str_contains($state, 'Urgent') ? 'danger' : 'success')
                                        ->icon(fn ($state) => str_contains($state, 'Urgent') ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge')
                                        ->visible(fn ($record) => $record->maintenanceRequests()->exists()),
                                    
                                    Infolists\Components\TextEntry::make('property.name')
                                        ->label('Property')
                                        ->icon('heroicon-m-building-office-2')
                                        ->color('primary')
                                        ->url(fn($record) => route('filament.admin.resources.properties.view', $record->property_id)),

                                    Infolists\Components\TextEntry::make('type')
                                        ->badge()
                                        ->color('gray'),
                                ])->columns(1),

                            Infolists\Components\Section::make('Description')
                                ->schema([
                                    Infolists\Components\TextEntry::make('description')
                                        ->label('')
                                        ->placeholder('No description has been added or generated yet.'),
                                ])
                                ->collapsible(),
                        ])->columnSpan(2),

                        Infolists\Components\Group::make([
                            Infolists\Components\Section::make('Financials & Status')
                                ->schema([
                                    Infolists\Components\TextEntry::make('rent_price')
                                        ->money('USD')
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                        ->color('success')
                                        ->weight('bold'),

                                    Infolists\Components\TextEntry::make('status')
                                        ->badge()
                                        ->colors([
                                            'success' => 'available',
                                            'danger' => 'occupied',
                                            'warning' => 'maintenance',
                                            'info' => 'reserved',
                                        ]),
                                ])->columns(1),

                            Infolists\Components\Section::make('Amenities')
                                ->description('Unit-specific features')
                                ->schema([
                                    Infolists\Components\RepeatableEntry::make('features')
                                        ->label('')
                                        ->schema([
                                            Infolists\Components\TextEntry::make('name')
                                                ->label('')
                                                ->size(Infolists\Components\TextEntry\TextEntrySize::Small)
                                                ->weight('bold')
                                                ->badge()
                                                ->color('info')
                                                ->icon('heroicon-o-check-circle')
                                                ->formatStateUsing(fn ($state, $record) => $record->value ? "$state: {$record->value}" : $state),
                                        ])
                                        ->grid(2)
                                ])
                                ->collapsible()
                                ->visible(fn ($record) => $record->features()->exists()),
                        ])->columnSpan(1),
                    ]),
            ]);
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
