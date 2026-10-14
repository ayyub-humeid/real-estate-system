<?php
namespace App\Filament\Resources\CrmFollowUpTaskResource\Pages; use App\Filament\Resources\CrmFollowUpTaskResource; use App\Services\CrmTaskService; use Filament\Resources\Pages\CreateRecord;
class CreateCrmFollowUpTask extends CreateRecord { protected static string $resource=CrmFollowUpTaskResource::class; protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model{return app(CrmTaskService::class)->create(auth()->user(),$data);} }
