<?php
namespace App\Filament\Resources\CrmPipelineResource\Pages;
use App\Filament\Resources\CrmPipelineResource; use App\Services\CrmPipelineService; use Filament\Actions; use Filament\Resources\Pages\EditRecord; use Illuminate\Database\Eloquent\Model;
class EditCrmPipeline extends EditRecord { protected static string $resource=CrmPipelineResource::class; protected function handleRecordUpdate(Model $record,array $data): Model { return app(CrmPipelineService::class)->update(auth()->user(),$record,$data); } protected function getHeaderActions():array{return[Actions\DeleteAction::make()];}}
