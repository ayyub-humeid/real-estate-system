<?php

namespace App\Filament\Resources\ProjectBudgetResource\RelationManagers;

use App\Models\BudgetCategory;
use App\Models\ProjectBudget;
use App\Services\BudgetingService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

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
                Tables\Columns\TextColumn::make('code')->placeholder(__('—')),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Items'),
                Tables\Columns\TextColumn::make('items_sum_planned_amount')
                    ->sum('items', 'planned_amount')
                    ->money(fn (): string => $this->getOwnerRecord()->project->currency),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('budget.actions.add_category'))
                    ->icon('heroicon-o-folder-plus')
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft')
                    ->form([
                        Forms\Components\Select::make('parent_id')->label(__('budget.fields.parent_category'))
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

                // Quick add budget item (without category selection - uses first category or creates default)
                Action::make('quickAddItem')
                    ->label(__('budget.actions.quick_add_item'))
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft')
                    ->form([
                        Forms\Components\Select::make('budget_category_id')->label(__('budget.fields.category'))
                            ->options(fn (): array => BudgetCategory::withoutGlobalScopes()->where('project_budget_id', $this->getOwnerRecord()->id)->pluck('name', 'id')->all())
                            ->required()->searchable()->native(false)
                            ->default(fn (): ?int => BudgetCategory::withoutGlobalScopes()->where('project_budget_id', $this->getOwnerRecord()->id)->orderBy('sort_order')->value('id')),
                        Forms\Components\TextInput::make('name')->required()->label(__('budget.fields.item_name')),
                        Forms\Components\TextInput::make('code')->label(__('budget.fields.code')),
                        Forms\Components\TextInput::make('planned_amount')->label(__('budget.fields.planned_amount'))->numeric()->required()->step(0.01)->minValue(0),
                        Forms\Components\TextInput::make('quantity')->label(__('budget.fields.quantity'))->numeric(),
                        Forms\Components\TextInput::make('unit')->label(__('budget.fields.unit')),
                        Forms\Components\TextInput::make('unit_cost')->label(__('budget.fields.unit_cost'))->numeric()->step(0.01)->minValue(0),
                        Forms\Components\Textarea::make('description')->label(__('budget.fields.description')),
                        Forms\Components\Textarea::make('notes')->label(__('budget.fields.notes')),
                    ])
                    ->action(function (array $data, ProjectBudget $ownerRecord) {
                        $category = BudgetCategory::withoutGlobalScopes()
                            ->where('project_budget_id', $ownerRecord->id)
                            ->findOrFail($data['budget_category_id']);

                        unset($data['budget_category_id']);
                        $data['company_id'] = $ownerRecord->company_id;

                        app(BudgetingService::class)->addItem(auth()->user(), $category, $data);
                    })
                    ->modalHeading(__('budget.modals.quick_add_heading'))
                    ->modalSubmitActionLabel(__('budget.modals.add_submit')),
            ])
            ->actions([
                Tables\Actions\Action::make('addItem')
                    ->label(__('budget.actions.add_item'))
                    ->icon('heroicon-o-plus-circle')
                    ->tooltip(__('budget.tooltips.add_planned_cost'))
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
            Forms\Components\TextInput::make('name')->label(__('budget.fields.item_name'))->required(),
            Forms\Components\TextInput::make('code')->label(__('budget.fields.code')),
            Forms\Components\TextInput::make('planned_amount')->label(__('budget.fields.planned_amount'))->numeric()->required()->step(0.01)->minValue(0),
            Forms\Components\TextInput::make('quantity')->label(__('budget.fields.quantity'))->numeric(),
            Forms\Components\TextInput::make('unit')->label(__('budget.fields.unit')),
            Forms\Components\TextInput::make('unit_cost')->label(__('budget.fields.unit_cost'))->numeric()->step(0.01)->minValue(0),
            Forms\Components\Textarea::make('description')->label(__('budget.fields.description')),
            Forms\Components\Textarea::make('notes')->label(__('budget.fields.notes')),
        ];
    }
}
