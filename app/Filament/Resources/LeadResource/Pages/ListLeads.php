<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Exports\LeadExporter;
use App\Filament\Resources\LeadResource;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(LeadExporter::class)
                ->color('gray'),
            Actions\CreateAction::make(),
        ];
    }
}