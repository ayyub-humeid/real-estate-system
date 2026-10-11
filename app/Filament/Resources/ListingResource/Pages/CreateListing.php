<?php

namespace App\Filament\Resources\ListingResource\Pages;

use App\Filament\Resources\ListingResource;
use App\Models\Listing;
use App\Services\ListingService;
use Filament\Resources\Pages\CreateRecord;

class CreateListing extends CreateRecord
{
    protected static string $resource = ListingResource::class;

    protected function handleRecordCreation(array $data): Listing
    {
        return app(ListingService::class)->create(auth()->user(), $data);
    }
}
