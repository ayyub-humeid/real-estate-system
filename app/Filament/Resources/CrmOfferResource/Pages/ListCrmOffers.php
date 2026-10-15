<?php
namespace App\Filament\Resources\CrmOfferResource\Pages;
use App\Filament\Resources\CrmOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListCrmOffers extends ListRecords { protected static string $resource = CrmOfferResource::class; public function getTitle(): string { return __('crm.offers.navigation'); } protected function getHeaderActions(): array { return [Actions\CreateAction::make()->label(__('crm.offer_modals.create_offer'))]; } }
