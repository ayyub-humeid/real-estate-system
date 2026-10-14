<?php
namespace App\Filament\Resources\CrmActivityResource\Pages; use App\Filament\Resources\CrmActivityResource; use Filament\Actions; use Filament\Resources\Pages\ListRecords;
class ListCrmActivities extends ListRecords { protected static string $resource=CrmActivityResource::class; protected function getHeaderActions():array{return [Actions\CreateAction::make()->label(__('crm.tasks.actions.log_activity'))];} }
