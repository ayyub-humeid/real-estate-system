<?php

namespace App\Filament\Resources\ProjectConstructionResource\RelationManagers;

use App\Models\ConstructionDelay;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** The history of schedule delays. Delays are recorded from a stage's row and are never edited. */
class DelaysRelationManager extends RelationManager
{
    protected static string $relationship = 'delays';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project.construction.tab_delays');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['workPackage', 'task']))
            ->defaultSort('reported_at', 'desc')
            ->emptyStateHeading(__('project.construction.delays_empty'))
            ->columns([
                Tables\Columns\TextColumn::make('workPackage.name')->label(__('project.construction.fields.package'))->weight('medium'),
                Tables\Columns\TextColumn::make('task.name')->label(__('project.construction.fields.task'))->placeholder('—'),
                Tables\Columns\TextColumn::make('reason_code')->label(__('project.construction.fields.reason_code'))->badge()
                    ->formatStateUsing(fn (?string $state): string => __('project.construction.delay_reasons.'.$state)),
                Tables\Columns\TextColumn::make('baseline_end_date')->label(__('project.construction.fields.baseline_end'))->date(),
                Tables\Columns\TextColumn::make('revised_end_date')->label(__('project.construction.fields.revised_end'))->date(),
                Tables\Columns\TextColumn::make('delay_days')->label(__('project.construction.fields.days'))->badge()->color('warning'),
                Tables\Columns\TextColumn::make('description')->label(__('project.construction.fields.description'))->placeholder('—')->wrap(),
            ]);
    }
}
