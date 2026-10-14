<?php
namespace App\Filament\Resources\CrmActivityResource\Pages; use App\Filament\Resources\CrmActivityResource; use App\Services\CrmTaskService; use Filament\Resources\Pages\CreateRecord;
class CreateCrmActivity extends CreateRecord { protected static string $resource=CrmActivityResource::class; protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model{return app(CrmTaskService::class)->logActivity(auth()->user(),$data);} }
