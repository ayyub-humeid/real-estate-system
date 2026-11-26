<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpense extends EditRecord
{
    use SurfacesExpenseErrors;

    protected static string $resource = ExpenseResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return $this->saveOrExplain(fn () => parent::handleRecordUpdate($record, $data));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->status === \App\Enums\ExpenseStatus::Pending->value),
        ];
    }
}
