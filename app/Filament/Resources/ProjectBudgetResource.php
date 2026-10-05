<?php
namespace App\Filament\Resources;
use App\Filament\Resources\ProjectBudgetResource\Pages;
use App\Models\ProjectBudget;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class ProjectBudgetResource extends Resource
{
    protected static ?string $model = ProjectBudget::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name'),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
            Forms\Components\Placeholder::make('status')->content(fn(?ProjectBudget $r) => $r?->status ?? 'Created from the Project budget workspace')
        ]);
    }
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('project.name'),
            Tables\Columns\TextColumn::make('version_number')->label('Version'),
            Tables\Columns\TextColumn::make('status')->badge()
        ])->actions([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                ]);
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectBudgets::route('/'),
            'view' => Pages\ViewProjectBudget::route('/{record}'),
            'edit' => Pages\EditProjectBudget::route('/{record}/edit')
        ];
    }
}
