<?php
// app/Filament/Resources/UserResource/Pages/EditUser.php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Role;
use App\Services\CompanyRoleService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
    protected ?int $roleId = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role_id'] = $this->record->roles()->value('roles.id');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        if (! $this->roleId) throw new \InvalidArgumentException('A role is required.');

        return $data;
    }

    protected function afterSave(): void
    {
        app(CompanyRoleService::class)->assignSelectedRole($this->record, Role::findOrFail($this->roleId), auth()->user());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->updateQuietly($data);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
