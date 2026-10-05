<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Services\CompanyRoleService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;
    protected Collection $permissions;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(CompanyRoleService::class)->assertRoleManageable(auth()->user(), $this->record);
        $this->permissions = collect($data)->except(['name', 'guard_name', 'company_id', 'select_all'])->flatten()->filter()->unique()->values();
        if (! auth()->user()->isSuperAdmin()) {
            $data['company_id'] = $this->record->company_id;
        }
        $data['guard_name'] = 'web';
        return Arr::only($data, ['name', 'guard_name', 'company_id']);
    }

    protected function afterSave(): void
    {
        app(CompanyRoleService::class)->syncPermissions(auth()->user(), $this->record, $this->permissions);
    }
}
