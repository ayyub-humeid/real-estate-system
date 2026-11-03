<?php
namespace App\Filament\Resources;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\{Company, Project};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class ProjectResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
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
                ->label(__('Company'))
                ->options(fn() => Company::query()->pluck('name', 'id'))->required()->visible(fn() => auth()->user()->isSuperAdmin()),
            Forms\Components\TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
            Forms\Components\Select::make('project_type')
                ->label(__('Project Type'))
                ->options([
                    'residential' => __('project.project_types.residential'),
                    'commercial' => __('project.project_types.commercial'),
                    'mixed_use' => __('project.project_types.mixed_use'),
                    'industrial' => __('project.project_types.industrial'),
                    'hospitality' => __('project.project_types.hospitality'),
                    'land_development' => __('project.project_types.land_development'),
                    'other' => __('project.project_types.other'),
                ])
                ->searchable()
                ->native(false)
                ->required(),
            Forms\Components\TextInput::make('currency')->label(__('Currency'))->required()->length(3)->extraInputAttributes(['style' => 'text-transform: uppercase;'])->default('USD')
                ->disabled(fn (?Project $record): bool => $record && ($record->budgets()->exists() || $record->commitments()->exists() || $record->actualCosts()->exists() || $record->payments()->exists())),
            Forms\Components\DatePicker::make('start_date')->label(__('Start Date')),
            Forms\Components\DatePicker::make('expected_completion_date')->label(__('Expected Completion Date')),
            Forms\Components\Textarea::make('description')->label(__('Description'))->columnSpanFull()
        ]);
    }
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable(),
            Tables\Columns\TextColumn::make('project_type')->label(__('Project Type'))->badge()
                ->formatStateUsing(fn (?string $state): string => $state ? __('project.project_types.'.$state) : __('—')),
            Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()
                ->colors(['gray' => 'planning', 'info' => 'approved', 'warning' => 'in_progress', 'success' => ['completed', 'closed'], 'danger' => 'cancelled'])
                ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
            Tables\Columns\TextColumn::make('active_project_properties_count')->label(__('Properties')),
            Tables\Columns\TextColumn::make('active_members_count')->label(__('Members')),
            Tables\Columns\TextColumn::make('buildings_count')->label(__('Buildings'))
        ])->actions([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
        ->emptyStateHeading(__('No projects yet'))
        ->emptyStateDescription(__('Create your first project to get started.'))
        ->emptyStateIcon('heroicon-o-building-office-2');
    }
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Project overview'))
                ->icon('heroicon-o-building-office-2')
                ->columnSpanFull()
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label(__('Name'))->weight('bold'),
                    Infolists\Components\TextEntry::make('project_type')->label(__('Project Type'))->badge()
                        ->formatStateUsing(fn (?string $state): string => $state ? __('project.project_types.'.$state) : __('—')),
                    Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()
                        ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                    Infolists\Components\TextEntry::make('currency')->label(__('Currency')),
                    Infolists\Components\TextEntry::make('start_date')->label(__('Start Date'))->date()->placeholder(__('—')),
                    Infolists\Components\TextEntry::make('expected_completion_date')->label(__('Expected Completion Date'))->date()->placeholder(__('—')),
                    Infolists\Components\TextEntry::make('description')->label(__('Description'))->placeholder(__('No description'))->columnSpanFull(),
                ])->columns(3),
        ]);
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
            RelationManagers\ProjectContractsRelationManager::class,
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
