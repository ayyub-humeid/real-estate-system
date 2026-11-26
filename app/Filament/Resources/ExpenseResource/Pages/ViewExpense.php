<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Filament\Support\ExpenseActions;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewExpense extends ViewRecord
{
    protected static string $resource = ExpenseResource::class;

    public function getSubheading(): ?string
    {
        return __('expense_domain.guidance.'.$this->getRecord()->status);
    }

    protected function getHeaderActions(): array
    {
        return [
            ExpenseActions::markPaid(Actions\Action::class),
            Actions\EditAction::make(),
            ExpenseActions::cancel(Actions\Action::class),
        ];
    }
}
