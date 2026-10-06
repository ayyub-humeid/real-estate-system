<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\{ActualCost, Party, Payment};
use App\Services\BudgetingService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ProjectPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $title = 'Payments';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('party.name')->label('Vendor'),
                Tables\Columns\TextColumn::make('amount'),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('project_amount')->label('Project amount'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'warning' => 'pending',
                    'success' => 'completed',
                    'danger' => ['failed', 'voided'],
                ]),
                Tables\Columns\TextColumn::make('payment_date')->date(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('recordPayment')
                    ->label('Record payment')
                    ->icon('heroicon-o-plus')
                    ->form(fn (): array => [
                        Forms\Components\Select::make('party_id')->label('Vendor')
                            ->options(Party::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->pluck('name', 'id'))
                            ->required()->searchable(),
                        Forms\Components\TextInput::make('amount')->numeric()->required(),
                        Forms\Components\TextInput::make('currency')->default($this->getOwnerRecord()->currency)->required(),
                        Forms\Components\TextInput::make('project_amount')->label('Project-currency amount')
                            ->helperText(fn (): string => 'Project currency: ' . $this->getOwnerRecord()->currency)
                            ->numeric()->required(),
                        Forms\Components\DatePicker::make('payment_date')->default(now())->maxDate(today())->required()
                            ->helperText('Payment dates cannot be in the future.'),
                        Forms\Components\TextInput::make('reference_number')->label('Reference number'),
                        Forms\Components\Select::make('payment_method')->label('Payment method')->options([
                            'cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'card' => 'Card',
                        ]),
                    ])
                    ->action(fn (array $data) => $this->run(fn () => app(BudgetingService::class)->recordPayment(auth()->user(), $this->getOwnerRecord(), $data))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Details')->icon('heroicon-o-eye')
                    ->tooltip('View payment details and cost allocations')->modalWidth('4xl')
                    ->infolist([
                        \Filament\Infolists\Components\Section::make('Payment')->schema([
                            \Filament\Infolists\Components\TextEntry::make('party.name')->label('Vendor'),
                            \Filament\Infolists\Components\TextEntry::make('status')->badge()->colors(['warning' => 'pending', 'success' => 'completed', 'danger' => ['failed', 'voided']]),
                            \Filament\Infolists\Components\TextEntry::make('direction')->badge(),
                            \Filament\Infolists\Components\TextEntry::make('amount')->money(fn (Payment $record): string => $record->currency),
                            \Filament\Infolists\Components\TextEntry::make('project_amount')->label('Project amount')->money(fn (Payment $record): string => $record->project->currency),
                            \Filament\Infolists\Components\TextEntry::make('payment_date')->label('Payment date')->date(),
                            \Filament\Infolists\Components\TextEntry::make('payment_method')->label('Payment method')->placeholder('—'),
                            \Filament\Infolists\Components\TextEntry::make('reference_number')->label('Reference number')->placeholder('—'),
                            \Filament\Infolists\Components\TextEntry::make('void_reason')->label('Void reason')->columnSpanFull()->placeholder('—'),
                        ])->columns(3),
                        \Filament\Infolists\Components\Section::make('Cost allocations')->schema([
                            \Filament\Infolists\Components\RepeatableEntry::make('allocations')->label('')->schema([
                                \Filament\Infolists\Components\TextEntry::make('allocatable.name')->label('Actual cost'),
                                \Filament\Infolists\Components\TextEntry::make('payment_amount')->label('Payment amount'),
                                \Filament\Infolists\Components\TextEntry::make('actual_cost_amount')->label('Cost amount'),
                                \Filament\Infolists\Components\TextEntry::make('project_amount')->label('Project amount'),
                            ])->columns(4),
                        ])->collapsible(),
                    ]),
                Tables\Actions\Action::make('allocate')->icon('heroicon-o-arrow-right-circle')->tooltip('Allocate to approved cost')
                    ->visible(fn (Payment $record): bool => $record->status === 'completed')
                    ->form(fn (Payment $record): array => [
                        Forms\Components\Select::make('actual_cost_id')->label('Approved actual cost')
                            ->options(ActualCost::withoutGlobalScopes()->where('project_id', $record->project_id)->where('party_id', $record->party_id)->where('status', 'approved')->pluck('name', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('payment_amount')->numeric()->required(),
                        Forms\Components\TextInput::make('actual_cost_amount')->numeric()->required(),
                        Forms\Components\TextInput::make('project_amount')->label('Project amount')->numeric()->required(),
                    ])
                    ->action(function (Payment $record, array $data) {
                        $cost = ActualCost::withoutGlobalScopes()->findOrFail($data['actual_cost_id']);
                        unset($data['actual_cost_id']);
                        return $this->run(fn () => app(BudgetingService::class)->allocatePayment(auth()->user(), $record, $cost, $data));
                    }),
                Tables\Actions\Action::make('void')->icon('heroicon-o-no-symbol')->tooltip('Void unallocated payment')->color('danger')
                    ->visible(fn (Payment $record): bool => $record->status === 'completed' && ! $record->allocations()->exists())
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(fn (Payment $record, array $data) => $this->run(fn () => app(BudgetingService::class)->voidPayment(auth()->user(), $record, $data['reason']))),
            ]);
    }

    private function run(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Cannot update payment')
                ->body(collect($exception->errors())->flatten()->first())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title('Unauthorized')
                ->body('You do not have permission to perform this payment action.')->send();
        }

        return null;
    }
}
