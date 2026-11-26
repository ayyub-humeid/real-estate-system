<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Enums\ExpenseStatus;
use App\Filament\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make(__('expense_domain.filters.all'))];

        foreach (ExpenseStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value));
        }

        // The number of unpaid expenses is the one that needs attention.
        $pending = ExpenseResource::getEloquentQuery()->where('status', ExpenseStatus::Pending->value)->count();
        $tabs[ExpenseStatus::Pending->value]->badge($pending ?: null)->badgeColor('warning');

        return $tabs;
    }
}
