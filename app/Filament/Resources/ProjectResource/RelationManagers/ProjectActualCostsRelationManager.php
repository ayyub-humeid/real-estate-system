<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\{ActualCost, BudgetItem, FinancialCommitment, Party};
use App\Services\BudgetingService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ProjectActualCostsRelationManager extends RelationManager
{
    protected static string $relationship = 'actualCosts';
    protected static ?string $title = 'Actual Costs';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Actual Costs');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('No actual costs recorded yet'))
            ->emptyStateDescription(__('Record actual costs incurred against project budgets or commitments.'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable(),
                Tables\Columns\TextColumn::make('party.name')->label(__('Vendor')),
                Tables\Columns\TextColumn::make('budget_amount')->label(__('Project amount')),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()->colors([
                    'gray' => 'draft', 'warning' => 'pending_approval', 'success' => 'approved', 'danger' => ['rejected', 'cancelled'],
                ])->formatStateUsing(fn ($state) => match ($state) {
                    'draft' => __('Draft'),
                    'pending_approval' => __('Pending Approval'),
                    'approved' => __('Approved'),
                    'rejected' => __('Rejected'),
                    'cancelled' => __('Cancelled'),
                    default => $state,
                }),
                Tables\Columns\TextColumn::make('incurred_at')->label(__('Incurred on'))->date(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('recordActualCost')->label(__('Record actual cost'))->icon('heroicon-o-plus')
                    ->visible(fn (): bool => auth()->user()->can('create', ActualCost::class))
                    ->form($this->actualCostForm())
                    ->action(fn (array $data) => $this->run(fn () => app(BudgetingService::class)->createActualCost(auth()->user(), $this->getOwnerRecord(), $data))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('Details'))->icon('heroicon-o-eye')
                    ->tooltip(__('View actual cost details and payment allocations'))->modalWidth('4xl')
                    ->infolist([
                        \Filament\Infolists\Components\Section::make(__('Actual cost'))->schema([
                            \Filament\Infolists\Components\TextEntry::make('name')->label(__('Name'))->weight('bold'),
                            \Filament\Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()->colors(['gray' => 'draft', 'warning' => 'pending_approval', 'success' => 'approved', 'danger' => ['rejected', 'cancelled']])
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'draft' => __('Draft'),
                                    'pending_approval' => __('Pending Approval'),
                                    'approved' => __('Approved'),
                                    'rejected' => __('Rejected'),
                                    'cancelled' => __('Cancelled'),
                                    default => $state,
                                }),
                            \Filament\Infolists\Components\TextEntry::make('party.name')->label(__('Vendor')),
                            \Filament\Infolists\Components\TextEntry::make('commitment.description')->label(__('Commitment'))->placeholder(__('No commitment')),
                            \Filament\Infolists\Components\TextEntry::make('item.name')->label(__('Budget item'))->placeholder(__('Unbudgeted')),
                            \Filament\Infolists\Components\TextEntry::make('amount')->label(__('Amount'))->money(fn (ActualCost $record): string => $record->currency),
                            \Filament\Infolists\Components\TextEntry::make('budget_amount')->label(__('Project amount'))->money(fn (ActualCost $record): string => $record->project->currency),
                            \Filament\Infolists\Components\TextEntry::make('incurred_at')->label(__('Incurred on'))->date(),
                            \Filament\Infolists\Components\TextEntry::make('correction_reason')->label(__('Correction reason'))->columnSpanFull()->placeholder(__('—')),
                        ])->columns(3),
                        \Filament\Infolists\Components\Section::make(__('Payment allocations'))->schema([
                            \Filament\Infolists\Components\RepeatableEntry::make('allocations')->label('')->schema([
                                \Filament\Infolists\Components\TextEntry::make('payment.reference_number')->label(__('Payment reference'))->placeholder(__('—')),
                                \Filament\Infolists\Components\TextEntry::make('payment_amount')->label(__('Payment amount')),
                                \Filament\Infolists\Components\TextEntry::make('project_amount')->label(__('Project amount')),
                            ])->columns(3),
                        ])->collapsible(),
                    ]),
                Tables\Actions\Action::make('edit')->label(__('Edit'))->icon('heroicon-o-pencil-square')->tooltip(__('Edit draft actual cost'))
                    ->visible(fn (ActualCost $record): bool => $record->status === 'draft' && auth()->user()->can('update', $record))
                    ->fillForm(fn (ActualCost $record): array => $record->only(['party_id', 'financial_commitment_id', 'budget_item_id', 'name', 'amount', 'currency', 'budget_amount', 'incurred_at']))
                    ->form($this->actualCostForm())
                    ->action(fn (ActualCost $record, array $data) => $this->run(fn () => app(BudgetingService::class)->updateActualCost(auth()->user(), $record, $data))),
                Tables\Actions\Action::make('submit')->label(__('Submit'))->icon('heroicon-o-paper-airplane')->tooltip(__('Submit for approval'))
                    ->visible(fn (ActualCost $record): bool => $record->status === 'draft' && auth()->user()->can('submit', $record))
                    ->action(fn (ActualCost $record) => $this->run(fn () => app(BudgetingService::class)->submitActualCost(auth()->user(), $record))),
                Tables\Actions\Action::make('approve')->label(__('Approve'))->icon('heroicon-o-check-circle')->tooltip(__('Approve cost'))->color('success')
                    ->visible(fn (ActualCost $record): bool => $record->status === 'pending_approval' && auth()->user()->can('approve', $record))
                    ->action(fn (ActualCost $record) => $this->run(fn () => app(BudgetingService::class)->approveActualCost(auth()->user(), $record))),
                Tables\Actions\Action::make('correct')->label(__('Create corrective offset'))->icon('heroicon-o-arrow-path')->tooltip(__('Create corrective offset'))
                    ->visible(fn (ActualCost $record): bool => $record->status === 'approved' && auth()->user()->can('correct', $record))
                    ->form([
                        Forms\Components\TextInput::make('amount')->label(__('Original-currency correction'))->helperText(__('Enter a negative amount to reduce the original cost.'))->numeric()->required(),
                        Forms\Components\TextInput::make('budget_amount')->label(__('Project-currency correction'))->helperText(fn (): string => __('Project currency:') . ' ' . $this->getOwnerRecord()->currency)->numeric()->required(),
                        Forms\Components\Textarea::make('correction_reason')->label(__('Correction reason'))->required(),
                        Forms\Components\DatePicker::make('incurred_at')->label(__('Incurred on'))->default(now())->maxDate(today())
                            ->helperText(__('Actual-cost dates cannot be in the future.')),
                    ])
                    ->action(fn (ActualCost $record, array $data) => $this->run(fn () => app(BudgetingService::class)->correctActualCost(auth()->user(), $record, $data))),
            ]);
    }

    private function actualCostForm(): array
    {
        return [
            Forms\Components\Select::make('party_id')->label(__('Vendor'))
                ->options(Party::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->pluck('name', 'id'))
                ->required()->searchable(),
            Forms\Components\Select::make('financial_commitment_id')->label(__('Financial commitment'))
                ->options(fn (): array => FinancialCommitment::withoutGlobalScopes()
                    ->where('project_id', $this->getOwnerRecord()->id)->where('status', 'committed')->orderBy('description')->get()
                    ->mapWithKeys(fn (FinancialCommitment $commitment): array => [$commitment->id => ($commitment->reference_number ? "{$commitment->reference_number} — " : '') . $commitment->description])->all())
                ->searchable(),
            Forms\Components\Select::make('budget_item_id')->label(__('Budget item'))
                ->options(fn (): array => BudgetItem::withoutGlobalScopes()
                    ->where('company_id', $this->getOwnerRecord()->company_id)
                    ->whereHas('category.budget', fn ($query) => $query->where('project_id', $this->getOwnerRecord()->id)->where('status', 'approved'))
                    ->with('category')->orderBy('name')->get()
                    ->mapWithKeys(fn (BudgetItem $item): array => [$item->id => "{$item->category->name} — {$item->name}"])->all())
                ->helperText(__('Choose the linked commitment, or an item from this project’s approved budget.'))
                ->searchable(),
            Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
            Forms\Components\TextInput::make('amount')->label(__('Amount'))->numeric()->required(),
            Forms\Components\TextInput::make('currency')->label(__('Currency'))->default($this->getOwnerRecord()->currency)->required(),
            Forms\Components\TextInput::make('budget_amount')->label(__('Project-currency amount'))
                ->helperText(fn (): string => __('Project currency:') . ' ' . $this->getOwnerRecord()->currency)->numeric()->required(),
            Forms\Components\DatePicker::make('incurred_at')->label(__('Incurred on'))->required()->default(now())->maxDate(today())
                ->helperText(__('Actual-cost dates cannot be in the future.')),
        ];
    }

    private function run(callable $operation): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('Cannot update actual cost'))
                ->body(collect($exception->errors())->flatten()->first())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('Unauthorized'))
                ->body(__('You do not have permission to perform this actual-cost action.'))->send();
        }
    }
}
