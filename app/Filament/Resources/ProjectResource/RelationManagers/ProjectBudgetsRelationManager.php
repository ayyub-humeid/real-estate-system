<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\{BudgetCategory, BudgetItem, ProjectBudget};
use App\Services\BudgetingService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ProjectBudgetsRelationManager extends RelationManager
{
    protected static string $relationship = 'budgets';

    protected static ?string $title = 'Budget Planning';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('version_number', 'desc')
            ->emptyStateHeading('Create the project budget')
            ->emptyStateDescription('Create a budget version, then manage its categories and planned cost items directly from this Project page.')
            ->columns([
                Tables\Columns\TextColumn::make('version_number')->label('Version')->badge(),
                Tables\Columns\TextColumn::make('name')->placeholder('Unnamed budget')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'gray' => ['draft', 'superseded', 'closed'],
                    'warning' => 'pending_approval',
                    'success' => 'approved',
                    'danger' => 'cancelled',
                ]),
                Tables\Columns\TextColumn::make('categories_count')->counts('categories')->label('Categories')->alignCenter(),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Items')->alignCenter(),
                Tables\Columns\TextColumn::make('items_sum_planned_amount')
                    ->sum('items', 'planned_amount')
                    ->label('Planned')
                    ->money(fn (): string => $this->getOwnerRecord()->currency),
                Tables\Columns\TextColumn::make('approved_at')->dateTime()->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createBudget')
                    ->label('Create budget')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\TextInput::make('name')->label('Budget name'),
                        Forms\Components\Textarea::make('notes'),
                    ])
                    ->action(function (array $data): void {
                        $this->run(function () use ($data): void {
                            app(BudgetingService::class)->createBudget(auth()->user(), $this->getOwnerRecord(), $data);
                            Notification::make()->success()->title('Budget created')->body('Add categories and planned cost items from this Project page.')->send();
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->tooltip('View budget version, categories, and planned items')
                    ->modalWidth('6xl')
                    ->infolist($this->budgetInfolist()),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('addCategory')
                        ->label('Add category')
                        ->icon('heroicon-o-plus')
                        ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft')
                        ->form(fn (ProjectBudget $record): array => $this->categoryForm($record))
                        ->action(function (ProjectBudget $record, array $data): void {
                            $this->run(function () use ($record, $data): void {
                                app(BudgetingService::class)->addCategory(auth()->user(), $record, $data);
                                Notification::make()->success()->title('Budget category added')->send();
                            });
                        }),
                    Tables\Actions\Action::make('editCategory')
                        ->label('Edit category')
                        ->icon('heroicon-o-pencil-square')
                        ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft' && $record->categories()->exists())
                        ->form(fn (ProjectBudget $record): array => array_merge([
                            Forms\Components\Select::make('category_id')
                                ->label('Category')
                                ->options($this->categoryOptions($record))
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function ($state, $set) use ($record): void {
                                    if (blank($state)) {
                                        return;
                                    }

                                    $category = $this->categoryForBudget($record, $state);
                                    $set('parent_id', $category->parent_id);
                                    $set('name', $category->name);
                                    $set('code', $category->code);
                                    $set('description', $category->description);
                                    $set('sort_order', $category->sort_order);
                                }),
                        ], $this->categoryForm($record)))
                        ->action(function (ProjectBudget $record, array $data): void {
                            $this->run(function () use ($record, $data): void {
                                $category = $this->categoryForBudget($record, $data['category_id']);
                                unset($data['category_id']);
                                app(BudgetingService::class)->updateCategory(auth()->user(), $record, $category, $data);
                                Notification::make()->success()->title('Budget category updated')->send();
                            });
                        }),
                    Tables\Actions\Action::make('deleteCategory')
                        ->label('Delete category')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft' && $record->categories()->exists())
                        ->form(fn (ProjectBudget $record): array => [
                            Forms\Components\Select::make('category_id')->label('Category')->options($this->categoryOptions($record))->required()->searchable(),
                        ])
                        ->requiresConfirmation()
                        ->modalDescription('A category can be deleted only after its child categories and budget items are removed.')
                        ->action(function (ProjectBudget $record, array $data): void {
                            $this->run(function () use ($record, $data): void {
                                app(BudgetingService::class)->deleteCategory(auth()->user(), $record, $this->categoryForBudget($record, $data['category_id']));
                                Notification::make()->success()->title('Budget category deleted')->send();
                            });
                        }),
                ])->label('Categories')->icon('heroicon-o-folder')->tooltip('Manage budget categories'),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('addItem')
                        ->label('Add budget item')
                        ->icon('heroicon-o-plus')
                        ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft' && $record->categories()->exists())
                        ->form(fn (ProjectBudget $record): array => array_merge([
                            Forms\Components\Select::make('category_id')->label('Category')->options($this->categoryOptions($record))->required()->searchable(),
                        ], $this->itemForm()))
                        ->action(function (ProjectBudget $record, array $data): void {
                            $this->run(function () use ($record, $data): void {
                                $category = $this->categoryForBudget($record, $data['category_id']);
                                unset($data['category_id']);
                                app(BudgetingService::class)->addItem(auth()->user(), $category, $data);
                                Notification::make()->success()->title('Budget item added')->send();
                            });
                        }),
                    Tables\Actions\Action::make('editItem')
                        ->label('Edit budget item')
                        ->icon('heroicon-o-pencil-square')
                        ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft' && $record->items()->exists())
                        ->form(fn (ProjectBudget $record): array => array_merge([
                            Forms\Components\Select::make('budget_item_id')
                                ->label('Budget item')
                                ->options($this->itemOptions($record))
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function ($state, $set) use ($record): void {
                                    if (blank($state)) {
                                        return;
                                    }

                                    $item = $this->itemForBudget($record, $state);
                                    $set('name', $item->name);
                                    $set('code', $item->code);
                                    $set('description', $item->description);
                                    $set('planned_amount', $item->planned_amount);
                                    $set('quantity', $item->quantity);
                                    $set('unit', $item->unit);
                                    $set('unit_cost', $item->unit_cost);
                                    $set('notes', $item->notes);
                                }),
                        ], $this->itemForm()))
                        ->action(function (ProjectBudget $record, array $data): void {
                            $this->run(function () use ($record, $data): void {
                                $item = $this->itemForBudget($record, $data['budget_item_id']);
                                unset($data['budget_item_id']);
                                app(BudgetingService::class)->updateItem(auth()->user(), $record, $item, $data);
                                Notification::make()->success()->title('Budget item updated')->send();
                            });
                        }),
                    Tables\Actions\Action::make('deleteItem')
                        ->label('Delete budget item')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft' && $record->items()->exists())
                        ->form(fn (ProjectBudget $record): array => [
                            Forms\Components\Select::make('budget_item_id')->label('Budget item')->options($this->itemOptions($record))->required()->searchable(),
                        ])
                        ->requiresConfirmation()
                        ->modalDescription('Deleting a draft item does not change historical budget versions or financial records.')
                        ->action(function (ProjectBudget $record, array $data): void {
                            $this->run(function () use ($record, $data): void {
                                app(BudgetingService::class)->deleteItem(auth()->user(), $record, $this->itemForBudget($record, $data['budget_item_id']));
                                Notification::make()->success()->title('Budget item deleted')->send();
                            });
                        }),
                ])->label('Budget items')->icon('heroicon-o-calculator')->tooltip('Manage planned cost items'),
                Tables\Actions\Action::make('submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->tooltip('Submit for approval')
                    ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft')
                    ->action(fn (ProjectBudget $record) => $this->run(fn () => app(BudgetingService::class)->submitBudget(auth()->user(), $record))),
                Tables\Actions\Action::make('approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->tooltip('Approve budget')
                    ->visible(fn (ProjectBudget $record): bool => $record->status === 'pending_approval')
                    ->action(fn (ProjectBudget $record) => $this->run(fn () => app(BudgetingService::class)->approveBudget(auth()->user(), $record))),
                Tables\Actions\Action::make('returnToDraft')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->tooltip('Return to draft')
                    ->visible(fn (ProjectBudget $record): bool => $record->status === 'pending_approval')
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(fn (ProjectBudget $record, array $data) => $this->run(fn () => app(BudgetingService::class)->rejectBudget(auth()->user(), $record, $data['reason']))),
                Tables\Actions\Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->tooltip('Cancel draft budget')
                    ->visible(fn (ProjectBudget $record): bool => in_array($record->status, ['draft', 'pending_approval'], true))
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(fn (ProjectBudget $record, array $data) => $this->run(fn () => app(BudgetingService::class)->cancelBudget(auth()->user(), $record, $data['reason']))),
                Tables\Actions\Action::make('createRevision')
                    ->icon('heroicon-o-arrow-path')
                    ->tooltip('Create revision')
                    ->visible(fn (ProjectBudget $record): bool => $record->status === 'approved')
                    ->action(fn (ProjectBudget $record) => $this->run(fn () => app(BudgetingService::class)->createRevision(auth()->user(), $record))),
            ]);
    }

    private function categoryForm(ProjectBudget $budget): array
    {
        return [
            Forms\Components\Select::make('parent_id')->label('Parent category')->options($this->categoryOptions($budget))->searchable(),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('code'),
            Forms\Components\Textarea::make('description'),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
        ];
    }

    private function budgetInfolist(): array
    {
        return [
            \Filament\Infolists\Components\Section::make('Budget version')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('version_number')->label('Version')->badge(),
                    \Filament\Infolists\Components\TextEntry::make('name')->placeholder('Unnamed budget'),
                    \Filament\Infolists\Components\TextEntry::make('status')->badge()->colors([
                        'gray' => ['draft', 'superseded', 'closed', 'cancelled'],
                        'warning' => 'pending_approval',
                        'success' => 'approved',
                    ]),
                    \Filament\Infolists\Components\TextEntry::make('approved_at')->label('Approved at')->dateTime()->placeholder('—'),
                    \Filament\Infolists\Components\TextEntry::make('notes')->columnSpanFull()->placeholder('No notes'),
                ])->columns(4),

            \Filament\Infolists\Components\Tabs::make('Budget details')
                ->tabs([
                    \Filament\Infolists\Components\Tabs\Tab::make('Categories')
                        ->icon('heroicon-o-folder')
                        ->schema([
                            \Filament\Infolists\Components\RepeatableEntry::make('budget_categories')
                                ->label('')
                                ->state(fn (ProjectBudget $record): array => $record->categories()
                                    ->with('parent')
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                                    ->get()
                                    ->map(fn (BudgetCategory $category): array => [
                                        'name' => $category->name,
                                        'code' => $category->code,
                                        'parent' => $category->parent?->name ?? 'Top-level category',
                                        'description' => $category->description,
                                    ])->all())
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('name')->weight('bold'),
                                    \Filament\Infolists\Components\TextEntry::make('code')->placeholder('—'),
                                    \Filament\Infolists\Components\TextEntry::make('parent')->label('Parent'),
                                    \Filament\Infolists\Components\TextEntry::make('description')->columnSpanFull()->placeholder('No description'),
                                ])->columns(3),
                        ]),
                    \Filament\Infolists\Components\Tabs\Tab::make('Planned items')
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            \Filament\Infolists\Components\RepeatableEntry::make('budget_items')
                                ->label('')
                                ->state(fn (ProjectBudget $record): array => $record->items()
                                    ->with('category')
                                    ->orderBy('budget_items.name')
                                    ->get()
                                    ->map(fn (BudgetItem $item): array => [
                                        'category' => $item->category->name,
                                        'name' => $item->name,
                                        'code' => $item->code,
                                        'planned_amount' => number_format((float) $item->planned_amount, 2) . ' ' . $record->project->currency,
                                        'quantity' => $item->quantity,
                                        'unit' => $item->unit,
                                        'description' => $item->description,
                                    ])->all())
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('category')->badge()->color('gray'),
                                    \Filament\Infolists\Components\TextEntry::make('name')->weight('bold'),
                                    \Filament\Infolists\Components\TextEntry::make('code')->placeholder('—'),
                                    \Filament\Infolists\Components\TextEntry::make('planned_amount')->label('Planned amount'),
                                    \Filament\Infolists\Components\TextEntry::make('quantity')->placeholder('—'),
                                    \Filament\Infolists\Components\TextEntry::make('unit')->placeholder('—'),
                                    \Filament\Infolists\Components\TextEntry::make('description')->columnSpanFull()->placeholder('No description'),
                                ])->columns(3),
                        ]),
                ]),
        ];
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

    private function categoryOptions(ProjectBudget $budget): array
    {
        return BudgetCategory::withoutGlobalScopes()->where('project_budget_id', $budget->id)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }

    private function itemOptions(ProjectBudget $budget): array
    {
        return $budget->items()->with('category')->orderBy('budget_items.name')->get()->mapWithKeys(
            fn (BudgetItem $item): array => [$item->id => "{$item->category->name} — {$item->name}"]
        )->all();
    }

    private function categoryForBudget(ProjectBudget $budget, mixed $categoryId): BudgetCategory
    {
        return BudgetCategory::withoutGlobalScopes()->where('project_budget_id', $budget->id)->findOrFail($categoryId);
    }

    private function itemForBudget(ProjectBudget $budget, mixed $itemId): BudgetItem
    {
        return $budget->items()->with('category')->findOrFail($itemId);
    }

    private function run(callable $operation): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Cannot update budget')->body(collect($exception->errors())->flatten()->first())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title('Unauthorized')->body('You do not have permission to perform this budget action.')->send();
        }
    }
}
