<?php
namespace App\Filament\Resources\UnitResource\RelationManagers;
use App\Models\Party;
use App\Services\UnitSetupService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
class OwnershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'ownerships';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Ownership');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('party.name')->label(__('Owner')),
            Tables\Columns\TextColumn::make('ownership_percentage')->label(__('Ownership %'))->suffix('%'),
            Tables\Columns\TextColumn::make('start_date')->label(__('Start Date'))->date(),
            Tables\Columns\TextColumn::make('end_date')->label(__('End Date'))->date()->placeholder(__('Current'))->badge(),
        ])->headerActions([
            Tables\Actions\Action::make('replaceOwnership')
                ->label(__('Replace ownership'))
                ->icon('heroicon-o-user-group')
                ->tooltip(__('Record a new complete ownership allocation'))
                ->visible(fn() => auth()->user()->can('changeOwnership', $this->getOwnerRecord()))
                ->form([
                    Forms\Components\DatePicker::make('start_date')->label(__('Start Date'))->required()->default(now()),
                    Forms\Components\Textarea::make('notes')->label(__('Notes')),
                    Forms\Components\Repeater::make('allocations')->label(__('Allocations'))->schema([
                        Forms\Components\Select::make('party_id')->label(__('Owner'))->options(fn() => Party::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                        Forms\Components\TextInput::make('ownership_percentage')->label(__('Ownership %'))->numeric()->minValue(0.01)->maxValue(100)->required(),
                    ])->minItems(1)->required(),
                ])->action(function (array $data): void {
                    try {
                        app(UnitSetupService::class)->replaceOwnership(auth()->user(), $this->getOwnerRecord(), $data['allocations'], $data['start_date'], $data['notes'] ?? null);
                        Notification::make()->success()->title(__('Ownership replaced'))->send();
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title(__('Cannot replace ownership'))->body(collect($e->errors())->flatten()->first())->send();
                    }
                }),
        ])->actions([Tables\Actions\ViewAction::make()]);
    }
}
