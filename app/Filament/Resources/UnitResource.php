<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Filament\Resources\UnitResource\RelationManagers;
use App\Models\Unit;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Project;
use App\Models\ProjectPlannedUnit;
use App\Models\Party;
use App\Services\PropertyDescriptionService;
use App\Services\UnitSetupService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class UnitResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = Unit::class;
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationGroup = '🏠 Properties';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'property:id,name',
                'primaryImage:id,imageable_type,imageable_id,path',
                'features',
                'maintenanceRequests',
            ]);
    }

    /**
     * Reusable company field logic
     */
    private static function companyField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('company_id')
            ->label(__('Company'))
            ->relationship('company', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->visible(fn() => auth()->user()->isSuperAdmin());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make(__('Unit Details'))
                ->schema([
                    self::companyField(),

                    Forms\Components\Select::make('property_id')
                        ->label(__('Property'))
                        ->relationship('property', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false)
                        ->prefixIcon('heroicon-m-building-office-2'),

                    Forms\Components\TextInput::make('unit_number')
                        ->label(__('Unit Number'))
                        ->required()
                        ->maxLength(50)
                        ->prefixIcon('heroicon-m-hashtag'),

                    Forms\Components\Select::make('type')
                        ->label(__('Type'))
                        ->options(UnitType::options())
                        ->native(false)
                        ->prefixIcon('heroicon-m-tag'),

                    Forms\Components\Select::make('project_id')
                        ->label(__('Project'))
                        ->options(fn () => Project::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->live()->native(false),

                    Forms\Components\Select::make('planned_unit_id')
                        ->label(__('Planned Unit source'))
                        ->options(fn (Forms\Get $get) => $get('project_id') ? ProjectPlannedUnit::query()->whereHas('floor.building', fn ($q) => $q->where('project_id', $get('project_id')))->where('status', 'approved')->orderBy('code')->pluck('code', 'id') : [])
                        ->searchable()->native(false),

                    Forms\Components\Select::make('status')
                        ->label(__('Physical status'))
                        ->options(UnitStatus::options())
                        ->default('draft')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText(__('Use the controlled Change status action after setup is saved.')),

                    Forms\Components\TextInput::make('bedrooms')
                        ->label(__('Bedrooms'))
                        ->numeric()
                        ->minValue(0)
                        ->prefixIcon('heroicon-m-home'),

                    Forms\Components\TextInput::make('bathrooms')
                        ->label(__('Bathrooms'))
                        ->numeric()
                        ->minValue(0)
                        ->prefixIcon('heroicon-m-home'),

                    Forms\Components\TextInput::make('sqft')
                        ->label(__('Square Feet'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix('sqft'),

                    Forms\Components\TextInput::make('actual_area')->label(__('Actual Area'))->numeric()->minValue(0)->suffix(fn (Forms\Get $get) => $get('area_unit') ?: 'm2'),
                    Forms\Components\Select::make('area_unit')->label(__('Area Unit'))->options(['m2' => 'm²', 'sqft' => 'sq ft'])->native(false),
                    Forms\Components\TextInput::make('location_label')->label(__('Location'))->maxLength(255),
                    Forms\Components\Toggle::make('is_featured')
                        ->label(__('Is Featured?'))
                        ->default(false),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Description'))
                ->description(__('Write a description manually or use AI to generate one.'))
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->label(__('Unit Description'))
                        ->rows(5)
                        ->placeholder(__('Describe this unit for potential tenants...'))
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            Forms\Components\Section::make(__('Gallery'))
                ->description(__('Add images for this unit'))
                ->schema([
                    Forms\Components\Repeater::make('images')
                        ->label(__('Gallery'))
                        ->relationship('images')
                        ->rules([
                            function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    $primaryCount = collect($value)
                                        ->filter(fn($item) => !empty($item['is_primary']))
                                        ->count();

                                    if ($primaryCount > 1) {
                                        $fail(__('Only one image can be set as primary.'));
                                    }
                                };
                            },
                        ])
                        ->schema([
                            Forms\Components\FileUpload::make('path')
                                ->label(__('Photo'))
                                ->image()
                                ->imageEditor()
                                ->directory('images/units')
                                ->maxSize(5120)
                                ->required(),
                            Forms\Components\Toggle::make('is_primary')
                                ->label(__('Primary'))
                                ->default(false),
                            Forms\Components\TextInput::make('order')
                                ->label(__('Order'))
                                ->numeric()
                                ->default(0),
                        ])
                        ->grid(2)
                        ->defaultItems(0)
                        ->collapsible()
                        ->itemLabel(fn(array $state): ?string => is_string($state['path'] ?? null) ? $state['path'] : null)
                        ->columnSpanFull(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('property.name')
                    ->label(__('Property'))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-building-office-2'),

                Tables\Columns\TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->visible(fn() => auth()->user()->isSuperAdmin()),

                Tables\Columns\TextColumn::make('unit_number')
                    ->label(__('Unit #'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label(__('Is Featured'))
                    ->sortable()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn(?string $state) => $state ? UnitType::tryFrom($state)?->getLabel() ?? $state : __('—'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('bedrooms')
                    ->label(__('Beds'))
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-home')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('bathrooms')
                    ->label(__('Baths'))
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-home')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sqft')
                    ->label(__('Sqft'))
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn(?int $state) => $state ? number_format($state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('rent_price')
                    ->label(__('Legacy rent'))
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'ready',
                        'warning' => 'maintenance',
                        'danger' => 'inactive',
                    ])
                    ->formatStateUsing(fn(string $state): string => UnitStatus::tryFrom($state)?->getLabel() ?? $state),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company')
                    ->label(__('Company'))
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn() => auth()->user()->isSuperAdmin()),

                Tables\Filters\SelectFilter::make('property')
                    ->label(__('Property'))
                    ->relationship('property', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(UnitStatus::options())
                    ->native(false),

                Tables\Filters\SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(UnitType::options())
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Action::make('changeStatus')->label(__('Change status'))->icon('heroicon-o-arrow-path')->tooltip(__('Change physical operational status'))
                    ->visible(fn (Unit $record) => ! empty(static::statusTransitionOptions($record)) && (auth()->user()->can('changeStatus', $record) || auth()->user()->can('reactivate', $record)))
                    ->form(fn (Unit $record) => [Forms\Components\Select::make('status')->label(__('Status'))->options(static::statusTransitionOptions($record))->required(), Forms\Components\Textarea::make('reason')->label(__('Reason'))->required(fn (Forms\Get $get) => in_array($get('status'), ['inactive', 'draft'], true))])
                    ->action(function (Unit $record, array $data): void { try { app(UnitSetupService::class)->transitionStatus(auth()->user(), $record, $data['status'], $data['reason'] ?? null); Notification::make()->success()->title(__('Unit status updated'))->send(); } catch (\Illuminate\Validation\ValidationException $e) { Notification::make()->danger()->title(__('Cannot change status'))->body(collect($e->errors())->flatten()->first())->send(); } }),

                // ── AI Description Generator ─────────────────────────────
                Action::make('generateAiDescription')
                    ->label(__('✨ Generate Description'))
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->hidden(fn(Unit $record) => !empty($record->description))
                    ->requiresConfirmation(false)
                    ->modalHeading(__('AI-Generated Description'))
                    ->modalDescription(__('Review the description below. Click "Approve & Save" to use it, or "Cancel" to keep the existing description.'))
                    ->modalWidth('2xl')
                    ->form(function (Unit $record): array {
                        /** @var PropertyDescriptionService $service */
                        $service = app(PropertyDescriptionService::class);

                        try {
                            $generated = $service->generate($record);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('AI Error'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            $generated = '';
                        }

                        return [
                            Forms\Components\Placeholder::make('ai_preview_label')
                                ->label('')
                                ->content(__('The AI generated the following description based on the unit details and amenities:')),

                            Forms\Components\Textarea::make('ai_description')
                                ->label(__('Generated Description'))
                                ->default($generated)
                                ->rows(7)
                                ->columnSpanFull()
                                ->readOnly(),
                        ];
                    })
                    ->modalSubmitActionLabel(__('✅ Approve & Save'))
                    ->action(function (Unit $record, array $data): void {
                        if (empty($data['ai_description'])) {
                            Notification::make()
                                ->title(__('Nothing to save'))
                                ->warning()
                                ->send();
                            return;
                        }

                        /** @var PropertyDescriptionService $service */
                        $service = app(PropertyDescriptionService::class);
                        $service->saveDescription($record, $data['ai_description']);

                        Notification::make()
                            ->title(__('Description saved!'))
                            ->body(__('The AI-generated description has been applied to this unit.'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('No units yet'))
            ->emptyStateDescription(__('Create your first unit or add one from a property.'))
            ->emptyStateIcon('heroicon-o-home');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FeaturesRelationManager::class,
            RelationManagers\OwnershipsRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\StatusHistoriesRelationManager::class,
            RelationManagers\MaintenanceRequestsRelationManager::class,
            RelationManagers\ImagesRelationManager::class,
            RelationManagers\ContractsRelationManager::class,
        ];
    }

    private static function statusTransitionOptions(Unit $unit): array
    {
        $current = UnitStatus::tryFrom((string) $unit->status);

        if (! $current) {
            return [];
        }

        return collect(UnitStatus::cases())
            ->filter(fn (UnitStatus $status): bool => $current->canTransitionTo($status))
            ->filter(fn (UnitStatus $status): bool => auth()->user()->can($status === UnitStatus::Draft ? 'reactivate' : 'changeStatus', $unit))
            ->mapWithKeys(fn (UnitStatus $status): array => [$status->value => UnitStatus::options()[$status->value]])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'view' => Pages\ViewUnit::route('/{record}'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }
}
