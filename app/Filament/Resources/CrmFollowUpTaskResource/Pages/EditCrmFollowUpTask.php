<?php
namespace App\Filament\Resources\CrmFollowUpTaskResource\Pages; use App\Filament\Resources\CrmFollowUpTaskResource; use App\Services\CrmTaskService; use Filament\Resources\Pages\EditRecord; use Illuminate\Database\Eloquent\Model;
class EditCrmFollowUpTask extends EditRecord { protected static string $resource=CrmFollowUpTaskResource::class; protected function handleRecordUpdate(Model $record,array $data):Model{return app(CrmTaskService::class)->update(auth()->user(),$record,$data);} }
