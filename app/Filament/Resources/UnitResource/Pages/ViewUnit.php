<?php

namespace App\Filament\Resources\UnitResource\Pages;

use App\Filament\Resources\UnitResource;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewUnit extends ViewRecord
{
    protected static string $resource = UnitResource::class;

    public function getContentTabLabel(): ?string
    {
        return __('Overview');
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
                            Infolists\Components\Section::make(__('Unit Information'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('unit_number')
                                        ->label(__('Unit Number'))
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                        ->weight('bold')
                                        ->icon('heroicon-m-hashtag'),

                                    Infolists\Components\TextEntry::make('maintenance_status')
                                        ->label(__('Maintenance Alerts'))
                                        ->default(function ($record) {
                                            $count = $record->maintenanceRequests
                                                ->whereIn('priority', ['high', 'emergency'])
                                                ->whereNotIn('status', ['resolved', 'cancelled'])
                                                ->count();
                                            return $count > 0 ? __(':count Urgent Request(s)', ['count' => $count]) : __('No Urgent Issues');
                                        })
                                        ->color(fn ($state) => str_contains($state, 'Urgent') || str_contains($state, 'عاجل') ? 'danger' : 'success')
                                        ->icon(fn ($state) => str_contains($state, 'Urgent') || str_contains($state, 'عاجل') ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge')
                                        ->visible(fn ($record) => $record->maintenanceRequests()->exists()),
                                    
                                    Infolists\Components\TextEntry::make('property.name')
                                        ->label(__('Property'))
                                        ->icon('heroicon-m-building-office-2')
                                        ->color('primary')
                                        ->url(fn($record) => route('filament.admin.resources.properties.view', $record->property_id)),

                                    Infolists\Components\TextEntry::make('type')
                                        ->label(__('Type'))
                                        ->badge()
                                        ->color('gray')
                                        ->formatStateUsing(fn (?string $state) => $state ? UnitType::tryFrom($state)?->getLabel() ?? $state : __('—')),
                                ])->columns(1),

                            Infolists\Components\Section::make(__('Description'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('description')
                                        ->label('')
                                        ->placeholder(__('No description has been added or generated yet.')),
                                ])
                                ->collapsible(),
                        ])->columnSpan(2),

                        Infolists\Components\Group::make([
                            Infolists\Components\Section::make(__('Financials & Status'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('rent_price')
                                        ->label(__('Legacy rent reference'))
                                        ->money('USD')
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                        ->color('success')
                                        ->weight('bold')
                                        ->visible(fn ($record): bool => $record->rent_price !== null),

                                    Infolists\Components\TextEntry::make('status')
                                        ->label(__('Status'))
                                        ->badge()
                                        ->colors([
                                            'gray' => 'draft',
                                            'success' => 'ready',
                                            'warning' => 'maintenance',
                                            'danger' => 'inactive',
                                        ])
                                        ->formatStateUsing(fn (?string $state) => $state ? UnitStatus::tryFrom($state)?->getLabel() ?? $state : __('—')),
                                ])->columns(1),

                            Infolists\Components\Section::make(__('Amenities'))
                                ->description(__('Unit-specific features'))
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
