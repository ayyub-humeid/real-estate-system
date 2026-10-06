<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\{BudgetItem, FinancialCommitment, Party};
use App\Services\BudgetingService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ProjectCommitmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'commitments';
    protected static ?string $title = 'Financial Commitments';

    public function isReadOnly(): bool { return false; }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('description')->searchable(),
            Tables\Columns\TextColumn::make('party.name')->label('Vendor')->placeholder('Unassigned'),
            Tables\Columns\TextColumn::make('budget_amount')->label('Project amount'),
            Tables\Columns\TextColumn::make('amendments_count')->counts('amendments')->label('Amendments'),
            Tables\Columns\TextColumn::make('status')->badge()->colors([
                'gray' => ['draft', 'released'],
                'info' => 'committed',
                'success' => 'fulfilled',
                'danger' => 'cancelled',
            ]),
        ])->headerActions([
            Tables\Actions\Action::make('createCommitment')
                ->label('Create commitment')->icon('heroicon-o-plus')
                ->visible(fn (): bool => auth()->user()->can('create', FinancialCommitment::class))
                ->form($this->commitmentForm())
                ->action(fn (array $data) => $this->run(fn () => app(BudgetingService::class)->createCommitment(auth()->user(), $this->getOwnerRecord(), $data))),
        ])->actions([
            Tables\Actions\ViewAction::make()
                ->label('Details')
                ->icon('heroicon-o-eye')
                ->tooltip('View commitment details and amendment history')
                ->modalWidth('4xl')
                ->infolist([
                    \Filament\Infolists\Components\Section::make('Commitment')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('description')->columnSpanFull(),
                            \Filament\Infolists\Components\TextEntry::make('status')->badge()->colors(['gray' => ['draft', 'released'], 'info' => 'committed', 'success' => 'fulfilled', 'danger' => 'cancelled']),
                            \Filament\Infolists\Components\TextEntry::make('party.name')->label('Vendor')->placeholder('Unassigned'),
                            \Filament\Infolists\Components\TextEntry::make('item.name')->label('Budget item')->placeholder('Unbudgeted'),
                            \Filament\Infolists\Components\TextEntry::make('amount')->money(fn (FinancialCommitment $record): string => $record->currency),
                            \Filament\Infolists\Components\TextEntry::make('budget_amount')->label('Project amount')->money(fn (FinancialCommitment $record): string => $record->project->currency),
                            \Filament\Infolists\Components\TextEntry::make('reference_number')->label('Reference')->placeholder('—'),
                            \Filament\Infolists\Components\TextEntry::make('notes')->columnSpanFull()->placeholder('No notes'),
                        ])->columns(3),
                    \Filament\Infolists\Components\Section::make('Amendment history')
                        ->schema([
                            \Filament\Infolists\Components\RepeatableEntry::make('amendments')
                                ->label('')
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('reason')->weight('bold'),
                                    \Filament\Infolists\Components\TextEntry::make('amount_change')->label('Original-currency change'),
                                    \Filament\Infolists\Components\TextEntry::make('budget_amount_change')->label('Project-currency change'),
                                    \Filament\Infolists\Components\TextEntry::make('status')->badge(),
                                    \Filament\Infolists\Components\TextEntry::make('requested_at')->label('Requested')->dateTime(),
                                ])->columns(3),
                        ])->collapsible(),
                ]),
            Tables\Actions\Action::make('edit')
                ->label('Edit')
                ->icon('heroicon-o-pencil-square')
                ->tooltip('Edit draft commitment')
                ->visible(fn (FinancialCommitment $record): bool => $record->status === 'draft' && auth()->user()->can('update', $record))
                ->fillForm(fn (FinancialCommitment $record): array => $record->only(['party_id', 'budget_item_id', 'description', 'reference_number', 'amount', 'currency', 'budget_amount', 'notes']))
                ->form($this->commitmentForm())
                ->action(fn (FinancialCommitment $record, array $data) => $this->run(fn () => app(BudgetingService::class)->updateCommitment(auth()->user(), $record, $data))),
            Tables\Actions\Action::make('commit')
                ->icon('heroicon-o-check')->tooltip('Commit obligation')->visible(fn (FinancialCommitment $r) => $r->status === 'draft')
                ->form([Forms\Components\Textarea::make('reason')->label('Over-budget reason (required only when over budget)')])
                ->action(fn (FinancialCommitment $r, array $data) => $this->run(fn () => app(BudgetingService::class)->commit(auth()->user(), $r, $data['reason'] ?? null))),
            Tables\Actions\Action::make('amend')
                ->icon('heroicon-o-pencil-square')->tooltip('Request value amendment')->visible(fn (FinancialCommitment $r) => $r->status === 'committed')
                ->form([Forms\Components\TextInput::make('amount_change')->numeric()->required(), Forms\Components\TextInput::make('budget_amount_change')->numeric()->required(), Forms\Components\Textarea::make('reason')->required()])
                ->action(fn (FinancialCommitment $r, array $data) => $this->run(fn () => app(BudgetingService::class)->requestCommitmentAmendment(auth()->user(), $r, $data))),
            Tables\Actions\Action::make('approvePendingAmendment')
                ->icon('heroicon-o-check-badge')->color('success')->tooltip('Approve pending amendment')
                ->visible(fn (FinancialCommitment $r) => $r->amendments()->where('status', 'pending_approval')->exists())
                ->form(fn (FinancialCommitment $r) => [Forms\Components\Select::make('amendment_id')->label('Pending amendment')->options($r->amendments()->where('status', 'pending_approval')->pluck('reason', 'id'))->required()])
                ->action(function (FinancialCommitment $record, array $data): void {
                    $amendment = $record->amendments()->whereKey($data['amendment_id'])->firstOrFail();
                    $this->run(fn () => app(BudgetingService::class)->approveCommitmentAmendment(auth()->user(), $amendment));
                }),
            Tables\Actions\Action::make('release')
                ->icon('heroicon-o-lock-open')->tooltip('Release obligation')->visible(fn (FinancialCommitment $r) => $r->status === 'committed')
                ->form([Forms\Components\Textarea::make('reason')->required()])
                ->action(fn (FinancialCommitment $r, array $data) => $this->run(fn () => app(BudgetingService::class)->releaseCommitment(auth()->user(), $r, $data['reason']))),
            Tables\Actions\Action::make('cancel')
                ->icon('heroicon-o-x-circle')->color('danger')->tooltip('Cancel obligation')->visible(fn (FinancialCommitment $r) => in_array($r->status, ['draft', 'committed'], true))
                ->form([Forms\Components\Textarea::make('reason')->required()])
                ->action(fn (FinancialCommitment $r, array $data) => $this->run(fn () => app(BudgetingService::class)->cancelCommitment(auth()->user(), $r, $data['reason']))),
        ]);
    }

    private function commitmentForm(): array
    {
        return [
            Forms\Components\Select::make('party_id')->label('Vendor')->options(Party::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->pluck('name', 'id'))->searchable(),
            Forms\Components\Select::make('budget_item_id')
                ->label('Budget item')
                ->options(fn (): array => BudgetItem::withoutGlobalScopes()
                    ->where('company_id', $this->getOwnerRecord()->company_id)
                    ->whereHas('category.budget', fn ($query) => $query->where('project_id', $this->getOwnerRecord()->id)->where('status', 'approved'))
                    ->with('category')->orderBy('name')->get()
                    ->mapWithKeys(fn (BudgetItem $item): array => [$item->id => "{$item->category->name} — {$item->name}"])->all())
                ->helperText('Only items from this project’s approved budget are available.')
                ->required(fn (): bool => ! auth()->user()->can('create_unbudgeted_commitment'))
                ->searchable(),
            Forms\Components\Textarea::make('description')->required(),
            Forms\Components\TextInput::make('reference_number')->label('Reference number'),
            Forms\Components\TextInput::make('amount')->numeric()->required(),
            Forms\Components\TextInput::make('currency')->default($this->getOwnerRecord()->currency)->required(),
            Forms\Components\TextInput::make('budget_amount')->label('Project-currency amount')
                ->helperText(fn (): string => 'Project currency: ' . $this->getOwnerRecord()->currency)
                ->numeric()->required(),
            Forms\Components\Textarea::make('notes'),
        ];
    }

    private function run(callable $operation): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Cannot update commitment')
                ->body(collect($exception->errors())->flatten()->first())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title('Unauthorized')
                ->body('You do not have permission to perform this commitment action.')->send();
        }
    }
}
