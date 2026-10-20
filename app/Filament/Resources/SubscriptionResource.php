<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubscriptionResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = '🏢 Core';
    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['company', 'plan']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Subscription Details'))
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label(__('Company'))
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('plan_id')
                            ->label(__('Plan'))
                            ->relationship('plan', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label(__('Status'))
                            ->options([
                                'active' => __('Active'),
                                'expired' => __('Expired'),
                                'canceled' => __('Canceled'),
                                'trailing' => __('Trailing'),
                                'past_due' => __('Past Due'),
                            ])
                            ->required()
                            ->default('trailing'),
                    ])->columns(3),

                Forms\Components\Section::make(__('Dates'))
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label(__('Starts At'))
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label(__('Ends At'))
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label(__('Trial Ends At'))
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('canceled_at')
                            ->label(__('Canceled At'))
                            ->nullable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label(__('Plan'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => __('Active'),
                        'expired' => __('Expired'),
                        'canceled' => __('Canceled'),
                        'trailing' => __('Trailing'),
                        'past_due' => __('Past Due'),
                        default => __($state),
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'trailing' => 'warning',
                        'past_due' => 'danger',
                        'expired', 'canceled' => 'gray',
                        default => 'secondary',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('Starts At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('Ends At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->label(__('Trial Ends At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateHeading(__('No subscriptions found'))
            ->filters([
                Tables\Filters\SelectFilter::make('company')
                    ->label(__('Company'))
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('plan')
                    ->label(__('Plan'))
                    ->relationship('plan', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'active' => __('Active'),
                        'expired' => __('Expired'),
                        'canceled' => __('Canceled'),
                        'trailing' => __('Trailing'),
                        'past_due' => __('Past Due'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'view' => Pages\ViewSubscription::route('/{record}'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
