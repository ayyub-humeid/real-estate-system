<?php

namespace App\Filament\Resources\PropertyAcquisitionResource\RelationManagers;

use App\Enums\{AcquisitionStatus, DueDiligenceStatus};
use App\Models\DueDiligenceCase;
use App\Models\DueDiligenceItem;
use App\Models\Property;
use App\Services\PropertyAcquisitionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class DueDiligenceCasesRelationManager extends RelationManager
{
    protected static string $relationship = 'dueDiligenceCases';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Due Diligence Cases');
    }

    /**
     * Due-diligence is managed from the Acquisition's View page as well as Edit.
     * Individual actions remain protected by their model policies.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema($this->caseFormSchema(saveItemsRelationship: true));
    }

    /** @return array<int, Forms\Components\Component> */
    private function caseFormSchema(bool $saveItemsRelationship): array
    {
        $items = Forms\Components\Repeater::make('items')
            ->label(__('Checklist Items'))
            ->schema($this->checklistItemSchema())
            ->defaultItems(0)
            ->addActionLabel(__('Add checklist item'))
            ->deleteAction(fn (Forms\Components\Actions\Action $action) => $action->visible(
                fn (array $arguments, Forms\Components\Repeater $component): bool => ! in_array(
                    $component->getState()[$arguments['item']]['status'] ?? null,
                    [DueDiligenceStatus::Passed->value, DueDiligenceStatus::Waived->value],
                    true,
                ),
            ))
            ->columnSpanFull();

        // EditAction saves an existing Case's items through Eloquent. Create Case is a
        // custom action, so its repeater must remain dehydrated for the service to receive it.
        if ($saveItemsRelationship) {
            $items->relationship()
                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                    $data['company_id'] = $this->getOwnerRecord()->company_id;
                    return $data;
                });
        }

        return [
            Forms\Components\Select::make('property_id')
                ->label(__('Property'))
                ->options(fn (): array => Property::withoutGlobalScopes()
                    ->whereIn('id', $this->getOwnerRecord()->acquisitionProperties()->select('property_id'))
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->rule(function () {
                    return function (string $attribute, mixed $value, \Closure $fail): void {
                        if ($value && ! Property::withoutGlobalScopes()
                            ->whereKey($value)
                            ->whereIn('id', $this->getOwnerRecord()->acquisitionProperties()->select('property_id'))
                            ->exists()) {
                            $fail(__('The selected property must be attached to this acquisition.'));
                        }
                    };
                }),
            Forms\Components\Textarea::make('summary')->label(__('Summary')),
            $items,
        ];
    }

    private function checklistItemSchema(): array
    {
        return [
            Forms\Components\TextInput::make('title')->label(__('Title'))->required(),
            Forms\Components\TextInput::make('category')->label(__('Category')),
            Forms\Components\Textarea::make('description')->label(__('Description')),
            Forms\Components\Toggle::make('is_required')->label(__('Required'))->default(true),
            Forms\Components\Select::make('status')
                ->label(__('Status'))
                ->options(DueDiligenceStatus::itemOptions())
                ->default(DueDiligenceStatus::Pending->value),
            Forms\Components\TextInput::make('sort_order')
                ->label(__('Sort Order'))
                ->numeric()
                ->default(0),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getOwnerRecord()->company_id;
        $data['opened_at'] = now();
        $data['opened_by'] = auth()->id();

        return $data;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (?string $state): string|array|null => DueDiligenceStatus::tryFrom((string) $state)?->getColor() ?? 'gray')
                    ->formatStateUsing(fn (?string $state): string => DueDiligenceStatus::labelFor($state)),
                Tables\Columns\TextColumn::make('property.name')->label(__('Property')),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label(__('Items Count')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createCase')
                    ->label(__('Create Case'))
                    ->visible(fn (): bool => auth()->user()->can('create', \App\Models\DueDiligenceCase::class)
                        && ! $this->acquisitionIsClosed())
                    ->form($this->caseFormSchema(saveItemsRelationship: false))
                    ->action(function (array $data) {
                        try {
                            app(PropertyAcquisitionService::class)->createDueDiligenceCase(
                                auth()->user(),
                                $this->getOwnerRecord(),
                                $data,
                            );
                            Notification::make()->success()->title(__('Due Diligence Case Created'))->send();
                        } catch (ValidationException $exception) {
                            $this->sendValidationNotification(__('Cannot Create Case'), $exception);
                        } catch (AuthorizationException) {
                            $this->sendUnauthorizedNotification(__('create due-diligence cases'));
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('addItem')
                    ->label(__('Add checklist item'))
                    ->visible(fn (): bool => auth()->user()->can('create', DueDiligenceItem::class)
                        && ! $this->acquisitionIsClosed())
                    ->form($this->checklistItemSchema())
                    ->action(function (DueDiligenceCase $record, array $data) {
                        try {
                            app(PropertyAcquisitionService::class)->createDueDiligenceItem(auth()->user(), $record, $data);
                            Notification::make()->success()->title(__('Checklist Item Added'))->send();
                        } catch (ValidationException $exception) {
                            $this->sendValidationNotification(__('Cannot Add Checklist Item'), $exception);
                        } catch (AuthorizationException) {
                            $this->sendUnauthorizedNotification(__('add checklist items'));
                        }
                    }),
                Tables\Actions\Action::make('waiveItem')
                    ->label(__('Waive item'))
                    ->form([
                        Forms\Components\Select::make('item_id')
                            ->label(__('Checklist item'))
                            ->options(fn ($record) => $record->items()
                                ->whereNotIn('status', [DueDiligenceStatus::Passed->value, DueDiligenceStatus::Waived->value])
                                ->pluck('title', 'id'))
                            ->required(),
                        Forms\Components\Textarea::make('notes')->label(__('Notes')),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(PropertyAcquisitionService::class)->waiveItem(
                                auth()->user(),
                                $record->items()->findOrFail($data['item_id']),
                                $data['notes'] ?? null,
                            );
                            Notification::make()->success()->title(__('Checklist Item Waived'))->send();
                        } catch (ValidationException $exception) {
                            $this->sendValidationNotification(__('Cannot Waive Checklist Item'), $exception);
                        } catch (AuthorizationException) {
                            $this->sendUnauthorizedNotification(__('waive checklist items'));
                        }
                    }),
                Tables\Actions\Action::make('clear')
                    ->label(__('Clear Case'))
                    ->authorize('clear')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            app(PropertyAcquisitionService::class)->clearCase(auth()->user(), $record);
                            Notification::make()
                                ->success()
                                ->title(__('Case Cleared'))
                                ->send();
                        } catch (ValidationException $exception) {
                            $this->sendValidationNotification(__('Cannot Clear Case'), $exception);
                        } catch (AuthorizationException) {
                            $this->sendUnauthorizedNotification(__('clear due-diligence cases'));
                        }
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    private function sendValidationNotification(string $title, ValidationException $exception): void
    {
        Notification::make()->danger()->title($title)
            ->body(collect($exception->errors())->flatten()->first())->send();
    }

    private function sendUnauthorizedNotification(string $action): void
    {
        Notification::make()->danger()->title(__('Unauthorized'))
            ->body(__('acquisition.errors.no_permission_action', ['action' => $action]))->send();
    }

    private function acquisitionIsClosed(): bool
    {
        return (bool) AcquisitionStatus::tryFrom((string) $this->getOwnerRecord()->status)?->isClosed();
    }
}

