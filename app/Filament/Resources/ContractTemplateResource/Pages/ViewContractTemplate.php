<?php

namespace App\Filament\Resources\ContractTemplateResource\Pages;

use App\Filament\Resources\ContractTemplateResource;
use Filament\Actions;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewContractTemplate extends ViewRecord
{
    protected static string $resource = ContractTemplateResource::class;

    public function getContentTabLabel(): ?string
    {
        return __('Overview');
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return ContractTemplateResource::infolist($infolist);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()->label(__('Edit'))];
    }
}
