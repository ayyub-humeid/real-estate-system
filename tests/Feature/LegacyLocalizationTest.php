<?php

namespace Tests\Feature;

use App\Filament\Resources\PropertyResource;
use App\Filament\Resources\UnitResource;
use App\Enums\ListingStatus;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use Tests\TestCase;

class LegacyLocalizationTest extends TestCase
{
    public function test_legacy_resource_navigation_and_controlled_unit_values_are_localized_in_arabic(): void
    {
        app()->setLocale('ar');

        $this->assertSame('العقارات', PropertyResource::getNavigationLabel());
        $this->assertSame('🏠 العقارات', PropertyResource::getNavigationGroup());
        $this->assertSame('الوحدات', UnitResource::getPluralModelLabel());
        $this->assertSame('جاهزة', UnitStatus::Ready->getLabel());
        $this->assertSame('شقة', UnitType::Apartment->getLabel());
        $this->assertSame('منشور', ListingStatus::Published->getLabel());
    }

    public function test_legacy_resource_navigation_and_controlled_unit_values_keep_english_in_english(): void
    {
        app()->setLocale('en');

        $this->assertSame('Properties', PropertyResource::getNavigationLabel());
        $this->assertSame('🏠 Properties', PropertyResource::getNavigationGroup());
        $this->assertSame('Ready', UnitStatus::Ready->getLabel());
        $this->assertSame('Apartment', UnitType::Apartment->getLabel());
        $this->assertSame('Published', ListingStatus::Published->getLabel());
    }
}
