<?php

namespace App\Filament\Resources\PropertyAcquisitionResource\RelationManagers;

use App\Models\Property;
use App\Services\PropertyAcquisitionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DueDiligenceCasesRelationManager extends RelationManager
{
    protected static string $relationship = 'dueDiligenceCases';
    protected static ?string $title = 'Due Diligence Cases';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('property_id')
                ->label('Property')
                ->options(fn (): array => Property::withoutGlobalScopes()
                    ->where('company_id', $this->getOwnerRecord()->company_id)
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->rule(function () {
                    return function (string $attribute, mixed $value, \Closure $fail): void {
                        if ($value && ! Property::withoutGlobalScopes()
                            ->whereKey($value)
                            ->where('company_id', $this->getOwnerRecord()->company_id)
                            ->exists()) {
                            $fail('The selected property must belong to this acquisition company.');
                        }
                    };
                }),
            Forms\Components\Textarea::make('summary'),
            Forms\Components\Repeater::make('items')
                ->relationship()
                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                    $data['company_id'] = $this->getOwnerRecord()->company_id;
                    return $data;
                })
                ->schema([
                    Forms\Components\TextInput::make('title')->required(),
                    Forms\Components\TextInput::make('category'),
                    Forms\Components\Textarea::make('description'),
                    Forms\Components\Toggle::make('is_required')->default(true),
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'passed'  => 'Passed',
                            'failed'  => 'Failed',
                            'waived'  => 'Waived',
                        ])
                        ->default('pending')
                        ->disableOptionWhen(fn (string $value): bool => $value === 'waived'),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                ])
                ->defaultItems(0)
                ->addActionLabel('Add checklist item')
                ->columnSpanFull(),
        ]);
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
                    ->visible(fn (): bool => auth()->user()->can('create', \App\Models\DueDiligenceCase::class))
                    ->form(fn (Form $form) => $this->form($form)->getComponents())
                    ->action(function (array $data) {
                        app(PropertyAcquisitionService::class)->createDueDiligenceCase(
                            auth()->user(),
                            $this->getOwnerRecord(),
                            $data,
                        );
                    }),
            ])
            ->actions([
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
                        app(PropertyAcquisitionService::class)->waiveItem(
                            auth()->user(),
                            $record->items()->findOrFail($data['item_id']),
                            $data['notes'] ?? null,
                        );
                    }),
                Tables\Actions\Action::make('clear')
                    ->authorize('clear')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            app(PropertyAcquisitionService::class)->clearCase(auth()->user(), $record);
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Case Cleared')
                                ->send();
                        } catch (\Illuminate\Validation\ValidationException $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Cannot Clear Case')
                                ->body(collect($e->errors())->flatten()->first())
                                ->send();
                        }
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }
}

