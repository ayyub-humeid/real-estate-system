<?php
namespace App\Filament\Resources;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\{Company, Project};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = '🏠 Properties';
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withCount(['activeProjectProperties', 'activeMembers', 'buildings']);
    }
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('company_id')
                ->label('Company')
                ->options(fn() => Company::query()->pluck('name', 'id'))->required()->visible(fn() => auth()->user()->isSuperAdmin()),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('project_type')
                ->placeholder('e.g., Residential, Commercial, Industrial')
                ->required(),
            Forms\Components\TextInput::make('currency')->required()->length(3)->extraInputAttributes(['style' => 'text-transform: uppercase;'])->default('USD')
                ->disabled(fn (?Project $record): bool => $record && ($record->budgets()->exists() || $record->commitments()->exists() || $record->actualCosts()->exists() || $record->payments()->exists())),
            Forms\Components\DatePicker::make('start_date'),
            Forms\Components\DatePicker::make('expected_completion_date'),
            Forms\Components\Textarea::make('description')->columnSpanFull()
        ]);
    }
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('project_type')->badge(),
            Tables\Columns\TextColumn::make('status')->badge()->colors(['gray' => 'planning', 'info' => 'approved', 'warning' => 'in_progress', 'success' => ['completed', 'closed'], 'danger' => 'cancelled']),
            Tables\Columns\TextColumn::make('active_project_properties_count')->label('Properties'),
            Tables\Columns\TextColumn::make('active_members_count')->label('Members'),
            Tables\Columns\TextColumn::make('buildings_count')->label('Buildings')
        ])->actions([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
    public static function getRelations(): array
    {
        return [
            RelationManagers\ProjectPropertiesRelationManager::class,
            RelationManagers\ProjectMembersRelationManager::class,
            RelationManagers\ProjectBuildingsRelationManager::class,
            RelationManagers\ProjectUnitsRelationManager::class,
            RelationManagers\DesignPackagesRelationManager::class,
            RelationManagers\ProjectBudgetsRelationManager::class,
            RelationManagers\ProjectCommitmentsRelationManager::class,
            RelationManagers\ProjectConstructionRelationManager::class,
            RelationManagers\ProjectActualCostsRelationManager::class,
            RelationManagers\ProjectPaymentsRelationManager::class,
        ];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view' => Pages\ViewProject::route('/{record}'),
            'edit' => Pages\EditProject::route('/{record}/edit')
        ];
    }
}
