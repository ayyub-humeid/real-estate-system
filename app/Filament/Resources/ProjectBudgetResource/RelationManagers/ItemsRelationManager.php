<?php

namespace App\Filament\Resources\ProjectBudgetResource\RelationManagers;

use App\Models\BudgetCategory;
use App\Models\ProjectBudget;
use App\Services\BudgetingService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Budget Items';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.name')->label('Category')->searchable(),
                Tables\Columns\TextColumn::make('code')->placeholder('—'),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('planned_amount')->money(fn (): string => $this->getOwnerRecord()->project->currency)->sortable(),
                Tables\Columns\TextColumn::make('quantity')->placeholder('—'),
                Tables\Columns\TextColumn::make('unit')->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add budget item')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft')
                    ->form([
                        Forms\Components\Select::make('budget_category_id')->label('Category')
                            ->options(fn (): array => BudgetCategory::withoutGlobalScopes()->where('project_budget_id', $this->getOwnerRecord()->id)->pluck('name', 'id')->all())
                            ->required()->searchable(),
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\TextInput::make('code'),
                        Forms\Components\TextInput::make('planned_amount')->numeric()->required(),
                        Forms\Components\TextInput::make('quantity')->numeric(),
                        Forms\Components\TextInput::make('unit'),
                        Forms\Components\TextInput::make('unit_cost')->numeric(),
                        Forms\Components\Textarea::make('description'),
                        Forms\Components\Textarea::make('notes'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;
                        return $data;
                    })
                    ->using(function (array $data, ProjectBudget $ownerRecord) {
                        $category = BudgetCategory::withoutGlobalScopes()
                            ->where('project_budget_id', $ownerRecord->id)
                            ->findOrFail($data['budget_category_id']);

                        unset($data['budget_category_id']);

                        return app(BudgetingService::class)->addItem(auth()->user(), $category, $data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft'),
                Tables\Actions\DeleteAction::make()->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft'),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getOwnerRecord()->company_id;

        return $data;
    }
}
