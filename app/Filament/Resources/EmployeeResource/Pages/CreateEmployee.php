<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data) {
                if (!isset($data['user'])) {
                    throw new \Exception('User data is required');
                }

                // Extract user data
                $userData = $data['user'];
                unset($data['user']);

                // Hash password
                if (isset($userData['password'])) {
                    $userData['password'] = Hash::make($userData['password']);
                } else {
                    $userData['password'] = Hash::make('password123');
                }

                // Create User
                $user = \App\Models\User::create($userData);

                // Link employee to user and company
                $data['user_id'] = $user->id;
                $data['company_id'] = $user->company_id;

                if (empty($data['user_id']) || empty($data['company_id'])) {
                    throw new \Exception('User ID or Company ID is missing');
                }

                // Create Employee
                $employee = static::getModel()::create($data);

                Log::info('Employee created', [
                    'employee_id' => $employee->id,
                    'user_id' => $user->id,
                    'company_id' => $user->company_id,
                ]);

                return $employee;
            });

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error creating employee')
                ->body($e->getMessage())
                ->danger()
                ->send();

            Log::error('Employee creation failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Employee created successfully';
    }
}
