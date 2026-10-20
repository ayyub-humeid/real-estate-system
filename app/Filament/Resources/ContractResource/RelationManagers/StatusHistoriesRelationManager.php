<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Enums\ContractStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Status history');
    }

    public static function getModelLabel(): string
    {
        return __('Status history');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Status history');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('from_status')->label(__('From status'))
                ->formatStateUsing(fn (?string $state): string => $state ? (ContractStatus::tryFrom($state)?->getLabel() ?? __(ucwords(str_replace('_', ' ', $state)))) : '—')
                ->placeholder('—'),
            Tables\Columns\TextColumn::make('to_status')->label(__('To status'))
                ->formatStateUsing(fn (string $state): string => ContractStatus::tryFrom($state)?->getLabel() ?? __(ucwords(str_replace('_', ' ', $state))))
                ->badge(),
            Tables\Columns\TextColumn::make('action')->label(__('Action'))
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'submit' => __('Submit for review'),
                    'approve' => __('Approve version'),
                    'request_revision' => __('Request revision'),
                    'create_revision' => __('Create revision'),
                    'attach_document' => __('Attach legal document'),
                    'record_signature' => __('Record signature'),
                    'activate' => __('Activate contract'),
                    'create_amendment' => __('Create amendment'),
                    'create' => __('Create contract'),
                    'update' => __('Update contract'),
                    default => __(ucwords(str_replace('_', ' ', $state))),
                }),
            Tables\Columns\TextColumn::make('actor.name')->label(__('Changed by'))->placeholder('—'),
            Tables\Columns\TextColumn::make('changed_at')->label(__('Changed at'))->dateTime(),
        ])->defaultSort('changed_at', 'desc');
    }
}
