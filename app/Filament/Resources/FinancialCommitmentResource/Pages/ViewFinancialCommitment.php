<?php

namespace App\Filament\Resources\FinancialCommitmentResource\Pages;

use App\Filament\Resources\FinancialCommitmentResource;
use App\Filament\Resources\FinancialCommitmentResource\RelationManagers\FinancialCommitmentAmendmentsRelationManager;
use Filament\Resources\Pages\ViewRecord;

class ViewFinancialCommitment extends ViewRecord
{
    protected static string $resource = FinancialCommitmentResource::class;

    public static function getRelations(): array
    {
        return [
            FinancialCommitmentAmendmentsRelationManager::class,
        ];
    }
}
