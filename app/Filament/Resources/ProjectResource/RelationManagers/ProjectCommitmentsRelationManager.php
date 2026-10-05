<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\{BudgetItem, FinancialCommitment, Party};
use App\Services\BudgetingService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

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
            Tables\Columns\TextColumn::make('status')->badge(),
        ])->headerActions([
            Tables\Actions\Action::make('createCommitment')
                ->label('Create commitment')->icon('heroicon-o-plus')
                ->form(fn () => [
                    Forms\Components\Select::make('party_id')->options(Party::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->pluck('name', 'id'))->searchable(),
                    Forms\Components\Select::make('budget_item_id')->options(BudgetItem::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->pluck('name', 'id'))->searchable(),
                    Forms\Components\Textarea::make('description')->required(),
                    Forms\Components\TextInput::make('amount')->numeric()->required(),
                    Forms\Components\TextInput::make('currency')->default($this->getOwnerRecord()->currency)->required(),
                    Forms\Components\TextInput::make('budget_amount')->label('Project-currency amount')->numeric()->required(),
                    Forms\Components\Textarea::make('notes'),
                ])->action(fn (array $data) => app(BudgetingService::class)->createCommitment(auth()->user(), $this->getOwnerRecord(), $data)),
        ])->actions([
            Tables\Actions\Action::make('commit')
                ->icon('heroicon-o-check')->tooltip('Commit obligation')->visible(fn (FinancialCommitment $r) => $r->status === 'draft')
                ->form([Forms\Components\Textarea::make('reason')->label('Over-budget reason (required only when over budget)')])
                ->action(fn (FinancialCommitment $r, array $data) => app(BudgetingService::class)->commit(auth()->user(), $r, $data['reason'] ?? null)),
            Tables\Actions\Action::make('amend')
                ->icon('heroicon-o-pencil-square')->tooltip('Request value amendment')->visible(fn (FinancialCommitment $r) => $r->status === 'committed')
                ->form([Forms\Components\TextInput::make('amount_change')->numeric()->required(), Forms\Components\TextInput::make('budget_amount_change')->numeric()->required(), Forms\Components\Textarea::make('reason')->required()])
                ->action(fn (FinancialCommitment $r, array $data) => app(BudgetingService::class)->requestCommitmentAmendment(auth()->user(), $r, $data)),
            Tables\Actions\Action::make('approvePendingAmendment')
                ->icon('heroicon-o-check-badge')->color('success')->tooltip('Approve pending amendment')
                ->visible(fn (FinancialCommitment $r) => $r->amendments()->where('status', 'pending_approval')->exists())
                ->form(fn (FinancialCommitment $r) => [Forms\Components\Select::make('amendment_id')->label('Pending amendment')->options($r->amendments()->where('status', 'pending_approval')->pluck('reason', 'id'))->required()])
                ->action(function (FinancialCommitment $record, array $data): void {
                    $amendment = $record->amendments()->whereKey($data['amendment_id'])->firstOrFail();
                    app(BudgetingService::class)->approveCommitmentAmendment(auth()->user(), $amendment);
                }),
            Tables\Actions\Action::make('release')
                ->icon('heroicon-o-lock-open')->tooltip('Release obligation')->visible(fn (FinancialCommitment $r) => $r->status === 'committed')
                ->form([Forms\Components\Textarea::make('reason')->required()])
                ->action(fn (FinancialCommitment $r, array $data) => app(BudgetingService::class)->releaseCommitment(auth()->user(), $r, $data['reason'])),
            Tables\Actions\Action::make('cancel')
                ->icon('heroicon-o-x-circle')->color('danger')->tooltip('Cancel obligation')->visible(fn (FinancialCommitment $r) => in_array($r->status, ['draft', 'committed'], true))
                ->form([Forms\Components\Textarea::make('reason')->required()])
                ->action(fn (FinancialCommitment $r, array $data) => app(BudgetingService::class)->cancelCommitment(auth()->user(), $r, $data['reason'])),
        ]);
    }
}
