<?php
namespace App\Filament\Resources\CrmFollowUpTaskResource\Pages; use App\Filament\Resources\CrmFollowUpTaskResource; use Filament\Actions; use Filament\Resources\Pages\ListRecords;
class ListCrmFollowUpTasks extends ListRecords { protected static string $resource=CrmFollowUpTaskResource::class; protected function getHeaderActions():array{return [Actions\CreateAction::make()];} }
