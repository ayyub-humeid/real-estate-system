<?php

namespace App\Filament\Resources\ProjectBudgetResource\RelationManagers;

use App\Models\BudgetCategory;
use App\Models\ProjectBudget;
use App\Services\BudgetingService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    protected static ?string $title = 'Budget Structure';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->description(
                    fn (BudgetCategory $record): string => $record->parent?->name ? "Under: {$record->parent->name}" : 'Top-level category'
                ),
                Tables\Columns\TextColumn::make('code')->placeholder('—'),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Items'),
                Tables\Columns\TextColumn::make('items_sum_planned_amount')
                    ->sum('items', 'planned_amount')
                    ->money(fn (): string => $this->getOwnerRecord()->project->currency),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add category')
                    ->icon('heroicon-o-folder-plus')
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft')
                    ->form([
                        Forms\Components\Select::make('parent_id')->label('Parent category')
                            ->options(fn (): array => BudgetCategory::withoutGlobalScopes()->where('project_budget_id', $this->getOwnerRecord()->id)->pluck('name', 'id')->all())
                            ->searchable(),
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\TextInput::make('code'),
                        Forms\Components\Textarea::make('description'),
                        Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;
                        return $data;
                    })
                    ->using(fn (array $data, ProjectBudget $ownerRecord) => app(BudgetingService::class)->addCategory(auth()->user(), $ownerRecord, $data)),
            ])
            ->actions([
                Tables\Actions\Action::make('addItem')
                    ->label('Add item')
                    ->icon('heroicon-o-plus-circle')
                    ->tooltip('Add a planned cost line')
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft')
                    ->form($this->itemForm())
                    ->action(fn (BudgetCategory $record, array $data) => app(BudgetingService::class)->addItem(auth()->user(), $record, $data)),
                Tables\Actions\EditAction::make()->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft'),
                Tables\Actions\DeleteAction::make()->visible(
                    fn (BudgetCategory $record): bool => $this->getOwnerRecord()->status === 'draft' && ! $record->items()->exists() && ! $record->children()->exists()
                ),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getOwnerRecord()->company_id;

        return $data;
    }

    private function itemForm(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('code'),
            Forms\Components\TextInput::make('planned_amount')->numeric()->required(),
            Forms\Components\TextInput::make('quantity')->numeric(),
            Forms\Components\TextInput::make('unit'),
            Forms\Components\TextInput::make('unit_cost')->numeric(),
            Forms\Components\Textarea::make('description'),
            Forms\Components\Textarea::make('notes'),
        ];
    }
}
