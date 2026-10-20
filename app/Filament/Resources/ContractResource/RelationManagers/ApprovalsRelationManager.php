<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Enums\ContractApprovalStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ApprovalsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvals';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Contract approvals');
    }

    public static function getModelLabel(): string
    {
        return __('Approvals');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Contract approvals');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('version.version_number')->label(__('Version'))->prefix('V'),
            Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()
                ->formatStateUsing(fn ($state): string => $state instanceof ContractApprovalStatus ? $state->getLabel() : ContractApprovalStatus::tryFrom((string) $state)?->getLabel() ?? (string) $state)
                ->color(fn ($state): ?string => $state instanceof ContractApprovalStatus ? $state->color() : ContractApprovalStatus::tryFrom((string) $state)?->color()),
            Tables\Columns\TextColumn::make('reviewer.name')->label(__('Reviewed by'))->placeholder('—'),
            Tables\Columns\TextColumn::make('reviewed_at')->label(__('Reviewed at'))->dateTime()->placeholder('—'),
            Tables\Columns\TextColumn::make('notes')->label(__('Notes'))->limit(60)->placeholder('—'),
        ])->defaultSort('reviewed_at', 'desc');
    }
}
