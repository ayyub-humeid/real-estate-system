<?php

namespace App\Filament\Resources\UnitReservationResource\RelationManagers;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class ReservationStatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('reservations.tabs.history');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('from_status')
                    ->label(__('reservations.fields.from_status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ReservationStatus ? $state->getLabel() : ($state ? __('reservations.statuses.'.$state) : '—')),
                Tables\Columns\TextColumn::make('to_status')
                    ->label(__('reservations.fields.to_status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ReservationStatus ? $state->getLabel() : __('reservations.statuses.'.$state))
                    ->color(fn ($state) => $state instanceof ReservationStatus ? $state->color() : ReservationStatus::tryFrom((string) $state)?->color()),
                Tables\Columns\TextColumn::make('action')->label(__('reservations.fields.action'))->formatStateUsing(fn (string $state) => __('reservations.history_actions.'.$state)),
                Tables\Columns\TextColumn::make('changedBy.name')->label(__('reservations.fields.changed_by'))->placeholder('—'),
                Tables\Columns\TextColumn::make('reason')->label(__('reservations.fields.reason'))->limit(50)->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label(__('reservations.fields.changed_at'))->dateTime()->sortable(),
            ])
            ->emptyStateHeading(__('reservations.empty.history_heading'))
            ->emptyStateDescription(__('reservations.empty.history_description'))
            ->emptyStateIcon('heroicon-o-clock');
    }
}
