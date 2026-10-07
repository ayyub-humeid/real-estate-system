<?php
namespace App\Filament\Resources\ProjectResource\RelationManagers;
use App\Enums\UnitType;
use App\Models\{ProjectPlannedUnit, Property, Unit};
use App\Services\UnitSetupService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
class ProjectUnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';
    protected static ?string $title = 'Actual Units';
    public function isReadOnly(): bool
    {
        return false;
    }
    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('property.name')->label('Property'),
            Tables\Columns\TextColumn::make('unit_number')->label('Unit')->weight('bold'),
            Tables\Columns\TextColumn::make('plannedUnit.code')->label('Planned source')->placeholder('Manual'),
            Tables\Columns\TextColumn::make('status')->label('Physical status')->badge(),
            Tables\Columns\TextColumn::make('actual_area')->suffix(fn(Unit $u) => $u->area_unit ? " {$u->area_unit}" : ''),
        ])->headerActions([
                    Tables\Actions\Action::make('convertPlannedUnit')->label('Create from approved plan')
                        ->icon('heroicon-o-arrow-down-tray')->tooltip('Create a draft actual Unit from an approved planned Unit')
                        ->visible(fn() => auth()->user()->can('create_actual_unit_from_planned_unit'))
                        ->form([
                            Forms\Components\Select::make('planned_unit_id')->label('Approved planned Unit')->options(fn() => ProjectPlannedUnit::query()->whereHas('floor.building', fn($q) => $q->where('project_id', $this->getOwnerRecord()->id))->where('status', 'approved')->orderBy('code')->pluck('code', 'id'))->required()->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                    $planned = ProjectPlannedUnit::query()->find($state);
                                    if ($planned) {
                                        $set('type', $planned->unit_type);
                                    }
                                }),
                            Forms\Components\Select::make('property_id')->label('Property')
                                ->options(fn() => Property::query()
                                    ->whereIn('id', $this->getOwnerRecord()
                                        ->activeProjectProperties()
                                        ->pluck('property_id'))
                                    ->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->required(),
                            Forms\Components\TextInput::make('unit_number')
                                ->required(),
                            Forms\Components\Select::make('type')
                                ->options(UnitType::options())->required(),
                            Forms\Components\TextInput::make('actual_area')
                                ->numeric()->minValue(0)->required(),
                            Forms\Components\Select::make('area_unit')
                                ->options(['m2' => 'm²', 'sqft' => 'sq ft'])->default('m2')->required(),
                        ])->action(function (array $data): void {
                            try {
                                $planned = ProjectPlannedUnit::findOrFail($data['planned_unit_id']);
                                app(UnitSetupService::class)->convertPlannedUnit(auth()->user(), $planned, $data);
                                Notification::make()->success()->title('Actual Unit created')->send();
                            } catch (ValidationException $e) {
                                Notification::make()->danger()->title('Cannot create actual Unit')->body(collect($e->errors())->flatten()->first())->send();
                            }
                        }),
                ])->actions([Tables\Actions\ViewAction::make()->url(fn(Unit $u) => \App\Filament\Resources\UnitResource::getUrl('view', ['record' => $u]))]);
    }
}
