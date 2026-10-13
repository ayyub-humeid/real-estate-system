<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Filament\Resources\LeadResource\RelationManagers\{InquiriesRelationManager, OpportunitiesRelationManager};
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }

    public function getRelationManagers(): array
    {
        return [InquiriesRelationManager::class, OpportunitiesRelationManager::class];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('name')->label(__('crm.fields.name'))->weight('bold'),
                Infolists\Components\TextEntry::make('phone')->label(__('crm.fields.phone'))->placeholder('—'),
                Infolists\Components\TextEntry::make('email')->label(__('crm.fields.email'))->placeholder('—'),
                Infolists\Components\TextEntry::make('assignedTo.name')->label(__('crm.fields.owner'))->placeholder(__('crm.kanban.unassigned')),
                Infolists\Components\TextEntry::make('first_source')->label(__('crm.fields.source'))->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\CrmInquirySource ? $state->getLabel() : __('crm.sources.' . $state)),
                Infolists\Components\TextEntry::make('status')->label(__('crm.fields.status'))->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\LeadStatus ? $state->getLabel() : __('crm.lead_status.' . $state)),
            ])->columns(3),
        ]);
    }
}
