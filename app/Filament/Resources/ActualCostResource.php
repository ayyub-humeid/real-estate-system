<?php
namespace App\Filament\Resources;
use App\Models\ActualCost;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class ActualCostResource extends Resource
{
    protected static ?string $model = ActualCost::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('project.name'),
            Tables\Columns\TextColumn::make('name'),
            Tables\Columns\TextColumn::make('status')->badge()
        ]);
    }
    public static function getPages(): array
    {
        return [];
    }
}
