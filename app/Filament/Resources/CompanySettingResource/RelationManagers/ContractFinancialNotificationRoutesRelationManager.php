<?php

namespace App\Filament\Resources\CompanySettingResource\RelationManagers;

use App\Models\{ContractFinancialNotificationRoute, User};
use App\Services\ContractFinancialNotificationDispatcher;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Unique;

class ContractFinancialNotificationRoutesRelationManager extends RelationManager
{
    protected static string $relationship = 'contractFinancialNotificationRoutes';

    /** @var array<string, Collection<int, User>> */
    private array $eligibleUsersByEvent = [];

    /** @var array<string, Collection<int, ContractFinancialNotificationRoute>> */
    private array $configuredRecipientsByEvent = [];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('manage_contract_financial_notification_routes') ?? false;
    }

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('contract_finance.settings.notification_routing');
    }

    public static function getModelLabel(): string
    {
        return __('contract_finance.settings.notification_route');
    }

    public static function getPluralModelLabel(): string
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
            ])
                ->helperText(__('contract_finance.settings.event_help'))
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('user_id', null)),
            Forms\Components\Select::make('user_id')
                ->label(__('contract_finance.fields.recipient'))
                ->options(function (Forms\Get $get): array {
                    $eventType = $get('event_type');
                    if (! $eventType) {
                        return [];
                    }

                    return ContractFinancialNotificationDispatcher::eligibleUsersQuery(
                        $this->getOwnerRecord()->company_id,
                        (string) $eventType,
                    )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all();
                })
                ->helperText(__('contract_finance.settings.recipient_help'))
                ->disabled(fn (Forms\Get $get): bool => blank($get('event_type')))
                ->searchable()
                ->required()
                ->unique(
                    table: 'contract_financial_notification_routes',
                    column: 'user_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Forms\Get $get) => $rule
                        ->where('company_id', $this->getOwnerRecord()->company_id)
                        ->where('event_type', $get('event_type')),
                )
                ->validationMessages([
                    'unique' => __('contract_finance.settings.recipient_already_configured'),
                ]),
            Forms\Components\Toggle::make('is_fallback')
                ->label(__('contract_finance.settings.fallback_recipient'))
                ->helperText(__('contract_finance.settings.fallback_help'))
                ->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // The table is intentionally grouped by event. One notification
            // event can have several recipients, but it must never look like
            // several duplicate events to the administrator.
            ->modifyQueryUsing(function ($query): void {
                $companyId = $this->getOwnerRecord()->company_id;

                $query->whereIn('id', ContractFinancialNotificationRoute::withoutGlobalScopes()
                    ->selectRaw('MIN(id)')
                    ->where('company_id', $companyId)
                    ->groupBy('event_type'));
            })
            ->heading(__('contract_finance.settings.notification_routing'))
            ->description(__('contract_finance.settings.routing_description'))
            ->emptyStateHeading(__('contract_finance.settings.empty_routes_heading'))
            ->emptyStateDescription(__('contract_finance.settings.empty_routes_description'))
            ->columns([
                Tables\Columns\TextColumn::make('event_type')->label(__('contract_finance.fields.event'))->formatStateUsing(fn (string $state): string => match ($state) {
                    'installment_upcoming' => __('contract_finance.notifications.upcoming'), 'installment_due' => __('contract_finance.notifications.due'),
                    'installment_overdue' => __('contract_finance.notifications.overdue'), 'contract_settled' => __('contract_finance.notifications.settled'),
                    default => __('contract_finance.notifications.'.$state),
                }),
                Tables\Columns\TextColumn::make('primary_recipients_count')
                    ->label(__('contract_finance.settings.primary_recipients'))
                    ->state(fn (ContractFinancialNotificationRoute $record): int => $this->configuredRecipientsFor($record->event_type)->where('is_fallback', false)->count())
                    ->badge()
                    ->color('info')
                    ->disabledClick()
                    ->tooltip(__('contract_finance.settings.primary_recipients_help')),
                Tables\Columns\TextColumn::make('fallback_recipients_count')
                    ->label(__('contract_finance.settings.fallback_recipients'))
                    ->state(fn (ContractFinancialNotificationRoute $record): int => $this->configuredRecipientsFor($record->event_type)->where('is_fallback', true)->count())
                    ->badge()
                    ->color('warning')
                    ->disabledClick()
                    ->tooltip(__('contract_finance.settings.fallback_recipients_help')),
                Tables\Columns\TextColumn::make('eligible_users_count')
                    ->label(__('contract_finance.settings.eligible_users_count'))
                    ->state(fn (ContractFinancialNotificationRoute $record): int => $this->eligibleUsersFor($record->event_type)->count())
                    ->badge()
                    ->color('gray')
                    ->disabledClick()
                    ->tooltip(__('contract_finance.settings.eligible_users_count_help')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('contract_finance.actions.add_notification_route'))
                    ->tooltip(__('contract_finance.settings.add_route_help'))
                    ->modalHeading(__('contract_finance.actions.add_notification_route'))
                    ->modalDescription(__('contract_finance.settings.routing_description'))
                    ->modalSubmitActionLabel(__('contract_finance.actions.save_notification_route'))
                    ->successNotification(fn (Notification $notification): Notification => $notification
                        ->title(__('contract_finance.settings.recipient_added_title'))
                        ->body(__('contract_finance.settings.recipient_added_body')))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;
                        $this->assertRecipientIsNotAlreadyConfigured($data);
                        return $data;
                    })
                    ->using(function (array $data): ContractFinancialNotificationRoute {
                        try {
                            return DB::transaction(function () use ($data): ContractFinancialNotificationRoute {
                                // Repeat the check inside the transaction. The database
                                // unique index remains the final concurrency safeguard.
                                $this->assertRecipientIsNotAlreadyConfigured($data);

                                return ContractFinancialNotificationRoute::withoutGlobalScopes()->create($data);
                            });
                        } catch (UniqueConstraintViolationException) {
                            $this->duplicateRecipientValidationError();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('viewRecipients')
                    ->label(__('contract_finance.actions.view_notification_recipients'))
                    ->icon('heroicon-o-users')
                    ->tooltip(__('contract_finance.settings.view_notification_recipients_help'))
                    ->modalHeading(__('contract_finance.settings.notification_recipients_heading'))
                    ->modalDescription(fn (ContractFinancialNotificationRoute $record): string => __('contract_finance.settings.notification_recipients_description', [
                        'event' => $this->eventLabel($record->event_type),
                    ]))
                    ->modalSubmitAction(false)
                    ->form(fn (ContractFinancialNotificationRoute $record): array => [
                        Forms\Components\Placeholder::make('notification_recipients')
                            ->label(__('contract_finance.settings.configured_recipients'))
                            ->content(function () use ($record): HtmlString {
                                $routes = $this->configuredRecipientsFor($record->event_type);
                                $primary = $routes->where('is_fallback', false);
                                $fallback = $routes->where('is_fallback', true);
                                $eligibleUserIds = $this->eligibleUsersFor($record->event_type)->pluck('id');
                                $hasEligiblePrimary = $primary->pluck('user_id')->intersect($eligibleUserIds)->isNotEmpty();

                                if ($routes->isEmpty()) {
                                    return new HtmlString(e(__('contract_finance.settings.no_configured_recipients')));
                                }

                                return new HtmlString(
                                    '<div class="space-y-4">'
                                    .'<section><div class="font-semibold">'.e(__('contract_finance.settings.primary_recipients')).'</div>'
                                    .'<div class="mt-2 space-y-2">'.($primary->isEmpty()
                                        ? e(__('contract_finance.settings.no_primary_recipients'))
                                        : $primary->map(fn (ContractFinancialNotificationRoute $route): string => '<div class="block">↳ '.e($route->user?->name ?? __('—')).'</div>')->implode('')).'</div></section>'
                                    .'<section><div class="font-semibold">'.e(__('contract_finance.settings.fallback_recipients')).'</div>'
                                    .'<div class="mt-2 space-y-2">'.($fallback->isEmpty()
                                        ? e(__('contract_finance.settings.no_fallback_recipients'))
                                        : $fallback->map(fn (ContractFinancialNotificationRoute $route): string => '<div class="block">↳ '.e($route->user?->name ?? __('—')).'</div>')->implode('')).'</div></section>'
                                    .(! $fallback->isEmpty()
                                        ? '<div class="rounded-lg border border-warning-500/30 bg-warning-500/10 px-3 py-2 text-sm">'.e($hasEligiblePrimary
                                            ? __('contract_finance.settings.fallback_waiting_for_no_primary')
                                            : __('contract_finance.settings.fallback_used_without_primary')).'</div>'
                                        : '')
                                    .'</div>',
                                );
                            }),
                    ]),
                Tables\Actions\Action::make('viewEligibleUsers')
                    ->label(__('contract_finance.actions.view_eligible_users'))
                    ->icon('heroicon-o-shield-check')
                    ->tooltip(__('contract_finance.settings.view_eligible_users_help'))
                    ->modalHeading(__('contract_finance.settings.eligible_users_heading'))
                    ->modalDescription(fn (ContractFinancialNotificationRoute $record): string => __('contract_finance.settings.eligible_users_description', [
                        'event' => $this->eventLabel($record->event_type),
                    ]))
                    ->modalSubmitAction(false)
                    ->form(fn (ContractFinancialNotificationRoute $record): array => [
                        Forms\Components\Placeholder::make('eligible_users')
                            ->label(__('contract_finance.settings.eligible_users_count'))
                            ->content(function () use ($record): HtmlString {
                                $users = $this->eligibleUsersFor($record->event_type);

                                if ($users->isEmpty()) {
                                    return new HtmlString(e(__('contract_finance.settings.no_eligible_users')));
                                }

                                return new HtmlString(
                                    '<div class="space-y-2">'
                                    .$users->map(fn (User $user): string => '<div class="block">↳ '.e($user->name).'</div>')->implode('')
                                    .'</div>',
                                );
                            }),
                    ]),
                Tables\Actions\Action::make('manageRecipients')
                    ->label(__('contract_finance.actions.manage_notification_recipients'))
                    ->icon('heroicon-o-user-group')
                    ->tooltip(__('contract_finance.settings.manage_recipients_help'))
                    ->modalHeading(__('contract_finance.actions.manage_notification_recipients'))
                    ->modalDescription(fn (ContractFinancialNotificationRoute $record): string => __('contract_finance.settings.manage_recipients_description', [
                        'event' => $this->eventLabel($record->event_type),
                    ]))
                    ->fillForm(fn (ContractFinancialNotificationRoute $record): array => [
                        'primary_recipient_ids' => $this->configuredRecipientsFor($record->event_type)
                            ->where('is_fallback', false)
                            ->pluck('user_id')
                            // HTML checkbox values are strings. Keeping the
                            // hydrated state in that form lets Filament mark
                            // the already-configured users as checked.
                            ->map(fn (int $id): string => (string) $id)
                            ->all(),
                        'fallback_recipient_ids' => $this->configuredRecipientsFor($record->event_type)
                            ->where('is_fallback', true)
                            ->pluck('user_id')
                            ->map(fn (int $id): string => (string) $id)
                            ->all(),
                    ])
                    ->form([
                        Forms\Components\CheckboxList::make('primary_recipient_ids')
                            ->label(__('contract_finance.settings.primary_recipients'))
                            ->options(fn (ContractFinancialNotificationRoute $record): array => ContractFinancialNotificationDispatcher::eligibleUsersQuery(
                                $this->getOwnerRecord()->company_id,
                                $record->event_type,
                            )->orderBy('name')->pluck('name', 'id')->all())
                            ->helperText(__('contract_finance.settings.primary_recipients_help'))
                            ->live()
                            ->disableOptionWhen(fn (string $value, Forms\Get $get): bool => in_array($value, array_map('strval', $get('fallback_recipient_ids') ?? []), true))
                            ->columns(1)
                            ->bulkToggleable(),
                        Forms\Components\CheckboxList::make('fallback_recipient_ids')
                            ->label(__('contract_finance.settings.fallback_recipients'))
                            ->options(fn (ContractFinancialNotificationRoute $record): array => ContractFinancialNotificationDispatcher::eligibleUsersQuery(
                                $this->getOwnerRecord()->company_id,
                                $record->event_type,
                            )->orderBy('name')->pluck('name', 'id')->all())
                            ->helperText(__('contract_finance.settings.fallback_recipients_help'))
                            ->live()
                            ->disableOptionWhen(fn (string $value, Forms\Get $get): bool => in_array($value, array_map('strval', $get('primary_recipient_ids') ?? []), true))
                            ->columns(1)
                            ->bulkToggleable(),
                    ])
                    ->action(function (ContractFinancialNotificationRoute $record, array $data): void {
                        $primaryIds = collect($data['primary_recipient_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
                        $fallbackIds = collect($data['fallback_recipient_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
                        $eligibleIds = ContractFinancialNotificationDispatcher::eligibleUsersQuery(
                            $this->getOwnerRecord()->company_id,
                            $record->event_type,
                        )->pluck('id')->map(fn ($id): int => (int) $id);

                        if ($primaryIds->intersect($fallbackIds)->isNotEmpty()) {
                            $this->recipientValidationError(
                                'fallback_recipient_ids',
                                __('contract_finance.settings.recipient_cannot_be_primary_and_fallback'),
                            );
                        }

                        if ($primaryIds->merge($fallbackIds)->diff($eligibleIds)->isNotEmpty()) {
                            $this->recipientValidationError('primary_recipient_ids', __('contract_finance.settings.invalid_recipient'));
                        }

                        DB::transaction(function () use ($record, $primaryIds, $fallbackIds): void {
                            $routes = ContractFinancialNotificationRoute::withoutGlobalScopes()
                                ->where('company_id', $this->getOwnerRecord()->company_id)
                                ->where('event_type', $record->event_type)
                                ->lockForUpdate()
                                ->get();

                            $desired = $primaryIds->mapWithKeys(fn (int $userId): array => [$userId => false])
                                ->union($fallbackIds->mapWithKeys(fn (int $userId): array => [$userId => true]));

                            $routes->each(function (ContractFinancialNotificationRoute $route) use ($desired): void {
                                if (! $desired->has($route->user_id)) {
                                    $route->delete();
                                    return;
                                }

                                if ($route->is_fallback !== $desired[$route->user_id]) {
                                    $route->update(['is_fallback' => $desired[$route->user_id]]);
                                }
                            });

                            $existingIds = $routes->pluck('user_id');
                            $desired->except($existingIds)->each(fn (bool $isFallback, int $userId) => ContractFinancialNotificationRoute::withoutGlobalScopes()->create([
                                'company_id' => $this->getOwnerRecord()->company_id,
                                'event_type' => $record->event_type,
                                'user_id' => $userId,
                                'is_fallback' => $isFallback,
                            ]));
                        });

                        unset($this->configuredRecipientsByEvent[$record->event_type]);

                        Notification::make()
                            ->success()
                            ->title(__('contract_finance.settings.recipients_saved_title'))
                            ->body(__('contract_finance.settings.recipients_saved_body'))
                            ->send();
                    }),
                Tables\Actions\Action::make('deleteNotificationEvent')
                    ->label(__('contract_finance.actions.delete_notification_event'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->tooltip(__('contract_finance.settings.delete_event_help'))
                    ->requiresConfirmation()
                    ->modalHeading(__('contract_finance.actions.delete_notification_event'))
                    ->modalDescription(fn (ContractFinancialNotificationRoute $record): string => __('contract_finance.settings.delete_event_description', [
                        'event' => $this->eventLabel($record->event_type),
                    ]))
                    ->modalSubmitActionLabel(__('contract_finance.actions.delete_notification_event'))
                    ->action(function (ContractFinancialNotificationRoute $record): void {
                        DB::transaction(function () use ($record): void {
                            ContractFinancialNotificationRoute::withoutGlobalScopes()
                                ->where('company_id', $this->getOwnerRecord()->company_id)
                                ->where('event_type', $record->event_type)
                                ->lockForUpdate()
                                ->get()
                                ->each->delete();
                        });

                        unset($this->configuredRecipientsByEvent[$record->event_type]);

                        Notification::make()
                            ->success()
                            ->title(__('contract_finance.settings.event_routing_deleted_title'))
                            ->body(__('contract_finance.settings.event_routing_deleted_body'))
                            ->send();
                    }),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getOwnerRecord()->company_id;
        return $data;
    }

    /** @param array{company_id: int, event_type: string, user_id: int} $data */
    private function assertRecipientIsNotAlreadyConfigured(array $data): void
    {
        $exists = ContractFinancialNotificationRoute::withoutGlobalScopes()
            ->where('company_id', $data['company_id'])
            ->where('event_type', $data['event_type'])
            ->where('user_id', $data['user_id'])
            ->exists();

        if ($exists) {
            $this->duplicateRecipientValidationError();
        }
    }

    private function duplicateRecipientValidationError(): never
    {
        $this->recipientValidationError('user_id', __('contract_finance.settings.recipient_already_configured'));
    }

    private function recipientValidationError(string $field, string $message): never
    {
        Notification::make()
            ->danger()
            ->title(__('contract_finance.settings.cannot_save_recipients'))
            ->body($message)
            ->persistent()
            ->send();

        throw ValidationException::withMessages([$field => $message]);
    }

    /** @return Collection<int, User> */
    private function eligibleUsersFor(string $eventType): Collection
    {
        return $this->eligibleUsersByEvent[$eventType] ??= ContractFinancialNotificationDispatcher::eligibleUsersQuery(
            $this->getOwnerRecord()->company_id,
            $eventType,
        )->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, ContractFinancialNotificationRoute> */
    private function configuredRecipientsFor(string $eventType): Collection
    {
        return $this->configuredRecipientsByEvent[$eventType] ??= ContractFinancialNotificationRoute::withoutGlobalScopes()
            ->where('company_id', $this->getOwnerRecord()->company_id)
            ->where('event_type', $eventType)
            ->with('user:id,name')
            ->orderBy('id')
            ->get();
    }

    private function eventLabel(string $eventType): string
    {
        return match ($eventType) {
            'installment_upcoming' => __('contract_finance.notifications.upcoming'),
            'installment_due' => __('contract_finance.notifications.due'),
            'installment_overdue' => __('contract_finance.notifications.overdue'),
            'contract_settled' => __('contract_finance.notifications.settled'),
            default => __('contract_finance.notifications.'.$eventType),
        };
    }
}
