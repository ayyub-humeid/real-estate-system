<?php
namespace App\Filament\Resources;
use App\Models\FinancialCommitment;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class FinancialCommitmentResource extends Resource
{
    protected static ?string $model = FinancialCommitment::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('project.name'),
            Tables\Columns\TextColumn::make('description'),
            Tables\Columns\TextColumn::make('status')->badge()
        ]);
    }
    public static function getPages(): array
    {
        return [];
    }
}
