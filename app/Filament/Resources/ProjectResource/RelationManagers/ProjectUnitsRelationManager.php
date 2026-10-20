<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Enums\{UnitStatus, UnitType};
use App\Models\{ProjectPlannedUnit, Property, Unit};
use App\Services\UnitSetupService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ProjectUnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Actual Units');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('property.name')->label(__('Property')),
            Tables\Columns\TextColumn::make('unit_number')->label(__('Unit'))->weight('bold'),
            Tables\Columns\TextColumn::make('plannedUnit.code')->label(__('Planned source'))->placeholder(__('Manual')),
            Tables\Columns\TextColumn::make('status')
                ->label(__('Physical status'))
                ->badge()
                ->colors([
                    'gray' => 'draft',
                    'success' => 'ready',
                    'warning' => 'maintenance',
                    'danger' => 'inactive',
                ])
                ->formatStateUsing(fn ($state): string => UnitStatus::tryFrom($state)?->getLabel() ?? $state),
            Tables\Columns\TextColumn::make('actual_area')->label(__('Actual Area'))->suffix(fn(Unit $u) => $u->area_unit ? " {$u->area_unit}" : ''),
        ])->headerActions([
            Tables\Actions\Action::make('convertPlannedUnit')->label(__('Create from approved plan'))
                ->icon('heroicon-o-arrow-down-tray')->tooltip(__('Create a draft actual Unit from an approved planned Unit'))
                ->visible(fn() => auth()->user()->can('create_actual_unit_from_planned_unit'))
                ->form([
                    Forms\Components\Select::make('planned_unit_id')->label(__('Approved planned Unit'))->options(fn() => ProjectPlannedUnit::query()->whereHas('floor.building', fn($q) => $q->where('project_id', $this->getOwnerRecord()->id))->where('status', 'approved')->orderBy('code')->pluck('code', 'id'))->required()->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set): void {
                            $planned = ProjectPlannedUnit::query()->find($state);
                            if ($planned) {
                                $set('type', $planned->unit_type);
                            }
                        }),
                    Forms\Components\Select::make('property_id')->label(__('Property'))
                        ->options(fn() => Property::query()
                            ->whereIn('id', $this->getOwnerRecord()
                                ->activeProjectProperties()
                                ->pluck('property_id'))
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->required(),
                    Forms\Components\TextInput::make('unit_number')->label(__('Unit Number'))
                        ->required(),
                    Forms\Components\Select::make('type')->label(__('Type'))
                        ->options(UnitType::options())->required(),
                    Forms\Components\TextInput::make('actual_area')->label(__('Actual Area'))
                        ->numeric()->minValue(0)->required(),
                    Forms\Components\Select::make('area_unit')->label(__('Area Unit'))
                        ->options(['m2' => 'm²', 'sqft' => 'sq ft'])->default('m2')->required(),
                ])->action(function (array $data): void {
                    try {
                        $planned = ProjectPlannedUnit::findOrFail($data['planned_unit_id']);
                        app(UnitSetupService::class)->convertPlannedUnit(auth()->user(), $planned, $data);
                        Notification::make()->success()->title(__('Actual Unit created'))->send();
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title(__('Cannot create actual Unit'))->body(collect($e->errors())->flatten()->first())->send();
                    }
                }),
        ])->actions([
            Tables\Actions\ViewAction::make()->label(__('Details'))->url(fn(Unit $u) => \App\Filament\Resources\UnitResource::getUrl('view', ['record' => $u])),
            Tables\Actions\Action::make('change_status')
                ->label(__('Change status'))
                ->icon('heroicon-o-arrow-path')
                ->tooltip(__('Change physical status'))
                ->visible(fn (Unit $record): bool => ! empty($this->transitionOptions($record))
                    && (auth()->user()->can('changeStatus', $record) || auth()->user()->can('reactivate', $record)))
                ->form(fn (Unit $record): array => [
                    Forms\Components\Select::make('status')
                        ->label(__('Physical status'))
                        ->options($this->transitionOptions($record))
                        ->required(),
                    Forms\Components\Textarea::make('reason')
                        ->label(__('Reason'))
                        ->required(fn (Forms\Get $get): bool => in_array($get('status'), [UnitStatus::Inactive->value, UnitStatus::Draft->value], true)),
                ])
                ->action(function (Unit $record, array $data): void {
                    try {
                        app(UnitSetupService::class)->transitionStatus(auth()->user(), $record, $data['status'], $data['reason'] ?? null);
                        Notification::make()->success()->title(__('Unit status updated'))->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title(__('Cannot change status'))->body(collect($exception->errors())->flatten()->first())->send();
                        throw $exception;
                    } catch (AuthorizationException) {
                        Notification::make()->danger()->title(__('Unauthorized'))->send();
                    }
                }),
        ]);
    }

    private function transitionOptions(Unit $unit): array
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
}
