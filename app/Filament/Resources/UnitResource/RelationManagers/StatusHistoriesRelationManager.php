<?php
namespace App\Filament\Resources\UnitResource\RelationManagers;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
class StatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';
    protected static ?string $title = 'Status history';
    public function isReadOnly(): bool { return true; }
    public function table(Table $table): Table { return $table->columns([
        Tables\Columns\TextColumn::make('from_status')->label('From')->placeholder('Legacy baseline'),
        Tables\Columns\TextColumn::make('to_status')->label('To')->badge(),
        Tables\Columns\TextColumn::make('reason')->wrap()->placeholder('—'),
        Tables\Columns\TextColumn::make('changedBy.name')->label('Changed by')->placeholder('System'),
        Tables\Columns\TextColumn::make('changed_at')->dateTime()->sortable(),
    ])->defaultSort('changed_at', 'desc'); }
}
