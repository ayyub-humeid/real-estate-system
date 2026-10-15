<?php
namespace App\Filament\Resources\CrmOfferResource\Pages;
use App\Filament\Resources\CrmOfferResource; use App\Services\OfferNegotiationService; use Filament\Actions; use Filament\Resources\Pages\EditRecord; use Illuminate\Database\Eloquent\Model;
class EditCrmOffer extends EditRecord { protected static string $resource = CrmOfferResource::class; protected function handleRecordUpdate(Model $record, array $data): Model { return app(OfferNegotiationService::class)->updateOffer(auth()->user(), $record, $data); } protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; } }
