<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Services\CompanyRoleService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /** Persist role metadata and permission assignments atomically. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return DB::transaction(function () use ($record, $data): Model {
                $record->update($data);
                app(CompanyRoleService::class)->syncPermissions(auth()->user(), $record, $this->permissions);

                return $record;
            });
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('Cannot save role permissions'))
                ->body(collect($exception->errors())->flatten()->first())
                ->persistent()
                ->send();

            throw $exception;
        }
    }
}
