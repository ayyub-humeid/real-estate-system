<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Enums\ContractVersionStatus;
use App\Models\{ContractVersion, Party};
use App\Services\ContractDraftService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Contract versions');
    }

    public static function getModelLabel(): string
    {
        return __('Version');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Contract versions');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version_number')->label(__('Version'))->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->formatStateUsing(fn ($state): string => $state instanceof ContractVersionStatus ? $state->getLabel() : ContractVersionStatus::tryFrom((string) $state)?->getLabel() ?? (string) $state)
                    ->color(fn ($state): ?string => $state instanceof ContractVersionStatus ? $state->color() : ContractVersionStatus::tryFrom((string) $state)?->color())
                    ->badge(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('Total amount'))
                    ->money(fn (ContractVersion $record): string => $record->currency ?? 'USD')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('submitted_at')->label(__('Submitted at'))->dateTime()->placeholder('—'),
                Tables\Columns\TextColumn::make('approved_at')->label(__('Approved at'))->dateTime()->placeholder('—'),
                Tables\Columns\TextColumn::make('parties_count')->label(__('Parties'))->counts('parties')->badge(),
                Tables\Columns\TextColumn::make('terms_count')->label(__('Terms'))->counts('terms')->badge(),
            ])
            ->defaultSort('version_number', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('Details'))
                    ->modalHeading(__('Version overview'))
                    ->modalWidth('4xl')
                    ->infolist([
                        Infolists\Components\Section::make(__('Version overview'))->schema([
                            Infolists\Components\TextEntry::make('version_number')->label(__('Version'))->prefix('V'),
                            Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge(),
                            Infolists\Components\TextEntry::make('total_amount')->label(__('Total amount'))->money(fn (ContractVersion $record): string => $record->currency ?? 'USD')->placeholder('—'),
                            Infolists\Components\TextEntry::make('start_date')->label(__('Start date'))->date()->placeholder('—'),
                            Infolists\Components\TextEntry::make('end_date')->label(__('End date'))->date()->placeholder('—'),
                        ])->columns(3),
                        Infolists\Components\Section::make(__('Contract parties'))->schema([
                            Infolists\Components\TextEntry::make('parties_summary')
                                ->label(__('Contract parties'))
                                ->state(function (ContractVersion $record): array {
                                    $roles = [
                                        'buyer' => __('Buyer'),
                                        'seller' => __('Seller'),
                                        'landlord' => __('Landlord'),
                                        'tenant' => __('Tenant'),
                                        'guarantor' => __('Guarantor'),
                                    ];

                                    return $record->parties()->with('party')->get()->map(function ($party) use ($roles): string {
                                        $role = $roles[$party->role] ?? ($party->role ?: __('Party'));
                                        $name = $party->snapshot['name'] ?? $party->party?->name ?? '—';
                                        $share = $party->share_percentage !== null ? ' ('.$party->share_percentage.'%)' : '';

                                        return trim("{$role}: {$name}{$share}");
                                    })->all();
                                })
                                ->listWithLineBreaks()
                                ->placeholder('—'),
                        ]),
                        Infolists\Components\Section::make(__('Contract terms'))->schema([
                            Infolists\Components\TextEntry::make('terms_summary')
                                ->label(__('Contract terms'))
                                ->state(function (ContractVersion $record): array {
                                    $types = [
                                        'clause' => __('Clause'),
                                        'commercial' => __('Commercial term'),
                                        'legal' => __('Legal term'),
                                    ];

                                    return $record->terms()->get()->map(function ($term) use ($types): string {
                                        $type = $types[$term->term_type] ?? $term->term_type;

                                        return "[{$type}] {$term->title}".($term->text ? ': '.$term->text : '');
                                    })->all();
                                })
                                ->listWithLineBreaks()
                                ->placeholder('—'),
                        ]),
                    ]),
                Tables\Actions\Action::make('editDraft')
                    ->label(__('Edit draft'))
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading(__('Edit draft version'))
                    ->modalSubmitActionLabel(__('Save changes'))
                    ->visible(fn (ContractVersion $record): bool => $record->status === ContractVersionStatus::Draft && auth()->user()->can('update_contract_version'))
                    ->fillForm(fn (ContractVersion $record): array => [
                        'total_amount' => $record->total_amount,
                        'currency' => $record->currency,
                        'start_date' => $record->start_date,
                        'end_date' => $record->end_date,
                        'delivery_date' => $record->delivery_date,
                        'parties' => $record->parties()->get()->map(fn ($party): array => [
                            'party_id' => $party->party_id,
                            'role' => $party->role,
                            'share_percentage' => $party->share_percentage,
                        ])->all(),
                        'terms' => $record->terms()->get()->map(fn ($term): array => [
                            'term_type' => $term->term_type,
                            'title' => $term->title,
                            'text' => $term->text,
                        ])->all(),
                    ])
                    ->form(fn (): array => $this->draftForm())
                    ->action(function (ContractVersion $record, array $data): void {
                        try {
                            app(ContractDraftService::class)->updateDraft(auth()->user(), $record, $data);
                            Notification::make()->success()->title(__('Draft version updated'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()
                                ->title(__('Unable to update draft'))
                                ->body(collect($exception->errors())->flatten()->first())
                                ->send();
                        } catch (AuthorizationException) {
                            Notification::make()->danger()->title(__('Unauthorized'))->body(__('You do not have permission to edit this draft.'))->send();
                        }
                    }),
            ]);
    }

    private function draftForm(): array
    {
        $companyId = $this->getOwnerRecord()->company_id;

        return [
            Forms\Components\TextInput::make('total_amount')->label(__('Total amount'))->numeric()->minValue(0),
            Forms\Components\TextInput::make('currency')->label(__('Currency'))->length(3),
            Forms\Components\DatePicker::make('start_date')->label(__('Start date')),
            Forms\Components\DatePicker::make('end_date')->label(__('End date')),
            Forms\Components\DatePicker::make('delivery_date')->label(__('Delivery date')),
            Forms\Components\Repeater::make('parties')
                ->label(__('Contract parties'))
                ->schema([
                    Forms\Components\Select::make('party_id')
                        ->label(__('Party'))
                        ->options(fn (): array => Party::withoutGlobalScopes()
                            ->where('company_id', $companyId)
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    Forms\Components\Select::make('role')
                        ->label(__('Role'))
                        ->options([
                            'buyer' => __('Buyer'), 'seller' => __('Seller'), 'landlord' => __('Landlord'),
                            'tenant' => __('Tenant'), 'guarantor' => __('Guarantor'),
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('share_percentage')->label(__('Share percentage'))->numeric()->minValue(0)->maxValue(100),
                ])
                ->columns(3)
                ->defaultItems(1)
                ->addActionLabel(__('Add party')),
            Forms\Components\Repeater::make('terms')
                ->label(__('Contract terms'))
                ->schema([
                    Forms\Components\Select::make('term_type')->label(__('Term type'))
                        ->options(['clause' => __('Clause'), 'commercial' => __('Commercial term'), 'legal' => __('Legal term')])
                        ->default('clause')->required(),
                    Forms\Components\TextInput::make('title')->label(__('Term title'))->required(),
                    Forms\Components\Textarea::make('text')->label(__('Term text'))->columnSpanFull(),
                ])
                ->columns(2)
                ->defaultItems(1)
                ->addActionLabel(__('Add term')),
        ];
    }
}
