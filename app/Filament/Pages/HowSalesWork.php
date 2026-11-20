<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class HowSalesWork extends Page
{
    protected static string $view = 'filament.pages.how-sales-work';

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return __('crm.guide.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return '💼 '.__('crm.navigation.crm');
    }

    public function getTitle(): string
    {
        return __('crm.guide.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->hasAnyPermission([
            'view_any_lead', 'view_any_opportunity', 'view_any_crm::offer', 'view_any_unit::reservation', 'view_any_contract',
        ]);
    }
}
