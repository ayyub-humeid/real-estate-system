<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Models\Role;
use App\Services\CompanyRoleService;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;
    protected Collection $permissions;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->permissions = collect($data)->except(['name', 'guard_name', 'company_id', 'select_all'])->flatten()->filter()->unique()->values();
        if (! auth()->user()->isSuperAdmin()) {
            $data['company_id'] = auth()->user()->company_id;
        }
        $data['guard_name'] = 'web';
        if (! auth()->user()->isSuperAdmin() && ! $data['company_id']) {
            throw ValidationException::withMessages(['company_id' => 'A company context is required.']);
        }
        return Arr::only($data, ['name', 'guard_name', 'company_id']);
    }

    protected function afterCreate(): void
    {
        app(CompanyRoleService::class)->syncPermissions(auth()->user(), $this->record, $this->permissions);
    }
}
