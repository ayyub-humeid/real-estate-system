<?php
// app/Filament/Resources/CompanyResource/Pages/ViewCompany.php

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

    // 🔥 Add this to display company info nicely
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Company Information')
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

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('users_count') // سيقرأ مباشرة من withCount
                            ->label('Total Users')
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-m-users'),

                        Infolists\Components\TextEntry::make('active_users_count') // سيقرأ القيمة المحسوبة
                            ->label('Active Staff')
                            ->badge()
                            ->color('success')
                            ->icon('heroicon-m-user-group'),

                        Infolists\Components\TextEntry::make('tenants_count') 
                            ->label('Tenants')
                            ->badge()
                            ->color('gray')
                            ->icon('heroicon-m-user'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Member Since')
                            ->dateTime()
                            ->since() 
                            ->icon('heroicon-m-calendar'),
                    ])
                    ->columns(4)
                    ->collapsible(),
            ]);
    }
}
