<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    use SurfacesExpenseErrors;

    protected static string $resource = ExpenseResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return $this->saveOrExplain(fn () => parent::handleRecordCreation($data));
    }

    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        $paid = $this->getRecord()->status === \App\Enums\ExpenseStatus::Paid->value;

        return \Filament\Notifications\Notification::make()
            ->success()
            ->title(__('expense_domain.notifications.created'))
            ->body(__('expense_domain.notifications.'.($paid ? 'created_paid_body' : 'created_pending_body')));
    }
}
