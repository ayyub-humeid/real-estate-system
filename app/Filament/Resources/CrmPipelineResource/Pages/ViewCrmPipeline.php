<?php

namespace App\Filament\Resources\CrmPipelineResource\Pages;

use App\Filament\Resources\CrmPipelineResource;
use App\Filament\Resources\CrmPipelineResource\RelationManagers\StagesRelationManager;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewCrmPipeline extends ViewRecord
{
    protected static string $resource = CrmPipelineResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }

    public function getRelationManagers(): array
    {
        return [StagesRelationManager::class];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('name')->label(__('crm.fields.name'))->weight('bold')
                    ->formatStateUsing(fn ($state, $record) => $record->display_name),
                Infolists\Components\IconEntry::make('is_default')->label(__('crm.fields.default'))->boolean(),
                Infolists\Components\IconEntry::make('is_active')->label(__('crm.fields.active'))->boolean(),
                Infolists\Components\TextEntry::make('stages_count')->label(__('crm.navigation.stages'))->state(fn ($record) => $record->stages()->count())->badge(),
            ])->columns(4),
        ]);
    }
}
