<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\Role;
use App\Services\CompanyRoleService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load user relationship data into the form structure
        $data['user'] = [
            'name' => $this->record->user->name,
            'email' => $this->record->user->email,
            'phone' => $this->record->user->phone,
            'role_id' => $this->record->user->roles()->value('roles.id'),
            'company_id' => $this->record->user->company_id,
        ];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            
            if (isset($data['user'])) {
                $userData = [
                    'name' => $data['user']['name'],
                    'email' => $data['user']['email'],
                    'phone' => $data['user']['phone'] ?? null,
                ];
                $roleId = $data['user']['role_id'] ?? null;

                if (!empty($data['user']['password'])) {
                    $userData['password'] = Hash::make($data['user']['password']);
                }

                if (isset($data['user']['company_id'])) {
                    $userData['company_id'] = $data['user']['company_id'];
                    $data['company_id'] = $data['user']['company_id'];
                }

                $record->user->updateQuietly($userData);
                if (! $roleId) {
                    throw new \InvalidArgumentException('A company role is required.');
                }
                app(CompanyRoleService::class)->assignRole($record->user, Role::findOrFail($roleId));
                unset($data['user']);
            }

            $record->update($data);

            return $record;
        });
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

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Employee updated successfully';
    }
}
