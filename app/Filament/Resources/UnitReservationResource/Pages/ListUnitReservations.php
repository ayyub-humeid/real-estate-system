<?php
namespace App\Filament\Resources\UnitReservationResource\Pages;
use App\Filament\Resources\UnitReservationResource; use Filament\Actions; use Filament\Resources\Pages\ListRecords;
class ListUnitReservations extends ListRecords { protected static string $resource = UnitReservationResource::class; public function getTitle(): string { return __('reservations.navigation'); } protected function getHeaderActions(): array { return [Actions\CreateAction::make()->label(__('reservations.actions.create'))]; } }
