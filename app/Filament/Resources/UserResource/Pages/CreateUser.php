<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Role;
use App\Services\CompanyRoleService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
    protected ?int $roleId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        if (! $this->roleId) throw new \InvalidArgumentException('A role is required.');

        return $data;
    }

    protected function afterCreate(): void
    {
        app(CompanyRoleService::class)->assignSelectedRole($this->record, Role::findOrFail($this->roleId), auth()->user());
    }
      protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
      public function mount(): void
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->company?->canAddUser()) {
            Notification::make()
                ->title('Limit Reached')
                ->body('Your plan does not allow adding more users.')
                ->danger()
                ->persistent()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
            return;
        }

        parent::mount();
    }
}
