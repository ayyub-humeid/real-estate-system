<?php
namespace App\Filament\Resources\ProjectDesignPackageResource\Pages;
use App\Filament\Resources\ProjectDesignPackageResource;
use App\Services\DesignEngineeringService;
use Filament\Resources\Pages\EditRecord;
class EditProjectDesignPackage extends EditRecord { protected static string $resource = ProjectDesignPackageResource::class; protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model { return app(DesignEngineeringService::class)->updatePackage(auth()->user(), $record, $data); } }
