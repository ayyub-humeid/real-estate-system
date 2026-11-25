<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    public function getTitle(): string
    {
        return __('Contracts');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label(__('Create contract')),
        ];
    }
}
