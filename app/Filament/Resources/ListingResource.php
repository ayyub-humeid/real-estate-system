<?php

namespace App\Filament\Resources;

use App\Enums\{ListingStatus, ListingType, RentFrequency};
use App\Filament\Resources\ListingResource\Pages;
use App\Filament\Resources\ListingResource\RelationManagers;
use App\Models\{Company, Listing, ProjectPlannedUnit, Unit};
use App\Services\ListingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ListingResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = Listing::class;
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationGroup = '💼 Sales & Marketing';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['listable', 'createdBy']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Commercial target'))
                ->description(__('A Listing represents one planned or physical Unit. It does not change the Unit lifecycle.'))
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label(__('Company'))
                        ->options(fn() => Company::query()->orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->disabled(fn(?Listing $record) => $record !== null)
                        ->live()
                        ->visible(fn() => auth()->user()->isSuperAdmin()),
                    Forms\Components\Select::make('listable_type')
                        ->label(__('Target type'))
                        ->options(['planned_unit' => __('Planned Unit (off-plan)'), 'unit' => __('Actual Unit')])
                        ->required()
                        ->disabled(fn(?Listing $record) => $record !== null)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set): void {
                            $set('listable_id', null);
                        }),
                    Forms\Components\Select::make('listable_id')
                        ->label(__('Target Unit'))
                        ->options(fn(Forms\Get $get) => self::targetOptions($get('listable_type'), $get('company_id')))
                        ->searchable()
                        ->required()
                        ->disabled(fn(?Listing $record) => $record !== null)
                        ->native(false),
                ])->columns(3),
            Forms\Components\Section::make(__('Listing details'))
                ->schema([
                    Forms\Components\Select::make('listing_type')
                        ->label(__('Listing type'))
                        ->options(ListingType::options())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Forms\Set $set): void {
                            if ($state === ListingType::Sale->value) {
                                $set('rent_frequency', null);
                                $set('security_deposit', null);
                                $set('minimum_lease_months', null);
                            }
                        }),
                    Forms\Components\TextInput::make('title')->label(__('Title'))->required()->maxLength(255)->columnSpan(2),
                    Forms\Components\Textarea::make('description')->label(__('Description'))->rows(4)->columnSpanFull(),
                    Forms\Components\TextInput::make('asking_price')->label(__('Asking price'))->numeric()->minValue(0.01)->required()->prefix('$'),
                    Forms\Components\TextInput::make('currency')->label(__('Currency'))->required()->length(3)->default('USD')->extraInputAttributes(['style' => 'text-transform: uppercase;']),
                    Forms\Components\Select::make('rent_frequency')
                        ->label(__('Rent frequency'))
                        ->options(RentFrequency::options())
                        ->visible(fn(Forms\Get $get) => $get('listing_type') === ListingType::Rent->value)
                        ->required(fn(Forms\Get $get) => $get('listing_type') === ListingType::Rent->value),
                    Forms\Components\TextInput::make('security_deposit')
                        ->label(__('Security deposit'))
                        ->numeric()->minValue(0)
                        ->visible(fn(Forms\Get $get) => $get('listing_type') === ListingType::Rent->value),
                    Forms\Components\TextInput::make('minimum_lease_months')
                        ->label(__('Minimum lease months'))
                        ->numeric()->integer()->minValue(1)
                        ->visible(fn(Forms\Get $get) => $get('listing_type') === ListingType::Rent->value),
                    Forms\Components\Textarea::make('availability_notes')->label(__('Availability notes'))->rows(3)->columnSpanFull(),
                    Forms\Components\Textarea::make('price_change_reason')
                        ->label(__('Price change reason'))
                        ->helperText(__('Recorded only when price, currency, or rent frequency changes.'))
                        ->dehydrated(false)
                        ->visible(fn(?Listing $record) => $record !== null)
                        ->columnSpanFull(),
                ])->columns(3),
            Forms\Components\Section::make(__('Lifecycle'))
                ->schema([
                    Forms\Components\Placeholder::make('status')
                        ->label(__('Status'))
                        ->content(fn(?Listing $record) => $record ? $record->status->getLabel() : __('Draft on creation'))
                        ->helperText(__('Use Publish, Pause, Archive, and Restore actions. Status cannot be edited here.')),
                ])->visible(fn(?Listing $record) => $record !== null),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Components\Section::make(__('Listing overview'))->schema([
                Components\TextEntry::make('title')->label(__('Title'))->weight('bold'),
                Components\TextEntry::make('target_label')->label(__('Commercial target'))->state(fn(Listing $record) => $record->target_label),
                Components\TextEntry::make('listing_type')->label(__('Listing type'))->badge()->formatStateUsing(fn(ListingType $state) => $state->getLabel()),
                Components\TextEntry::make('status')->label(__('Status'))->badge()->color(fn(ListingStatus $state) => match ($state) {
                    ListingStatus::Published => 'success',
                    ListingStatus::Paused => 'warning',
                    ListingStatus::Archived => 'danger',
                    default => 'gray'
                })->formatStateUsing(fn(ListingStatus $state) => $state->getLabel()),
                Components\TextEntry::make('asking_price')->label(__('Asking price'))->money(fn(Listing $record) => $record->currency),
                Components\TextEntry::make('rent_frequency')->label(__('Rent frequency'))->placeholder(__('—'))->formatStateUsing(fn(?RentFrequency $state) => $state?->getLabel() ?? __('—')),
                Components\TextEntry::make('security_deposit')->label(__('Security deposit'))->money(fn(Listing $record) => $record->currency)->placeholder(__('—')),
                Components\TextEntry::make('minimum_lease_months')->label(__('Minimum lease months'))->suffix(' ' . __('months'))->placeholder(__('—')),
            ])->columns(3),
            Components\Section::make(__('Marketing copy'))->schema([
                Components\TextEntry::make('description')->label(__('Description'))->placeholder(__('—'))->columnSpanFull(),
                Components\TextEntry::make('availability_notes')->label(__('Availability notes'))->placeholder(__('—'))->columnSpanFull(),
            ]),
            Components\Section::make(__('Lifecycle timestamps'))->schema([
                Components\TextEntry::make('published_at')->label(__('Published At'))->dateTime()->placeholder(__('Not published')),
                Components\TextEntry::make('paused_at')->label(__('Paused At'))->dateTime()->placeholder(__('—')),
                Components\TextEntry::make('archived_at')->label(__('Archived At'))->dateTime()->placeholder(__('—')),
                Components\TextEntry::make('createdBy.name')->label(__('Created by'))->placeholder(__('System')),
            ])->columns(4),
        ]);
    }
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->label(__('Title'))->searchable()->sortable()->weight('bold'),
            Tables\Columns\TextColumn::make('target_label')->label(__('Target'))->state(fn(Listing $record) => $record->target_label),
            Tables\Columns\TextColumn::make('listable_type')->label(__('Representation'))->badge()->formatStateUsing(fn(string $state) => $state === 'unit' ? __('Actual') : __('Planned')),
            Tables\Columns\TextColumn::make('listing_type')->label(__('Listing type'))->badge()->formatStateUsing(fn(ListingType $state) => $state->getLabel()),
            Tables\Columns\TextColumn::make('asking_price')->label(__('Asking price'))->money(fn(Listing $record) => $record->currency)->sortable(),
            Tables\Columns\TextColumn::make('rent_frequency')->label(__('Rent frequency'))->placeholder(__('—'))->formatStateUsing(fn(?RentFrequency $state) => $state?->getLabel() ?? __('—')),
            Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()->colors(['gray' => 'draft', 'success' => 'published', 'warning' => 'paused', 'danger' => 'archived'])->formatStateUsing(fn(ListingStatus $state) => $state->getLabel()),
            Tables\Columns\TextColumn::make('published_at')->label(__('Published At'))->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])
        ->emptyStateHeading(__('No listings found'))
        ->filters([
            Tables\Filters\SelectFilter::make('status')->label(__('Status'))->options(ListingStatus::options()),
            Tables\Filters\SelectFilter::make('listing_type')->label(__('Listing type'))->options(ListingType::options()),
            Tables\Filters\SelectFilter::make('listable_type')->label(__('Representation'))->options(['planned_unit' => __('Planned Unit'), 'unit' => __('Actual Unit')]),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            self::workflowAction('publish', __('Publish'), 'heroicon-o-arrow-up-circle', 'success'),
            self::workflowAction('pause', __('Pause'), 'heroicon-o-pause-circle', 'warning'),
            self::workflowAction('archive', __('Archive'), 'heroicon-o-archive-box', 'danger'),
            self::workflowAction('restore', __('Restore to draft'), 'heroicon-o-arrow-path', 'gray'),
            Tables\Actions\DeleteAction::make(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [RelationManagers\PriceHistoriesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListListings::route('/'),
            'create' => Pages\CreateListing::route('/create'),
            'view' => Pages\ViewListing::route('/{record}'),
            'edit' => Pages\EditListing::route('/{record}/edit'),
        ];
    }

    public static function targetOptions(?string $type, mixed $companyId): array
    {
        $companyId ??= auth()->user()?->company_id;
        if (! $companyId || ! in_array($type, ['planned_unit', 'unit'], true)) {
            return [];
        }
        return $type === 'unit'
            ? Unit::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('unit_number')->pluck('unit_number', 'id')->all()
            : ProjectPlannedUnit::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('code')->pluck('code', 'id')->all();
    }

    private static function workflowAction(string $name, string $label, string $icon, string $color): Action
    {
        return Action::make($name)->label($label)->icon($icon)->color($color)->tooltip($label)
            ->visible(fn(Listing $record) => match ($name) {
                'publish' => in_array($record->status, [ListingStatus::Draft, ListingStatus::Paused], true),
                'pause' => $record->status === ListingStatus::Published,
                'archive' => in_array($record->status, [ListingStatus::Published, ListingStatus::Paused], true),
                'restore' => $record->status === ListingStatus::Archived,
            })
            ->authorize($name)
            ->requiresConfirmation()
            ->action(function (Listing $record) use ($name, $label): void {
                try {
                    app(ListingService::class)->{$name}(auth()->user(), $record);
                    Notification::make()->success()->title(__('Listing action completed'))->send();
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title(__('Cannot execute Listing action'))->body(collect($exception->errors())->flatten()->first())->send();
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title(__('Unauthorized'))->body(__('You do not have permission for this Listing action.'))->send();
                }
            });
    }
}
