<?php
namespace App\Filament\Resources\UnitResource\RelationManagers;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
class StatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Status history');
    }

    public function isReadOnly(): bool { return true; }

    public function table(Table $table): Table { return $table->columns([
        Tables\Columns\TextColumn::make('from_status')->label(__('From'))->placeholder(__('Legacy baseline')),
        Tables\Columns\TextColumn::make('to_status')->label(__('To'))->badge(),
        Tables\Columns\TextColumn::make('reason')->label(__('Reason'))->wrap()->placeholder(__('—')),
        Tables\Columns\TextColumn::make('changedBy.name')->label(__('Changed by'))->placeholder(__('System')),
        Tables\Columns\TextColumn::make('changed_at')->label(__('Changed at'))->dateTime()->sortable(),
    ])->defaultSort('changed_at', 'desc'); }
}
