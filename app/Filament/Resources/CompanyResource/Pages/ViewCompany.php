<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
    public function getContentTabLabel(): ?string
{
    return 'Overview';
}

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Company Information section
                Infolists\Components\Section::make('Company Information')
                    ->collapsible()
                    ->schema([
                        Infolists\Components\ImageEntry::make('logo')
                            ->circular()
                            ->size(100)
                            ->defaultImageUrl(
                                fn($record) =>
                                'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF'
                            ),

                        Infolists\Components\TextEntry::make('name')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold')
                            ->icon('heroicon-m-building-office'),

                        Infolists\Components\TextEntry::make('email')
                            ->icon('heroicon-m-envelope')
                            ->copyable()
                            ->copyMessage('Email copied!')
                            ->copyMessageDuration(1500),

                        Infolists\Components\TextEntry::make('phone')
                            ->icon('heroicon-m-phone')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('address')
                            ->columnSpanFull()
                            ->placeholder('—'),

                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                    ])
                    ->columns(2),

              // 🔥 Statistics with fallback
            Infolists\Components\Section::make('Statistics')
                ->schema([
                    Infolists\Components\TextEntry::make('users_count')
                        ->label('Total Users')
                        ->badge()
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                        ->color('info')
                        ->icon('heroicon-m-users')
                        ->state(fn($record) => $record->users_count ?? $record->users()->count()), // 🔥 Fallback

                    Infolists\Components\TextEntry::make('active_users_count')
                        ->label('Active Staff')
                        ->badge()
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                        ->color('success')
                        ->icon('heroicon-m-user-group')
                        ->state(fn($record) => $record->active_users_count ?? $record->users()->whereIn('role', ['company_admin', 'property_manager'])->count()), // 🔥 Fallback

                    Infolists\Components\TextEntry::make('tenants_count')
                        ->label('Tenants')
                        ->badge()
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                        ->color('gray')
                        ->icon('heroicon-m-user')
                        ->state(fn($record) => $record->tenants_count ?? $record->users()->where('role', 'tenant')->count()), // 🔥 Fallback

                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Member Since')
                        ->since()
                        ->badge()
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Medium)
                        ->color('warning')
                        ->icon('heroicon-m-calendar'),
                ])
                ->columns(4)
                ->collapsible(),
            ]);
    }

    // 🔥 Enable tabs for relations
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
