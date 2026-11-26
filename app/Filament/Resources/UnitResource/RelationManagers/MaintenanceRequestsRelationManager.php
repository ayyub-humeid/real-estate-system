<?php

namespace App\Filament\Resources\UnitResource\RelationManagers;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Filament\Support\MaintenanceUnitNotice;
use App\Models\MaintenanceRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceRequests';

    public static function getModelLabel(): string { return __('Maintenance request'); }
    public static function getPluralModelLabel(): string { return __('Maintenance requests'); }

    /** Authorized users may record maintenance requests without leaving View Unit. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Maintenance Requests');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Maintenance Issue'))
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label(__('Title'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label(__('Description'))
                            ->required()
                            ->rows(3),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label(__('Status'))
                                    ->options(MaintenanceStatus::options())
                                    ->default(MaintenanceStatus::New->value)
                                    ->required()
                                    ->helperText(__('maintenance.help.status')),
                                Forms\Components\Select::make('priority')
                                    ->label(__('Priority'))
                                    ->options(MaintenancePriority::options())
                                    ->default(MaintenancePriority::Medium->value)
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('assigned_to_id')
                            ->label(__('Assign Technician'))
                            ->relationship('technician', 'name', fn (Builder $query) => $query
                                ->where('company_id', $this->getOwnerRecord()->company_id))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Textarea::make('internal_notes')
                            ->label(__('Technician Notes'))
                            ->rows(3)
                            ->placeholder(__('Observations and repair notes...')),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('estimated_cost')
                                    ->label(__('Estimated Cost'))
                                    ->numeric()
                                    ->prefix('$'),
                                Forms\Components\TextInput::make('actual_cost')
                                    ->label(__('Actual Cost'))
                                    ->numeric()
                                    ->prefix('$'),
                            ]),

                        Forms\Components\Hidden::make('reported_by_id')
                            ->default(fn () => auth()->id()),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (?string $state): string => MaintenanceStatus::tryFrom((string) $state)?->getColor() ?? 'gray')
                    ->formatStateUsing(fn (?string $state): string => MaintenanceStatus::labelFor($state)),
                Tables\Columns\TextColumn::make('priority')
                    ->label(__('Priority'))
                    ->badge()
                    ->color(fn (?string $state): string => MaintenancePriority::tryFrom((string) $state)?->getColor() ?? 'gray')
                    ->formatStateUsing(fn (?string $state): string => MaintenancePriority::labelFor($state)),
                Tables\Columns\TextColumn::make('actual_cost')
                    ->label(__('Actual Cost'))
                    ->money('USD'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(MaintenanceStatus::options()),
                Tables\Filters\SelectFilter::make('priority')
                    ->label(__('Priority'))
                    ->options(MaintenancePriority::options()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Create maintenance request'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    })
                    ->after(fn (MaintenanceRequest $record) => MaintenanceUnitNotice::send($record)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('View')),
                Tables\Actions\EditAction::make()->label(__('Edit'))
                    ->after(fn (MaintenanceRequest $record) => MaintenanceUnitNotice::send($record)),
                Tables\Actions\DeleteAction::make()->label(__('Delete')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getOwnerRecord()->company_id;

        return $data;
    }
}
