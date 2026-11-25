<?php

namespace App\Filament\Resources\UnitResource\Pages;

use App\Filament\Exports\UnitExporter;
use App\Filament\Resources\UnitResource;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListUnits extends ListRecords
{
    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(UnitExporter::class)
                ->color('gray'),
            Actions\CreateAction::make(),
        ];
    }
}
