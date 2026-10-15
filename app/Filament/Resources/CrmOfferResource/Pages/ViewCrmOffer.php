<?php
namespace App\Filament\Resources\CrmOfferResource\Pages;
use App\Filament\Resources\CrmOfferResource; use App\Filament\Resources\CrmOfferResource\RelationManagers\{OfferApprovalsRelationManager, OfferRevisionsRelationManager}; use Filament\Actions; use Filament\Infolists\Infolist; use Filament\Resources\Pages\ViewRecord;
class ViewCrmOffer extends ViewRecord { protected static string $resource = CrmOfferResource::class; protected function getHeaderActions(): array { return [Actions\EditAction::make()]; } public function getRelationManagers(): array { return [OfferRevisionsRelationManager::class, OfferApprovalsRelationManager::class]; } public function infolist(Infolist $infolist): Infolist { return CrmOfferResource::infolist($infolist); } }
