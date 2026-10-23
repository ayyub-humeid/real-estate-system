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

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Budget Planning');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('version_number', 'desc')
            ->emptyStateHeading(__('Create the project budget'))
            ->emptyStateDescription(__('Create a budget version, then manage its categories and planned cost items directly from this Project page.'))
            ->columns([
                Tables\Columns\TextColumn::make('version_number')->label(__('Version'))->badge(),
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->placeholder(__('Unnamed budget'))->searchable(),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()->colors([
                    'gray' => ['draft', 'superseded', 'closed'],
                    'warning' => 'pending_approval',
                    'success' => 'approved',
                    'danger' => 'cancelled',
                ])->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                Tables\Columns\TextColumn::make('categories_count')->counts('categories')->label(__('Categories'))->alignCenter(),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label(__('Items'))->alignCenter(),
                Tables\Columns\TextColumn::make('items_sum_planned_amount')
                    ->sum('items', 'planned_amount')
                    ->label(__('Planned'))
                    ->money(fn (): string => $this->getOwnerRecord()->currency),
                Tables\Columns\TextColumn::make('approved_at')->label(__('Approved At'))->dateTime()->placeholder(__('—')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createBudget')
                    ->label(__('Create budget'))
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\TextInput::make('name')->label(__('Budget name')),
                        Forms\Components\Textarea::make('notes')->label(__('Notes')),
                    ])
                    ->action(function (array $data): void {
                        $this->run(function () use ($data): void {
                            app(BudgetingService::class)->createBudget(auth()->user(), $this->getOwnerRecord(), $data);
                            Notification::make()->success()->title(__('Budget created'))->body(__('Add categories and planned cost items from this Project page.'))->send();
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('Details'))
                    ->icon('heroicon-o-eye')
                    ->tooltip(__('View budget version, categories, and planned items'))
                    ->modalWidth('6xl')
                    ->infolist($this->budgetInfolist()),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('addCategory')
                        ->label(__('Add category'))
                        ->icon('heroicon-o-plus')
                        ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft')
                        ->form(fn (ProjectBudget $record): array => $this->categoryForm($record))
                        ->action(function (ProjectBudget $record, array $data): void {
                            $this->run(function () use ($record, $data): void {
                                app(BudgetingService::class)->addCategory(auth()->user(), $record, $data);
                                Notification::make()->success()->title(__('Budget category added'))->send();
                            });
                        }),
                    Tables\Actions\Action::make('editCategory')
                        ->label(__('Edit category'))
                        ->icon('heroicon-o-pencil-square')
                        ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft' && $record->categories()->exists())
                        ->form(fn (ProjectBudget $record): array => array_merge([
                            Forms\Components\Select::make('category_id')
                                ->label(__('Category'))
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
                                Notification::make()->success()->title(__('Budget category updated'))->send();
                            });
                        }),
                    Tables\Actions\Action::make('deleteCategory')
                        ->label(__('Delete category'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft' && $record->categories()->exists())
                        ->form(fn (ProjectBudget $record): array => [
                            Forms\Components\Select::make('category_id')->label(__('Category'))->options($this->categoryOptions($record))->required()->searchable(),
                        ])
                        ->requiresConfirmation()
                        ->modalDescription(__('A category can be deleted only after its child categories and budget items are removed.'))
                        ->action(function (ProjectBudget $record, array $data): void {
                            $this->run(function () use ($record, $data): void {
                                app(BudgetingService::class)->deleteCategory(auth()->user(), $record, $this->categoryForBudget($record, $data['category_id']));
                                Notification::make()->success()->title(__('Budget category deleted'))->send();
                            });
                        }),
                ])->label(__('Categories'))->icon('heroicon-o-folder')->tooltip(__('Manage budget categories')),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('addItem')
                        ->label(__('Add budget item'))
                        ->icon('heroicon-o-plus')
                        ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft' && $record->categories()->exists())
                        ->form(fn (ProjectBudget $record): array => array_merge([
                            Forms\Components\Select::make('category_id')->label(__('Category'))->options($this->categoryOptions($record))->required()->searchable(),
                        ], $this->itemForm()))
                        ->action(function (ProjectBudget $record, array $data): void {
                            $this->run(function () use ($record, $data): void {
                                $category = $this->categoryForBudget($record, $data['category_id']);
                                unset($data['category_id']);
                                app(BudgetingService::class)->addItem(auth()->user(), $category, $data);
                                Notification::make()->success()->title(__('Budget item added'))->send();
                            });
                        }),
                    Tables\Actions\Action::make('editItem')
                        ->label(__('Edit budget item'))
                        ->icon('heroicon-o-pencil-square')
                        ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft' && $record->items()->exists())
                        ->form(fn (ProjectBudget $record): array => array_merge([
                            Forms\Components\Select::make('budget_item_id')
                                ->label(__('Budget item'))
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
                                Notification::make()->success()->title(__('Budget item updated'))->send();
                            });
                        }),
                    Tables\Actions\Action::make('deleteItem')
                        ->label(__('Delete budget item'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft' && $record->items()->exists())
                        ->form(fn (ProjectBudget $record): array => [
                            Forms\Components\Select::make('budget_item_id')->label(__('Budget item'))->options($this->itemOptions($record))->required()->searchable(),
                        ])
                        ->requiresConfirmation()
                        ->modalDescription(__('Deleting a draft item does not change historical budget versions or financial records.'))
                        ->action(function (ProjectBudget $record, array $data): void {
                            $this->run(function () use ($record, $data): void {
                                app(BudgetingService::class)->deleteItem(auth()->user(), $record, $this->itemForBudget($record, $data['budget_item_id']));
                                Notification::make()->success()->title(__('Budget item deleted'))->send();
                            });
                        }),
                ])->label(__('Budget items'))->icon('heroicon-o-calculator')->tooltip(__('Manage planned cost items')),
                Tables\Actions\Action::make('submit')
                    ->label(__('Submit'))
                    ->icon('heroicon-o-paper-airplane')
                    ->tooltip(__('Submit for approval'))
                    ->visible(fn (ProjectBudget $record): bool => $record->status === 'draft')
                    ->action(fn (ProjectBudget $record) => $this->run(fn () => app(BudgetingService::class)->submitBudget(auth()->user(), $record))),
                Tables\Actions\Action::make('approve')
                    ->label(__('Approve'))
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->tooltip(__('Approve budget'))
                    ->visible(fn (ProjectBudget $record): bool => $record->status === 'pending_approval')
                    ->action(fn (ProjectBudget $record) => $this->run(fn () => app(BudgetingService::class)->approveBudget(auth()->user(), $record))),
                Tables\Actions\Action::make('returnToDraft')
                    ->label(__('Return to draft'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->tooltip(__('Return to draft'))
                    ->visible(fn (ProjectBudget $record): bool => $record->status === 'pending_approval')
                    ->form([Forms\Components\Textarea::make('reason')->label(__('Reason'))->required()])
                    ->action(fn (ProjectBudget $record, array $data) => $this->run(fn () => app(BudgetingService::class)->rejectBudget(auth()->user(), $record, $data['reason']))),
                Tables\Actions\Action::make('cancel')
                    ->label(__('Cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->tooltip(__('Cancel draft budget'))
                    ->visible(fn (ProjectBudget $record): bool => in_array($record->status, ['draft', 'pending_approval'], true))
                    ->form([Forms\Components\Textarea::make('reason')->label(__('Reason'))->required()])
                    ->action(fn (ProjectBudget $record, array $data) => $this->run(fn () => app(BudgetingService::class)->cancelBudget(auth()->user(), $record, $data['reason']))),
                Tables\Actions\Action::make('createRevision')
                    ->label(__('Create revision'))
                    ->icon('heroicon-o-arrow-path')
                    ->tooltip(__('Create revision'))
                    ->visible(fn (ProjectBudget $record): bool => $record->status === 'approved')
                    ->action(fn (ProjectBudget $record) => $this->run(fn () => app(BudgetingService::class)->createRevision(auth()->user(), $record))),
            ]);
    }

    private function categoryForm(ProjectBudget $budget): array
    {
        return [
            Forms\Components\Select::make('parent_id')->label(__('Parent category'))->options($this->categoryOptions($budget))->searchable(),
            Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
            Forms\Components\TextInput::make('code')->label(__('Code')),
            Forms\Components\Textarea::make('description')->label(__('Description')),
            Forms\Components\TextInput::make('sort_order')->label(__('Sort Order'))->numeric()->default(0),
        ];
    }

    private function budgetInfolist(): array
    {
        return [
            \Filament\Infolists\Components\Section::make(__('Budget version'))
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('version_number')->label(__('Version'))->badge(),
                    \Filament\Infolists\Components\TextEntry::make('name')->label(__('Name'))->placeholder(__('Unnamed budget')),
                    \Filament\Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()->colors([
                        'gray' => ['draft', 'superseded', 'closed', 'cancelled'],
                        'warning' => 'pending_approval',
                        'success' => 'approved',
                    ])->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                    \Filament\Infolists\Components\TextEntry::make('approved_at')->label(__('Approved At'))->dateTime()->placeholder(__('—')),
                    \Filament\Infolists\Components\TextEntry::make('notes')->label(__('Notes'))->columnSpanFull()->placeholder(__('No notes')),
                ])->columns(4),

            \Filament\Infolists\Components\Tabs::make(__('Budget details'))
                ->tabs([
                    \Filament\Infolists\Components\Tabs\Tab::make(__('Categories'))
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
                                        'parent' => $category->parent?->name ?? __('Top-level category'),
                                        'description' => $category->description,
                                    ])->all())
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('name')->label(__('Name'))->weight('bold'),
                                    \Filament\Infolists\Components\TextEntry::make('code')->label(__('Code'))->placeholder(__('—')),
                                    \Filament\Infolists\Components\TextEntry::make('parent')->label(__('Parent category')),
                                    \Filament\Infolists\Components\TextEntry::make('description')->label(__('Description'))->columnSpanFull()->placeholder(__('No description')),
                                ])->columns(3),
                        ]),
                    \Filament\Infolists\Components\Tabs\Tab::make(__('Planned items'))
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
                                    \Filament\Infolists\Components\TextEntry::make('category')->label(__('Category'))->badge()->color('gray'),
                                    \Filament\Infolists\Components\TextEntry::make('name')->label(__('Name'))->weight('bold'),
                                    \Filament\Infolists\Components\TextEntry::make('code')->label(__('Code'))->placeholder(__('—')),
                                    \Filament\Infolists\Components\TextEntry::make('planned_amount')->label(__('Planned amount')),
                                    \Filament\Infolists\Components\TextEntry::make('quantity')->label(__('Quantity'))->placeholder(__('—')),
                                    \Filament\Infolists\Components\TextEntry::make('unit')->label(__('Unit'))->placeholder(__('—')),
                                    \Filament\Infolists\Components\TextEntry::make('description')->label(__('Description'))->columnSpanFull()->placeholder(__('No description')),
                                ])->columns(3),
                        ]),
                ]),
        ];
    }

    private function itemForm(): array
    {
        return [
            Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
            Forms\Components\TextInput::make('code')->label(__('Code')),
            Forms\Components\TextInput::make('planned_amount')->label(__('Planned amount'))->numeric()->required(),
            Forms\Components\TextInput::make('quantity')->label(__('Quantity'))->numeric(),
            Forms\Components\TextInput::make('unit')->label(__('Unit')),
            Forms\Components\TextInput::make('unit_cost')->label(__('Unit Cost'))->numeric(),
            Forms\Components\Textarea::make('description')->label(__('Description')),
            Forms\Components\Textarea::make('notes')->label(__('Notes')),
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
            Notification::make()->danger()->title(__('Cannot update budget'))->body(collect($exception->errors())->flatten()->first())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('Unauthorized'))->body(__('You do not have permission to perform this budget action.'))->send();
        }
    }
}
