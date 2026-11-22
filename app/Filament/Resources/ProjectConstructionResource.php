<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectConstructionResource\Pages;
use App\Models\ProjectConstruction;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Hidden from navigation: work starts from Project → Construction. */
class ProjectConstructionResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;

    protected static ?string $model = ProjectConstruction::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function getModelLabel(): string
    {
        return __('project.construction.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('project.construction.title');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('project.name')->disabled(),
            Forms\Components\DatePicker::make('planned_start_date'),
            Forms\Components\DatePicker::make('expected_completion_date'),
            Forms\Components\Textarea::make('notes'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('project.name')->label(__('Project'))->searchable(),
            Tables\Columns\TextColumn::make('status')->label(__('project.construction.fields.status'))->badge()
                ->formatStateUsing(fn (?string $state): string => __('project.statuses.'.$state)),
            Tables\Columns\TextColumn::make('progress_percentage')->label(__('project.construction.fields.progress'))->suffix('%'),
        ])->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('status')->label(__('project.construction.fields.status'))->badge()
                    ->formatStateUsing(fn (?string $state): string => __('project.statuses.'.$state))
                    ->color(fn (?string $state): string => match ($state) { 'in_progress' => 'info', 'completed' => 'success', default => 'gray' }),
                Infolists\Components\TextEntry::make('progress_percentage')->label(__('project.construction.fields.progress'))
                    ->formatStateUsing(fn ($state): string => (int) $state.'%')->size('lg')->weight('bold'),
                Infolists\Components\TextEntry::make('managerParty.name')->label(__('project.construction.fields.manager'))->placeholder('—'),
                Infolists\Components\TextEntry::make('planned_start_date')->label(__('project.construction.fields.planned_start'))->date()->placeholder('—'),
                Infolists\Components\TextEntry::make('expected_completion_date')->label(__('project.construction.fields.expected'))->date()->placeholder('—'),
                Infolists\Components\TextEntry::make('notes')->label(__('project.construction.fields.notes'))->placeholder('—'),
            ])->columns(3),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectConstructions::route('/'),
            'view' => Pages\ViewProjectConstruction::route('/{record}'),
        ];
    }
}
