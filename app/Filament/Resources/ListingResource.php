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
            Forms\Components\Section::make('Commercial target')
                ->description('A Listing represents one planned or physical Unit. It does not change the Unit lifecycle.')
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Company')
                        ->options(fn() => Company::query()->orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->disabled(fn(?Listing $record) => $record !== null)
                        ->live()
                        ->visible(fn() => auth()->user()->isSuperAdmin()),
                    Forms\Components\Select::make('listable_type')
                        ->label('Target type')
                        ->options(['planned_unit' => 'Planned Unit (off-plan)', 'unit' => 'Actual Unit'])
                        ->required()
                        ->disabled(fn(?Listing $record) => $record !== null)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set): void {
                            $set('listable_id', null);
                        }),
                    Forms\Components\Select::make('listable_id')
                        ->label('Target Unit')
                        ->options(fn(Forms\Get $get) => self::targetOptions($get('listable_type'), $get('company_id')))
                        ->searchable()
                        ->required()
                        ->disabled(fn(?Listing $record) => $record !== null)
                        ->native(false),
                ])->columns(3),
            Forms\Components\Section::make('Listing details')
                ->schema([
                    Forms\Components\Select::make('listing_type')
                        ->label('Listing type')
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
                    Forms\Components\TextInput::make('title')->required()->maxLength(255)->columnSpan(2),
                    Forms\Components\Textarea::make('description')->rows(4)->columnSpanFull(),
                    Forms\Components\TextInput::make('asking_price')->numeric()->minValue(0.01)->required()->prefix('$'),
                    Forms\Components\TextInput::make('currency')->required()->length(3)->default('USD')->extraInputAttributes(['style' => 'text-transform: uppercase;']),
                    Forms\Components\Select::make('rent_frequency')
                        ->options(RentFrequency::options())
                        ->visible(fn(Forms\Get $get) => $get('listing_type') === ListingType::Rent->value)
                        ->required(fn(Forms\Get $get) => $get('listing_type') === ListingType::Rent->value),
                    Forms\Components\TextInput::make('security_deposit')
                        ->numeric()->minValue(0)
                        ->visible(fn(Forms\Get $get) => $get('listing_type') === ListingType::Rent->value),
                    Forms\Components\TextInput::make('minimum_lease_months')
                        ->numeric()->integer()->minValue(1)
                        ->visible(fn(Forms\Get $get) => $get('listing_type') === ListingType::Rent->value),
                    Forms\Components\Textarea::make('availability_notes')->rows(3)->columnSpanFull(),
                    Forms\Components\Textarea::make('price_change_reason')
                        ->label('Price change reason')
                        ->helperText('Recorded only when price, currency, or rent frequency changes.')
                        ->dehydrated(false)
                        ->visible(fn(?Listing $record) => $record !== null)
                        ->columnSpanFull(),
                ])->columns(3),
            Forms\Components\Section::make('Lifecycle')
                ->schema([
                    Forms\Components\Placeholder::make('status')
                        ->content(fn(?Listing $record) => $record ? ucfirst($record->status->value) : 'Draft on creation')
                        ->helperText('Use Publish, Pause, Archive, and Restore actions. Status cannot be edited here.'),
                ])->visible(fn(?Listing $record) => $record !== null),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Components\Section::make('Listing overview')->schema([
                Components\TextEntry::make('title')->weight('bold'),
                Components\TextEntry::make('target_label')->label('Commercial target')->state(fn(Listing $record) => $record->target_label),
                Components\TextEntry::make('listing_type')->badge()->formatStateUsing(fn(ListingType $state) => ucfirst($state->value)),
                Components\TextEntry::make('status')->badge()->color(fn(ListingStatus $state) => match ($state) {
                    ListingStatus::Published => 'success',
                    ListingStatus::Paused => 'warning',
                    ListingStatus::Archived => 'danger',
                    default => 'gray'
                })->formatStateUsing(fn(ListingStatus $state) => ucfirst($state->value)),
                Components\TextEntry::make('asking_price')->money(fn(Listing $record) => $record->currency),
                Components\TextEntry::make('rent_frequency')->placeholder('—')->formatStateUsing(fn(?RentFrequency $state) => $state ? ucwords(str_replace('_', ' ', $state->value)) : '—'),
                Components\TextEntry::make('security_deposit')->money(fn(Listing $record) => $record->currency)->placeholder('—'),
                Components\TextEntry::make('minimum_lease_months')->suffix(' months')->placeholder('—'),
            ])->columns(3),
            Components\Section::make('Marketing copy')->schema([
                Components\TextEntry::make('description')->placeholder('—')->columnSpanFull(),
                Components\TextEntry::make('availability_notes')->placeholder('—')->columnSpanFull(),
            ]),
            Components\Section::make('Lifecycle timestamps')->schema([
                Components\TextEntry::make('published_at')->dateTime()->placeholder('Not published'),
                Components\TextEntry::make('paused_at')->dateTime()->placeholder('—'),
                Components\TextEntry::make('archived_at')->dateTime()->placeholder('—'),
                Components\TextEntry::make('createdBy.name')->label('Created by')->placeholder('System'),
            ])->columns(4),
        ]);
    }
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->sortable()->weight('bold'),
            Tables\Columns\TextColumn::make('target_label')->label('Target')->state(fn(Listing $record) => $record->target_label),
            Tables\Columns\TextColumn::make('listable_type')->label('Representation')->badge()->formatStateUsing(fn(string $state) => $state === 'unit' ? 'Actual' : 'Planned'),
            Tables\Columns\TextColumn::make('listing_type')->badge()->formatStateUsing(fn(ListingType $state) => ucfirst($state->value)),
            Tables\Columns\TextColumn::make('asking_price')->money(fn(Listing $record) => $record->currency)->sortable(),
            Tables\Columns\TextColumn::make('rent_frequency')->placeholder('—')->formatStateUsing(fn(?RentFrequency $state) => $state ? ucwords(str_replace('_', ' ', $state->value)) : '—'),
            Tables\Columns\TextColumn::make('status')->badge()->colors(['gray' => 'draft', 'success' => 'published', 'warning' => 'paused', 'danger' => 'archived'])->formatStateUsing(fn(ListingStatus $state) => ucfirst($state->value)),
            Tables\Columns\TextColumn::make('published_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options(ListingStatus::options()),
            Tables\Filters\SelectFilter::make('listing_type')->options(ListingType::options()),
            Tables\Filters\SelectFilter::make('listable_type')->label('Representation')->options(['planned_unit' => 'Planned Unit', 'unit' => 'Actual Unit']),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            self::workflowAction('publish', 'Publish', 'heroicon-o-arrow-up-circle', 'success'),
            self::workflowAction('pause', 'Pause', 'heroicon-o-pause-circle', 'warning'),
            self::workflowAction('archive', 'Archive', 'heroicon-o-archive-box', 'danger'),
            self::workflowAction('restore', 'Restore to draft', 'heroicon-o-arrow-path', 'gray'),
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
                    Notification::make()->success()->title("Listing {$label}")->send();
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title("Cannot {$label} Listing")->body(collect($exception->errors())->flatten()->first())->send();
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title('Unauthorized')->body('You do not have permission for this Listing action.')->send();
                }
            });
    }
}
