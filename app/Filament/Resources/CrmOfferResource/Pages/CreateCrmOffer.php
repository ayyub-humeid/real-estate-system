<?php
namespace App\Filament\Resources\CrmOfferResource\Pages;
use App\Filament\Resources\CrmOfferResource; use App\Models\CrmOffer; use App\Services\OfferNegotiationService; use Filament\Resources\Pages\CreateRecord;
class CreateCrmOffer extends CreateRecord { protected static string $resource = CrmOfferResource::class; public function getTitle(): string { return __('crm.offer_modals.create_offer'); } protected function handleRecordCreation(array $data): CrmOffer { return app(OfferNegotiationService::class)->createOffer(auth()->user(), $data); } }
