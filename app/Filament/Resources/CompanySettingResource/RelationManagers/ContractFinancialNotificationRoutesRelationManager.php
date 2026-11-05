<?php

namespace App\Filament\Resources\CompanySettingResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContractFinancialNotificationRoutesRelationManager extends RelationManager
{
    protected static string $relationship = 'contractFinancialNotificationRoutes';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('manage_contract_financial_notification_routes') ?? false;
    }

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('contract_finance.settings.notification_routing');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('event_type')->label(__('contract_finance.fields.event'))->options([
                'schedule_submitted' => __('contract_finance.notifications.schedule_submitted'),
                'schedule_issued' => __('contract_finance.notifications.schedule_issued'),
                'schedule_rescheduled' => __('contract_finance.notifications.schedule_rescheduled'),
                'payment_recorded' => __('contract_finance.notifications.payment_recorded'),
                'payment_reversed' => __('contract_finance.notifications.payment_reversed'),
                'installment_upcoming' => __('contract_finance.notifications.upcoming'),
                'installment_due' => __('contract_finance.notifications.due'),
                'installment_overdue' => __('contract_finance.notifications.overdue'),
                'contract_settled' => __('contract_finance.notifications.settled'),
            ])->required()->native(false),
            Forms\Components\Select::make('user_id')->label(__('contract_finance.fields.recipient'))->options(fn (): array => User::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->orderBy('name')->pluck('name', 'id')->all())->searchable()->required(),
            Forms\Components\Toggle::make('is_fallback')->label(__('contract_finance.settings.primary_only'))->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_type')->label(__('contract_finance.fields.event'))->formatStateUsing(fn (string $state): string => match ($state) {
                    'installment_upcoming' => __('contract_finance.notifications.upcoming'), 'installment_due' => __('contract_finance.notifications.due'),
                    'installment_overdue' => __('contract_finance.notifications.overdue'), 'contract_settled' => __('contract_finance.notifications.settled'),
                    default => __('contract_finance.notifications.'.$state),
                }),
                Tables\Columns\TextColumn::make('user.name')->label(__('contract_finance.fields.recipient')),
                Tables\Columns\IconColumn::make('is_fallback')->label(__('contract_finance.fields.fallback'))->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;
                        return $data;
                    }),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getOwnerRecord()->company_id;
        return $data;
    }
}
