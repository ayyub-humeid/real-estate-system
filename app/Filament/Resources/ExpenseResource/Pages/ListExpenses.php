<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Exports\ExpenseExporter;
use App\Filament\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(ExpenseExporter::class)
                ->color('gray'),
            Actions\CreateAction::make(),
        ];
    }
}
