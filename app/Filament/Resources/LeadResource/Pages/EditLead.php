<?php
namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Services\LeadIntakeService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(LeadIntakeService::class)->updateLead(auth()->user(), $record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
