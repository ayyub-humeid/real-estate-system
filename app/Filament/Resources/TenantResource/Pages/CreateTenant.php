<?php
// app/Filament/Resources/TenantResource/Pages/CreateTenant.php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data) {
                // ✅ Validate user data exists
                if (!isset($data['user'])) {
                    throw new \Exception('User data is required');
                }

                // Extract user data
                $userData = $data['user'];
                unset($data['user']);
                
                if (isset($data['company_id'])) {
                    $userData['company_id'] = $data['company_id'];
                }

                // ✅ Hash password
                if (isset($userData['password'])) {
                    $userData['password'] = Hash::make($userData['password']);
                } else {
                    // ✅ Default password if none provided
                    $userData['password'] = Hash::make('password123');
                }

                // ✅ Ensure role is always tenant
                $userData['role'] = 'tenant';

                // ✅ Create User
                $user = \App\Models\User::create($userData);

                // ✅ Link tenant to user and company
                $data['user_id'] = $user->id;
                $data['company_id'] = $user->company_id;

                // ✅ Validate required fields
                if (empty($data['user_id']) || empty($data['company_id'])) {
                    throw new \Exception('User ID or Company ID is missing');
                }

                // ✅ Create Tenant
                $tenant = static::getModel()::create($data);

                // ✅ Log success (helpful for debugging)
                Log::info('Tenant created', [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'company_id' => $user->company_id,
                ]);

                return $tenant;
            });

        } catch (\Exception $e) {
            // ✅ Show error to user
            Notification::make()
                ->title('Error creating tenant')
                ->body($e->getMessage())
                ->danger()
                ->send();

            // ✅ Log error for debugging
            Log::error('Tenant creation failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            // ✅ Re-throw to stop creation
            throw $e;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Tenant created successfully';
    }
}