<?php

namespace App\Filament\Resources\UnitResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MaintenanceRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceRequests';

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
                                    ->options([
                                        'new' => __('New'),
                                        'pending' => __('Pending'),
                                        'in_progress' => __('In Progress'),
                                        'resolved' => __('Resolved'),
                                        'cancelled' => __('Cancelled'),
                                    ])
                                    ->default('new')
                                    ->required(),
                                Forms\Components\Select::make('priority')
                                    ->label(__('Priority'))
                                    ->options([
                                        'low' => __('Low'),
                                        'medium' => __('Medium'),
                                        'high' => __('High'),
                                        'emergency' => __('Emergency'),
                                    ])
                                    ->default('medium')
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('assigned_to_id')
                            ->label(__('Assign Technician'))
                            ->relationship('technician', 'name')
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
                    ->colors([
                        'gray' => 'new',
                        'warning' => 'pending',
                        'info' => 'in_progress',
                        'success' => 'resolved',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'new' => __('New'),
                        'pending' => __('Pending'),
                        'in_progress' => __('In Progress'),
                        'resolved' => __('Resolved'),
                        'cancelled' => __('Cancelled'),
                        default => $state ?? '—',
                    }),
                Tables\Columns\TextColumn::make('priority')
                    ->label(__('Priority'))
                    ->badge()
                    ->colors([
                        'gray' => 'low',
                        'info' => 'medium',
                        'warning' => 'high',
                        'danger' => 'emergency',
                    ])
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'low' => __('Low'),
                        'medium' => __('Medium'),
                        'high' => __('High'),
                        'emergency' => __('Emergency'),
                        default => $state ?? '—',
                    }),
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
                    ->options([
                        'new' => __('New'),
                        'pending' => __('Pending'),
                        'in_progress' => __('In Progress'),
                        'resolved' => __('Resolved'),
                        'cancelled' => __('Cancelled'),
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->label(__('Priority'))
                    ->options([
                        'low' => __('Low'),
                        'medium' => __('Medium'),
                        'high' => __('High'),
                        'emergency' => __('Emergency'),
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    }),
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getOwnerRecord()->company_id;

        return $data;
    }
}
