<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Enums\{ContractAmendmentStatus, ContractStatus, ContractVersionStatus};
use App\Models\ContractVersion;
use App\Services\ContractWorkflowService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class AmendmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'amendments';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Contract amendments');
    }

    public static function getModelLabel(): string
    {
        return __('Amendments');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Contract amendments');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('amendment_type')
                    ->label(__('Amendment type'))
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'commercial' => __('Commercial amendment'),
                        'legal' => __('Legal amendment'),
                        'party' => __('Party amendment'),
                        'other' => __('Other'),
                        default => (string) $state,
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->formatStateUsing(fn ($state): string => $state instanceof ContractAmendmentStatus ? $state->getLabel() : ContractAmendmentStatus::tryFrom((string) $state)?->getLabel() ?? (string) $state)->color(fn ($state): ?string => $state instanceof ContractAmendmentStatus ? $state->color() : ContractAmendmentStatus::tryFrom((string) $state)?->color())->badge(),
                Tables\Columns\TextColumn::make('effective_date')->label(__('Effective date'))->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('reason')->label(__('Reason'))->limit(50),
                Tables\Columns\TextColumn::make('resultingVersion.version_number')->label(__('Resulting version'))->prefix('V')->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createFormalAmendment')
                    ->label(__('Create amendment'))
                    ->icon('heroicon-o-document-plus')
                    ->modalHeading(__('Create formal contract amendment'))
                    ->modalSubmitActionLabel(__('Create amendment'))
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === ContractStatus::Active && auth()->user()->can('create_contract_amendment'))
                    ->form([
                        Forms\Components\Select::make('base_contract_version_id')
                            ->label(__('Signed version to amend'))
                            ->options(fn (): array => $this->getOwnerRecord()->versions()
                                ->where('status', ContractVersionStatus::Signed->value)
                                ->orderByDesc('version_number')
                                ->get()
                                ->mapWithKeys(fn (ContractVersion $version): array => [$version->id => __('Version').' '.$version->version_number])
                                ->all())
                            ->required(),
                        Forms\Components\Select::make('amendment_type')
                            ->label(__('Amendment type'))
                            ->options([
                                'commercial' => __('Commercial amendment'),
                                'legal' => __('Legal amendment'),
                                'party' => __('Party amendment'),
                                'other' => __('Other'),
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('reason')->label(__('Reason'))->required(),
                        Forms\Components\DatePicker::make('effective_date')->label(__('Effective date')),
                    ])
                    ->action(function (array $data): void {
                        try {
                            app(ContractWorkflowService::class)->createAmendment(
                                auth()->user(),
                                $this->getOwnerRecord(),
                                ContractVersion::withoutGlobalScopes()->findOrFail($data['base_contract_version_id']),
                                $data['amendment_type'],
                                $data['reason'],
                                $data['effective_date'] ?? null,
                            );
                            Notification::make()->success()->title(__('Amendment draft created'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title(__('Cannot create amendment'))
                                ->body(collect($exception->errors())->flatten()->first())->send();
                        } catch (AuthorizationException) {
                            Notification::make()->danger()->title(__('Unauthorized'))->send();
                        }
                    }),
            ]);
    }
}
