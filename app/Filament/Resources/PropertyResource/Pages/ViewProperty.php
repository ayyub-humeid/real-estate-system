<?php

namespace App\Filament\Resources\PropertyResource\Pages;

use App\Filament\Resources\PropertyResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Storage;

class ViewProperty extends ViewRecord
{
    protected static string $resource = PropertyResource::class;

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
                                'style' => 'width: 100%; height: 400px; object-position: center; shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);',
                            ])
                            ->defaultImageUrl('https://images.unsplash.com/photo-1564013799919-ab600027ffc6?q=80&w=2070&auto=format&fit=crop')
                            ->columnSpanFull(),
                    ])->compact(),

                Infolists\Components\Grid::make(3)
                    ->schema([
                        Infolists\Components\Group::make([
                            Infolists\Components\Section::make(__('Basic Information'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')
                                        ->label(__('Name'))
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                        ->weight('bold')
                                        ->icon('heroicon-m-home'),
                                    
                                    Infolists\Components\TextEntry::make('company.name')
                                        ->label(__('Owner Company'))
                                        ->icon('heroicon-m-building-office')
                                        ->color('primary'),

                                    Infolists\Components\TextEntry::make('location.name')
                                        ->label(__('Location'))
                                        ->icon('heroicon-m-map-pin'),

                                    Infolists\Components\TextEntry::make('address')
                                        ->label(__('Address'))
                                        ->icon('heroicon-m-map'),
                                ])->columns(1),
                        ])->columnSpan(2),

                        Infolists\Components\Group::make([
                            Infolists\Components\Section::make(__('Quick Stats'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('units_count')
                                        ->label(__('Total Units'))
                                        ->state(fn($record) => $record->units()->count())
                                        ->badge()
                                        ->color('success')
                                        ->icon('heroicon-m-home-modern'),

                                    Infolists\Components\TextEntry::make('created_at')
                                        ->label(__('Date Added'))
                                        ->date()
                                        ->icon('heroicon-m-calendar'),
                                ])->columns(1),
                        ])->columnSpan(1),
                    ]),

                Infolists\Components\Section::make(__('About Property'))
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->label(__('Description'))
                            ->markdown()
                            ->placeholder(__('No description provided.')),
                    ])->collapsible(),
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
