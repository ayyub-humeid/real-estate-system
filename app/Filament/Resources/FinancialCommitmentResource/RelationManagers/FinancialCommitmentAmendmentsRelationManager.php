<?php

namespace App\Filament\Resources\FinancialCommitmentResource\RelationManagers;

use App\Services\BudgetingService;
use App\Models\FinancialCommitmentAmendment;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class FinancialCommitmentAmendmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'amendments';

    protected static ?string $title = 'Change Orders';

    public static function getModelLabel(): string
    {
        return __('Change Order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Change Orders');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('No change orders yet'))
            ->emptyStateDescription(__('Request a change order to amend this commitment\'s value.'))
            ->columns([
                Tables\Columns\TextColumn::make('reason')->label(__('Reason'))->searchable(),
                Tables\Columns\TextColumn::make('amount_change')->label(__('Original-currency change'))
                    ->formatStateUsing(fn (FinancialCommitmentAmendment $record): string => number_format((float) $record->amount_change, 2) . ' ' . $record->financialCommitment->currency),
                Tables\Columns\TextColumn::make('budget_amount_change')->label(__('Project-currency change'))
                    ->formatStateUsing(fn (FinancialCommitmentAmendment $record): string => number_format((float) $record->budget_amount_change, 2) . ' ' . $record->financialCommitment->project->currency),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()->colors([
                    'gray' => 'draft', 'warning' => 'pending_approval', 'success' => 'approved', 'danger' => 'rejected',
                ])->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                Tables\Columns\TextColumn::make('requested_at')->label(__('Requested at'))->dateTime(),
                Tables\Columns\TextColumn::make('approved_at')->label(__('Approved at'))->dateTime()->placeholder(__('—')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('requestAmendment')
                    ->label(__('Request change order'))
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\TextInput::make('amount_change')
                            ->label(__('Original-currency change'))
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('budget_amount_change')
                            ->label(__('Project-currency change'))
                            ->numeric()
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label(__('Reason'))
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->run(fn () => app(BudgetingService::class)->requestCommitmentAmendment(auth()->user(), $this->getOwnerRecord(), $data))),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label(__('Approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (FinancialCommitmentAmendment $record): bool => $record->status === 'pending_approval' && auth()->user()->can('approve', $record))
                    ->requiresConfirmation()
                    ->action(fn (FinancialCommitmentAmendment $record) => $this->run(fn () => app(BudgetingService::class)->approveCommitmentAmendment(auth()->user(), $record))),
            ]);
    }

    private function run(callable $operation): void
    {
        try {
            $operation();
            Notification::make()->success()->title(__('Change order requested'))->send();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('Cannot request change order'))
                ->body(collect($exception->errors())->flatten()->first())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('Unauthorized'))
                ->body(__('You do not have permission to request this change order.'))->send();
        }
    }
}
