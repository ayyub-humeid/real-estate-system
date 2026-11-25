<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Exports\ProjectExporter;
use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(ProjectExporter::class)
                ->color('gray'),
            Actions\CreateAction::make(),
        ];
    }
}
