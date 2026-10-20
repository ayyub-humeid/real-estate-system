<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Filament\Resources\PlanResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPlan extends ViewRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('Plan Information'))
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label(__('Plan Name'))
                            ->weight('bold')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                        Infolists\Components\TextEntry::make('slug')
                            ->label(__('Slug'))
                            ->color('gray'),
                        Infolists\Components\TextEntry::make('price')
                            ->label(__('Price'))
                            ->money()
                            ->badge()
                            ->color('success'),
                        Infolists\Components\TextEntry::make('billing_cycle')
                            ->label(__('Billing Cycle'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state === 'monthly' ? __('Monthly') : ($state === 'yearly' ? __('Yearly') : __($state))),
                        Infolists\Components\IconEntry::make('is_active')
                            ->boolean()
                            ->label(__('Active State')),
                        Infolists\Components\TextEntry::make('description')
                            ->label(__('Description'))
                            ->default(__('No Description'))
                            ->color('gray')
                            ->columnSpanFull(),
                    ])->columns(3),

                Infolists\Components\Section::make(__('Resource Limits'))
                    ->description(__('Maximum resource limits defined for this plan. Empty means Unlimited.'))
                    ->schema([
                        Infolists\Components\TextEntry::make('features.max_properties')
                            ->label(__('Max Properties'))
                            ->default(__('Unlimited'))
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('features.max_units')
                            ->label(__('Max Units'))
                            ->default(__('Unlimited'))
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('features.max_employees')
                            ->label(__('Max Employees'))
                            ->default(__('Unlimited'))
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('features.max_users')
                            ->label(__('Max Admin Users'))
                            ->default(__('Unlimited'))
                            ->badge()
                            ->color('info'),
                    ])->columns(4),

                Infolists\Components\Section::make(__('Feature Access'))
                    ->description(__('Modules and capabilities enabled for this plan'))
                    ->schema([
                        Infolists\Components\IconEntry::make('features.maintenance_tracking')
                            ->label(__('Maintenance Tracking'))
                            ->boolean(),
                        Infolists\Components\IconEntry::make('features.rental_requests')
                            ->label(__('Rental Requests Portal'))
                            ->boolean(),
                        Infolists\Components\IconEntry::make('features.expense_tracking')
                            ->label(__('Expense Tracking'))
                            ->boolean(),
                        Infolists\Components\IconEntry::make('features.document_management')
                            ->label(__('Document Management'))
                            ->boolean(),
                        Infolists\Components\IconEntry::make('features.advanced_reports')
                            ->label(__('Analytics & Reports'))
                            ->boolean(),
                        Infolists\Components\IconEntry::make('features.export_data')
                            ->label(__('Data Exporting (PDF/Excel)'))
                            ->boolean(),
                    ])->columns(3),
            ]);
    }
}
