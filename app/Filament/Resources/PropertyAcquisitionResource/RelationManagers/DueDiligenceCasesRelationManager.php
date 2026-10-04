<?php

namespace App\Filament\Resources\PropertyAcquisitionResource\RelationManagers;

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
    protected static ?string $title = 'Due Diligence Cases';

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
            ->schema($this->checklistItemSchema())
            ->defaultItems(0)
            ->addActionLabel('Add checklist item')
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
                ->label('Property')
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
                            $fail('The selected property must be attached to this acquisition.');
                        }
                    };
                }),
            Forms\Components\Textarea::make('summary'),
            $items,
        ];
    }

    private function checklistItemSchema(): array
    {
        return [
            Forms\Components\TextInput::make('title')->required(),
            Forms\Components\TextInput::make('category'),
            Forms\Components\Textarea::make('description'),
            Forms\Components\Toggle::make('is_required')->default(true),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'passed'  => 'Passed',
                    'failed'  => 'Failed',
                ])
                ->default('pending'),
            Forms\Components\TextInput::make('sort_order')
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
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('property.name'),
                Tables\Columns\TextColumn::make('items_count')->counts('items'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createCase')
                    ->label('Create Case')
                    ->visible(fn (): bool => auth()->user()->can('create', \App\Models\DueDiligenceCase::class)
                        && ! in_array($this->getOwnerRecord()->status, ['completed', 'cancelled'], true))
                    ->form($this->caseFormSchema(saveItemsRelationship: false))
                    ->action(function (array $data) {
                        try {
                            app(PropertyAcquisitionService::class)->createDueDiligenceCase(
                                auth()->user(),
                                $this->getOwnerRecord(),
                                $data,
                            );
                            Notification::make()->success()->title('Due Diligence Case Created')->send();
                        } catch (ValidationException $exception) {
                            $this->sendValidationNotification('Cannot Create Case', $exception);
                        } catch (AuthorizationException) {
                            $this->sendUnauthorizedNotification('create due-diligence cases');
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('addItem')
                    ->label('Add checklist item')
                    ->visible(fn (): bool => auth()->user()->can('create', DueDiligenceItem::class)
                        && ! in_array($this->getOwnerRecord()->status, ['completed', 'cancelled'], true))
                    ->form($this->checklistItemSchema())
                    ->action(function (DueDiligenceCase $record, array $data) {
                        try {
                            app(PropertyAcquisitionService::class)->createDueDiligenceItem(auth()->user(), $record, $data);
                            Notification::make()->success()->title('Checklist Item Added')->send();
                        } catch (ValidationException $exception) {
                            $this->sendValidationNotification('Cannot Add Checklist Item', $exception);
                        } catch (AuthorizationException) {
                            $this->sendUnauthorizedNotification('add checklist items');
                        }
                    }),
                Tables\Actions\Action::make('waiveItem')
                    ->label('Waive item')
                    ->form([
                        Forms\Components\Select::make('item_id')
                            ->label('Checklist item')
                            ->options(fn ($record) => $record->items()
                                ->whereNotIn('status', ['passed', 'waived'])
                                ->pluck('title', 'id'))
                            ->required(),
                        Forms\Components\Textarea::make('notes'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(PropertyAcquisitionService::class)->waiveItem(
                                auth()->user(),
                                $record->items()->findOrFail($data['item_id']),
                                $data['notes'] ?? null,
                            );
                            Notification::make()->success()->title('Checklist Item Waived')->send();
                        } catch (ValidationException $exception) {
                            $this->sendValidationNotification('Cannot Waive Checklist Item', $exception);
                        } catch (AuthorizationException) {
                            $this->sendUnauthorizedNotification('waive checklist items');
                        }
                    }),
                Tables\Actions\Action::make('clear')
                    ->authorize('clear')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            app(PropertyAcquisitionService::class)->clearCase(auth()->user(), $record);
                            Notification::make()
                                ->success()
                                ->title('Case Cleared')
                                ->send();
                        } catch (ValidationException $exception) {
                            $this->sendValidationNotification('Cannot Clear Case', $exception);
                        } catch (AuthorizationException) {
                            $this->sendUnauthorizedNotification('clear due-diligence cases');
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
        Notification::make()->danger()->title('Unauthorized')
            ->body("You do not have permission to {$action}.")->send();
    }
}

