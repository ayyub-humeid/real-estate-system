<?php
namespace App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\{ActualCost, BudgetItem, FinancialCommitment, Party};
use App\Services\BudgetingService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
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
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('party.name')->label('Vendor'),
            Tables\Columns\TextColumn::make('budget_amount')->label('Project amount'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('incurred_at')->date()
        ])->headerActions([
                    Tables\Actions\Action::make('recordActualCost')->label('Record actual cost')->icon('heroicon-o-plus')->form(fn() => [
                        Forms\Components\Select::make('party_id')
                            ->label('Party')
                            ->options(Party::withoutGlobalScopes()
                                ->where('company_id', $this->getOwnerRecord()->company_id)
                                ->pluck('name', 'id'))->required()->searchable(),
                        Forms\Components\Select::make('financial_commitment_id')
                            ->label('Financial commitment')
                            ->options(FinancialCommitment::withoutGlobalScopes()
                                ->where('project_id', $this->getOwnerRecord()->id)
                                ->pluck('description', 'id'))->searchable(),
                        Forms\Components\Select::make('budget_item_id')
                            ->label('Budget item')
                            ->options(BudgetItem::withoutGlobalScopes()
                                ->where('company_id', $this->getOwnerRecord()->company_id)
                                ->pluck('name', 'id'))->searchable(),
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\TextInput::make('amount')->numeric()->required(),
                        Forms\Components\TextInput::make('currency')->default($this->getOwnerRecord()->currency)->required(),
                        Forms\Components\TextInput::make('budget_amount')->numeric()->required(),
                        Forms\Components\DatePicker::make('incurred_at')->required()->default(now())
                    ])->action(fn(array $data) => app(BudgetingService::class)->createActualCost(auth()->user(), $this->getOwnerRecord(), $data))
                ])->actions([
                    Tables\Actions\Action::make('submit')
                        ->icon('heroicon-o-paper-airplane')
                        ->tooltip('Submit for approval')
                        ->visible(fn(ActualCost $r) => $r->status === 'draft')
                        ->action(fn(ActualCost $r) => app(BudgetingService::class)
                            ->submitActualCost(auth()->user(), $r)),
                    Tables\Actions\Action::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->tooltip('Approve cost')->color('success')->visible(fn(ActualCost $r) => $r->status === 'pending_approval')
                        ->action(fn(ActualCost $r) => app(BudgetingService::class)->approveActualCost(auth()->user(), $r)),
                    Tables\Actions\Action::make('correct')
                        ->icon('heroicon-o-arrow-path')
                        ->tooltip('Create corrective offset')
                        ->visible(fn(ActualCost $r) => $r->status === 'approved')
                        ->form([Forms\Components\TextInput::make('amount')->numeric()->required(), Forms\Components\TextInput::make('budget_amount')->numeric()->required(), Forms\Components\Textarea::make('correction_reason')->required(), Forms\Components\DatePicker::make('incurred_at')->default(now())])
                        ->action(fn(ActualCost $r, array $data) => app(BudgetingService::class)->correctActualCost(auth()->user(), $r, $data))
                ]);
    }
}
