<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectConstructionResource\Pages;
use App\Models\ProjectConstruction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectConstructionResource extends Resource
{
    protected static ?string $model = ProjectConstruction::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('project.name')->disabled(),
            Forms\Components\DatePicker::make('planned_start_date'),
            Forms\Components\DatePicker::make('expected_completion_date'),
            Forms\Components\Textarea::make('notes'),
            Forms\Components\Placeholder::make('status')
                ->content(fn(?ProjectConstruction $record) => $record ? str_replace('_', ' ', $record->status) : 'Created from a Project'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('project.name')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('progress_percentage')->suffix('%'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProjectConstructions::route('/')];
    }
}
