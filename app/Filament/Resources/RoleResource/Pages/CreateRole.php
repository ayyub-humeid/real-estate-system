<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Models\Role;
use App\Services\CompanyRoleService;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            throw ValidationException::withMessages(['company_id' => __('A company context is required.')]);
        }
        return Arr::only($data, ['name', 'guard_name', 'company_id']);
    }

    /** Create the role and its selected permissions as one operation. */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data): Model {
                $record = new ($this->getModel())($data);
                $record->save();
                app(CompanyRoleService::class)->syncPermissions(auth()->user(), $record, $this->permissions);

                return $record;
            });
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('Cannot create role'))
                ->body(collect($exception->errors())->flatten()->first())
                ->persistent()
                ->send();

            throw $exception;
        }
    }
}
