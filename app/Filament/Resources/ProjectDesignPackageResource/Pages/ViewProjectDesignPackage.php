<?php
namespace App\Filament\Resources\ProjectDesignPackageResource\Pages;
use App\Filament\Resources\ProjectDesignPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
class ViewProjectDesignPackage extends ViewRecord { protected static string $resource = ProjectDesignPackageResource::class; protected function getHeaderActions(): array { return [Actions\EditAction::make()]; } }
