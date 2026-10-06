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

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('party.name')->label('Vendor'),
                Tables\Columns\TextColumn::make('budget_amount')->label('Project amount'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'gray' => 'draft', 'warning' => 'pending_approval', 'success' => 'approved', 'danger' => ['rejected', 'cancelled'],
                ]),
                Tables\Columns\TextColumn::make('incurred_at')->date(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('recordActualCost')->label('Record actual cost')->icon('heroicon-o-plus')
                    ->visible(fn (): bool => auth()->user()->can('create', ActualCost::class))
                    ->form($this->actualCostForm())
                    ->action(fn (array $data) => $this->run(fn () => app(BudgetingService::class)->createActualCost(auth()->user(), $this->getOwnerRecord(), $data))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Details')->icon('heroicon-o-eye')
                    ->tooltip('View actual cost details and payment allocations')->modalWidth('4xl')
                    ->infolist([
                        \Filament\Infolists\Components\Section::make('Actual cost')->schema([
                            \Filament\Infolists\Components\TextEntry::make('name')->weight('bold'),
                            \Filament\Infolists\Components\TextEntry::make('status')->badge()->colors(['gray' => 'draft', 'warning' => 'pending_approval', 'success' => 'approved', 'danger' => ['rejected', 'cancelled']]),
                            \Filament\Infolists\Components\TextEntry::make('party.name')->label('Vendor'),
                            \Filament\Infolists\Components\TextEntry::make('commitment.description')->label('Commitment')->placeholder('No commitment'),
                            \Filament\Infolists\Components\TextEntry::make('item.name')->label('Budget item')->placeholder('Unbudgeted'),
                            \Filament\Infolists\Components\TextEntry::make('amount')->money(fn (ActualCost $record): string => $record->currency),
                            \Filament\Infolists\Components\TextEntry::make('budget_amount')->label('Project amount')->money(fn (ActualCost $record): string => $record->project->currency),
                            \Filament\Infolists\Components\TextEntry::make('incurred_at')->label('Incurred on')->date(),
                            \Filament\Infolists\Components\TextEntry::make('correction_reason')->label('Correction reason')->columnSpanFull()->placeholder('—'),
                        ])->columns(3),
                        \Filament\Infolists\Components\Section::make('Payment allocations')->schema([
                            \Filament\Infolists\Components\RepeatableEntry::make('allocations')->label('')->schema([
                                \Filament\Infolists\Components\TextEntry::make('payment.reference_number')->label('Payment reference')->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('payment_amount')->label('Payment amount'),
                                \Filament\Infolists\Components\TextEntry::make('project_amount')->label('Project amount'),
                            ])->columns(3),
                        ])->collapsible(),
                    ]),
                Tables\Actions\Action::make('edit')->label('Edit')->icon('heroicon-o-pencil-square')->tooltip('Edit draft actual cost')
                    ->visible(fn (ActualCost $record): bool => $record->status === 'draft' && auth()->user()->can('update', $record))
                    ->fillForm(fn (ActualCost $record): array => $record->only(['party_id', 'financial_commitment_id', 'budget_item_id', 'name', 'amount', 'currency', 'budget_amount', 'incurred_at']))
                    ->form($this->actualCostForm())
                    ->action(fn (ActualCost $record, array $data) => $this->run(fn () => app(BudgetingService::class)->updateActualCost(auth()->user(), $record, $data))),
                Tables\Actions\Action::make('submit')->icon('heroicon-o-paper-airplane')->tooltip('Submit for approval')
                    ->visible(fn (ActualCost $record): bool => $record->status === 'draft' && auth()->user()->can('submit', $record))
                    ->action(fn (ActualCost $record) => $this->run(fn () => app(BudgetingService::class)->submitActualCost(auth()->user(), $record))),
                Tables\Actions\Action::make('approve')->icon('heroicon-o-check-circle')->tooltip('Approve cost')->color('success')
                    ->visible(fn (ActualCost $record): bool => $record->status === 'pending_approval' && auth()->user()->can('approve', $record))
                    ->action(fn (ActualCost $record) => $this->run(fn () => app(BudgetingService::class)->approveActualCost(auth()->user(), $record))),
                Tables\Actions\Action::make('correct')->icon('heroicon-o-arrow-path')->tooltip('Create corrective offset')
                    ->visible(fn (ActualCost $record): bool => $record->status === 'approved' && auth()->user()->can('correct', $record))
                    ->form([
                        Forms\Components\TextInput::make('amount')->label('Original-currency correction')->helperText('Enter a negative amount to reduce the original cost.')->numeric()->required(),
                        Forms\Components\TextInput::make('budget_amount')->label('Project-currency correction')->helperText(fn (): string => 'Project currency: ' . $this->getOwnerRecord()->currency)->numeric()->required(),
                        Forms\Components\Textarea::make('correction_reason')->required(),
                        Forms\Components\DatePicker::make('incurred_at')->default(now())->maxDate(today())
                            ->helperText('Actual-cost dates cannot be in the future.'),
                    ])
                    ->action(fn (ActualCost $record, array $data) => $this->run(fn () => app(BudgetingService::class)->correctActualCost(auth()->user(), $record, $data))),
            ]);
    }

    private function actualCostForm(): array
    {
        return [
            Forms\Components\Select::make('party_id')->label('Vendor')
                ->options(Party::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->pluck('name', 'id'))
                ->required()->searchable(),
            Forms\Components\Select::make('financial_commitment_id')->label('Financial commitment')
                ->options(fn (): array => FinancialCommitment::withoutGlobalScopes()
                    ->where('project_id', $this->getOwnerRecord()->id)->where('status', 'committed')->orderBy('description')->get()
                    ->mapWithKeys(fn (FinancialCommitment $commitment): array => [$commitment->id => ($commitment->reference_number ? "{$commitment->reference_number} — " : '') . $commitment->description])->all())
                ->searchable(),
            Forms\Components\Select::make('budget_item_id')->label('Budget item')
                ->options(fn (): array => BudgetItem::withoutGlobalScopes()
                    ->where('company_id', $this->getOwnerRecord()->company_id)
                    ->whereHas('category.budget', fn ($query) => $query->where('project_id', $this->getOwnerRecord()->id)->where('status', 'approved'))
                    ->with('category')->orderBy('name')->get()
                    ->mapWithKeys(fn (BudgetItem $item): array => [$item->id => "{$item->category->name} — {$item->name}"])->all())
                ->helperText('Choose the linked commitment, or an item from this project’s approved budget.')
                ->searchable(),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('amount')->numeric()->required(),
            Forms\Components\TextInput::make('currency')->default($this->getOwnerRecord()->currency)->required(),
            Forms\Components\TextInput::make('budget_amount')->label('Project-currency amount')
                ->helperText(fn (): string => 'Project currency: ' . $this->getOwnerRecord()->currency)->numeric()->required(),
            Forms\Components\DatePicker::make('incurred_at')->required()->default(now())->maxDate(today())
                ->helperText('Actual-cost dates cannot be in the future.'),
        ];
    }

    private function run(callable $operation): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Cannot update actual cost')
                ->body(collect($exception->errors())->flatten()->first())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title('Unauthorized')
                ->body('You do not have permission to perform this actual-cost action.')->send();
        }
    }
}
