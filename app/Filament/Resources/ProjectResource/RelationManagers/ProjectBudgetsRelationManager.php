<?php
namespace App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\{BudgetCategory, ProjectBudget};
use App\Services\BudgetingService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
class ProjectBudgetsRelationManager extends RelationManager
{
    protected static string $relationship = 'budgets';
    protected static ?string $title = 'Budgets';
    public function isReadOnly(): bool
    {
        return false;
    }
    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('version_number')
                ->label('Version')->badge(),
            Tables\Columns\TextColumn::make('name')->placeholder('Unnamed'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('approved_at')->dateTime()->placeholder('—')
        ])->headerActions([
                    Tables\Actions\Action::make('createBudget')
                        ->label('Create budget')
                        ->icon('heroicon-o-plus')
                        ->form([
                            Forms\Components\TextInput::make('name'),
                            Forms\Components\Textarea::make('notes')
                        ])->action(fn(array $data) => app(BudgetingService::class)->createBudget(auth()->user(), $this->getOwnerRecord(), $data))
                ])
            ->actions([
                Tables\Actions\Action::make('manage')
                    ->label('Manage structure')
                    ->icon('heroicon-o-squares-2x2')
                    ->tooltip('Manage categories and budget items')
                    ->url(fn(ProjectBudget $r) => \App\Filament\Resources\ProjectBudgetResource::getUrl('view', ['record' => $r])),
                Tables\Actions\Action::make('submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->tooltip('Submit for approval')
                    ->visible(fn(ProjectBudget $r) => $r->status === 'draft')
                    ->action(fn(ProjectBudget $r) => app(BudgetingService::class)->submitBudget(auth()->user(), $r)),
                Tables\Actions\Action::make('approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->tooltip('Approve budget')
                    ->visible(fn(ProjectBudget $r) => $r->status === 'pending_approval')
                    ->action(fn(ProjectBudget $r) => app(BudgetingService::class)->approveBudget(auth()->user(), $r)),
                Tables\Actions\Action::make('returnToDraft')
                    ->label('Return to draft')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn(ProjectBudget $r) => $r->status === 'pending_approval')
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(fn(ProjectBudget $r, array $data) => app(BudgetingService::class)->rejectBudget(auth()->user(), $r, $data['reason'])),
                Tables\Actions\Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->tooltip('Cancel draft budget')
                    ->visible(fn(ProjectBudget $r) => in_array($r->status, ['draft', 'pending_approval'], true))
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(fn(ProjectBudget $r, array $data) => app(BudgetingService::class)->cancelBudget(auth()->user(), $r, $data['reason'])),
                Tables\Actions\Action::make('createRevision')
                    ->label('Create revision')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn(ProjectBudget $r) => $r->status === 'approved')
                    ->action(fn(ProjectBudget $r) => app(BudgetingService::class)->createRevision(auth()->user(), $r))
            ]);
    }
}
