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
    protected static ?string $title = 'Ownership';
    public function isReadOnly(): bool
    {
        return false;
    }
    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('party.name')->label('Owner'),
            Tables\Columns\TextColumn::make('ownership_percentage')->suffix('%'),
            Tables\Columns\TextColumn::make('start_date')->date(),
            Tables\Columns\TextColumn::make('end_date')->date()->placeholder('Current')->badge(),
        ])->headerActions([
                    Tables\Actions\Action::make('replaceOwnership')->label('Replace ownership')->icon('heroicon-o-user-group')->tooltip('Record a new complete ownership allocation')
                        ->visible(fn() => auth()->user()->can('changeOwnership', $this->getOwnerRecord()))
                        ->form([
                            Forms\Components\DatePicker::make('start_date')->required()->default(now()),
                            Forms\Components\Textarea::make('notes'),
                            Forms\Components\Repeater::make('allocations')->schema([
                                Forms\Components\Select::make('party_id')->options(fn() => Party::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                                Forms\Components\TextInput::make('ownership_percentage')->numeric()->minValue(0.01)->maxValue(100)->required(),
                            ])->minItems(1)->required(),
                        ])->action(function (array $data): void {
                            try {
                                app(UnitSetupService::class)->replaceOwnership(auth()->user(), $this->getOwnerRecord(), $data['allocations'], $data['start_date'], $data['notes'] ?? null);
                                Notification::make()->success()->title('Ownership replaced')->send(); } catch (ValidationException $e) {
                                Notification::make()->danger()->title('Cannot replace ownership')->body(collect($e->errors())->flatten()->first())->send(); } }),
                ])->actions([Tables\Actions\ViewAction::make()]);
    }
}
