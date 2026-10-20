<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SignaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'signatures';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Contract signatures');
    }

    public static function getModelLabel(): string
    {
        return __('Signatures');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Contract signatures');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('version.version_number')->label(__('Version'))->prefix('V'),
            Tables\Columns\TextColumn::make('party.snapshot.name')->label(__('Party'))->placeholder('—'),
            Tables\Columns\TextColumn::make('signatory_name')->label(__('Signatory name')),
            Tables\Columns\TextColumn::make('signing_method')
                ->label(__('Signing method'))
                ->formatStateUsing(fn ($state): string => match ($state) {
                    'manual' => __('Manual signature'),
                    'electronic' => __('Electronic evidence'),
                    default => (string) $state,
                })
                ->badge(),
            Tables\Columns\ImageColumn::make('signature_image_path')
                ->label(__('Signature image'))
                ->disk(config('filesystems.default'))
                ->height(36),
            Tables\Columns\TextColumn::make('file_sha256')->label(__('File checksum'))->copyable()->limit(16),
            Tables\Columns\TextColumn::make('signed_at')->label(__('Signed at'))->dateTime(),
        ])->defaultSort('signed_at', 'desc');
    }
}
