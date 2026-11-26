<?php

namespace App\Filament\Pages;

use App\Enums\ContractVersionStatus;
use App\Enums\Currency;
use App\Enums\RentFrequency;
use App\Filament\Resources\ContractResource;
use App\Models\Contract;
use App\Models\Property;
use App\Services\RentRollQuery;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Who owes rent, how much and since when — without opening contracts one by one.
 *
 * Read-only: rent is recorded against the contract's payment schedule, so every
 * row links back to its contract instead of taking money here.
 */
class RentRoll extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string $view = 'filament.pages.rent-roll';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationGroup = '💼 Operations';

    public static function getNavigationGroup(): ?string
    {
        return __(static::$navigationGroup);
    }

    public static function getNavigationLabel(): string
    {
        return __('rent_roll.navigation');
    }

    public function getTitle(): string
    {
        return __('rent_roll.title');
    }

    public function getSubheading(): ?string
    {
        $grace = $this->rentRoll()->graceDays();

        return $grace > 0
            ? __('rent_roll.subheading_with_grace', ['days' => $grace])
            : __('rent_roll.subheading');
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return (bool) $user && ($user->isSuperAdmin() || $user->can('page_RentRoll'));
    }

    public function table(Table $table): Table
    {
        $overdueBefore = $this->rentRoll()->overdueBefore();

        return $table
            ->query(fn (): Builder => $this->rentRoll()->builder())
            ->defaultSort('overdue_amount', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('tenant_name')
                    ->label(__('rent_roll.columns.tenant'))
                    ->description(fn (Contract $record): string => (string) $record->contract_number)
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('unit.unit_number')
                    ->label(__('rent_roll.columns.unit'))
                    ->description(fn (Contract $record): ?string => $record->unit?->property?->name)
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('rent_amount')
                    ->label(__('rent_roll.columns.rent'))
                    ->state(fn (Contract $record): string => $this->rentLabel($record))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('next_due_date')
                    ->label(__('rent_roll.columns.next_due'))
                    ->date()
                    ->sortable()
                    ->placeholder(__('rent_roll.nothing_due')),

                Tables\Columns\TextColumn::make('overdue_amount')
                    ->label(__('rent_roll.columns.overdue'))
                    ->state(fn (Contract $record): string => $this->money($record->overdue_amount, $record))
                    ->badge()
                    ->color(fn (Contract $record): string => (float) $record->overdue_amount > 0 ? 'danger' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('days_late')
                    ->label(__('rent_roll.columns.days_late'))
                    ->state(fn (Contract $record): string => $record->oldest_overdue_date
                        ? (string) Carbon::parse($record->oldest_overdue_date)->diffInDays(today())
                        : '—')
                    ->badge()
                    ->color(fn (Contract $record): string => $this->latenessColor($record)),

                Tables\Columns\TextColumn::make('outstanding_amount')
                    ->label(__('rent_roll.columns.outstanding'))
                    ->state(fn (Contract $record): string => $this->money($record->outstanding_amount, $record))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('term_end_date')
                    ->label(__('rent_roll.columns.ends'))
                    ->date()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('installment_count')
                    ->label(__('rent_roll.columns.schedule'))
                    ->state(fn (Contract $record): string => (int) $record->installment_count > 0
                        ? __('rent_roll.schedule_issued')
                        : __('rent_roll.schedule_missing'))
                    ->badge()
                    ->color(fn (Contract $record): string => (int) $record->installment_count > 0 ? 'success' : 'warning'),
            ])
            ->filters([
                Tables\Filters\Filter::make('overdue')
                    ->label(__('rent_roll.filters.overdue'))
                    ->query(fn (Builder $query): Builder => $query->where('roll.overdue_amount', '>', 0))
                    ->toggle(),

                Tables\Filters\SelectFilter::make('due_within')
                    ->label(__('rent_roll.filters.due_within'))
                    ->options([
                        '7' => __('rent_roll.filters.days', ['days' => 7]),
                        '30' => __('rent_roll.filters.days', ['days' => 30]),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereNotNull('roll.next_due_date')
                            ->where('roll.next_due_date', '<=', today()->addDays((int) $data['value'])->toDateString())
                        : $query),

                Tables\Filters\Filter::make('ending_soon')
                    ->label(__('rent_roll.filters.ending_soon'))
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'versions',
                        fn (Builder $versions) => $versions
                            ->whereIn('status', [ContractVersionStatus::Signed->value, ContractVersionStatus::Approved->value])
                            ->whereNotNull('end_date')
                            ->whereDate('end_date', '<=', today()->addDays(60)->toDateString()),
                    ))
                    ->toggle(),

                Tables\Filters\Filter::make('no_schedule')
                    ->label(__('rent_roll.filters.no_schedule'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('roll.contract_id'))
                    ->toggle(),

                Tables\Filters\SelectFilter::make('property_id')
                    ->label(__('rent_roll.filters.property'))
                    ->options(fn (): array => Property::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->attribute('contracts.property_id'),
            ])
            ->actions([
                Tables\Actions\Action::make('openContract')
                    ->label(__('rent_roll.actions.open_contract'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Contract $record): string => ContractResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading(__('rent_roll.empty.heading'))
            ->emptyStateDescription(__('rent_roll.empty.description'))
            ->emptyStateIcon('heroicon-o-building-office-2');
    }

    private function rentRoll(): RentRollQuery
    {
        return new RentRollQuery(auth()->user());
    }

    private function rentLabel(Contract $record): string
    {
        if (blank($record->rent_amount)) {
            return '—';
        }

        $frequency = RentFrequency::tryFrom((string) $record->rent_frequency);

        return $this->money($record->rent_amount, $record).($frequency ? ' · '.$frequency->getLabel() : '');
    }

    private function money(mixed $amount, Contract $record): string
    {
        $code = (string) ($record->contract_currency ?: Currency::USD->value);

        return number_format((float) $amount, 2).' '.(Currency::tryFrom($code)?->getSymbol() ?? $code);
    }

    private function latenessColor(Contract $record): string
    {
        if (! $record->oldest_overdue_date) {
            return 'gray';
        }

        $days = Carbon::parse($record->oldest_overdue_date)->diffInDays(today());

        return $days >= 30 ? 'danger' : ($days >= 7 ? 'warning' : 'info');
    }
}
