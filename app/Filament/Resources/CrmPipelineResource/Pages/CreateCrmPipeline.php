<?php
namespace App\Filament\Resources\CrmPipelineResource\Pages;
use App\Filament\Resources\CrmPipelineResource; use App\Models\CrmPipeline; use App\Services\CrmPipelineService; use Filament\Resources\Pages\CreateRecord;
class CreateCrmPipeline extends CreateRecord { protected static string $resource=CrmPipelineResource::class; protected function handleRecordCreation(array $data): CrmPipeline { return app(CrmPipelineService::class)->create(auth()->user(), $data); } }
