<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceRequestResource\Pages;
use App\Filament\Resources\MaintenanceRequestResource\RelationManagers;
use App\Models\MaintenanceRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MaintenanceRequestResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = MaintenanceRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = '💼 Operations';
    protected static ?int $navigationSort = 3;
public static function shouldRegisterNavigation(): bool
{
    $user = auth()->user();
    if ($user->isSuperAdmin()) return true;
    return $user->company->hasFeature('maintenance_tracking');
}

public static function canViewAny(): bool
{
    $user = auth()->user();
    if ($user->isSuperAdmin()) return true;
    
    // Check BOTH feature AND Shield permission
    return $user->company->hasFeature('maintenance_tracking') 
        && $user->can('view_any_maintenance::request');
}

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['unit.property', 'reporter', 'company', 'technician']);
    }

    /**
     * Reusable company field logic
     */
    private static function companyField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('company_id')
            ->label(__('Company'))
            ->relationship('company', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->visible(fn () => auth()->user()->isSuperAdmin());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Group::make([
                            Forms\Components\Section::make(__('Issue Details'))
                                ->schema([
                                    self::companyField(),
                                    
                                    Forms\Components\Select::make('unit_id')
                                        ->label(__('Unit'))
                                        ->relationship('unit', 'unit_number', function (Builder $query, Forms\Get $get) {
                                            $query->with('property');
                                            if ($get('company_id')) {
                                                $query->where('company_id', $get('company_id'));
                                            }
                                        })
                                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->property->name} - Unit {$record->unit_number}")
                                        ->searchable()
                                        ->preload()
                                        ->required(),

                                    Forms\Components\TextInput::make('title')
                                        ->label(__('Title'))
                                        ->required()
                                        ->maxLength(255),

                                    Forms\Components\Textarea::make('description')
                                        ->label(__('Description'))
                                        ->required()
                                        ->rows(5)
                                        ->columnSpanFull(),
                                ])->columns(1),

                            Forms\Components\Section::make(__('Technician Notes'))
                                ->description(__('Internal details and findings'))
                                ->schema([
                                    Forms\Components\Textarea::make('internal_notes')
                                        ->label(__('Repair Progress / Notes'))
                                        ->rows(4)
                                        ->placeholder(__('Notes for internal use or technician findings...'))
                                        ->columnSpanFull(),
                                ]),

                            Forms\Components\Section::make(__('Evidence'))
                                ->description(__('Upload photos of the issue'))
                                ->schema([
                                        Forms\Components\Repeater::make('images')
                                            ->label(__('Images'))
                                        ->relationship('images')
                                        ->schema([
                                            Forms\Components\FileUpload::make('path')
                                                ->label(__('Photo'))
                                                ->image()
                                                ->directory('maintenance/images')
                                                ->required(),
                                            Forms\Components\TextInput::make('order')
                                                ->numeric()
                                                ->default(0),
                                        ])
                                        ->grid(2)
                                        ->collapsible()
                                        ->columnSpanFull(),
                                ]),
                        ])->columnSpan(2),

                        Forms\Components\Group::make([
                            Forms\Components\Section::make(__('Status & Assignment'))
                                ->schema([
                                    Forms\Components\Select::make('status')
                                        ->label(__('Status'))
                                        ->options(static::statusOptions())
                                        ->default('new')
                                        ->required()
                                        ->native(false),

                                    Forms\Components\Select::make('priority')
                                        ->label(__('Priority'))
                                        ->options(static::priorityOptions())
                                        ->default('medium')
                                        ->required()
                                        ->native(false),

                                    Forms\Components\Select::make('assigned_to_id')
                                        ->label(__('Assigned Technician'))
                                        ->relationship('technician', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->placeholder(__('Select a user...')),

                                    Forms\Components\Hidden::make('reported_by_id')
                                        ->default(fn () => auth()->id()),
                                ]),

                            Forms\Components\Section::make(__('Financials'))
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

                            Forms\Components\Section::make(__('Scheduling'))
                                ->schema([
                                    Forms\Components\DateTimePicker::make('scheduled_at')
                                        ->label(__('Scheduled at')),
                                    Forms\Components\DateTimePicker::make('completed_at')
                                        ->label(__('Completed at')),
                                ]),
                        ])->columnSpan(1),
                    ])
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('unit.property.name')
                    ->label(__('Property'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.unit_number')
                    ->label(__('Unit #'))
                    ->sortable()
                    ->searchable()
                    ->badge(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('technician.name')
                    ->label(__('Assigned To'))
                    ->sortable()
                    ->placeholder(__('Unassigned'))
                    ->badge()
                    ->color('info'),
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
                    ->formatStateUsing(fn (?string $state) => $state ? __('maintenance.statuses.'.$state) : __('—')),
                Tables\Columns\TextColumn::make('priority')
                    ->label(__('Priority'))
                    ->badge()
                    ->colors([
                        'gray' => 'low',
                        'info' => 'medium',
                        'warning' => 'high',
                        'danger' => 'emergency',
                    ])
                    ->formatStateUsing(fn (?string $state) => $state ? __('maintenance.priorities.'.$state) : __('—')),
                Tables\Columns\TextColumn::make('actual_cost')
                    ->label(__('Actual Cost'))
                    ->money('USD')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money()),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(static::statusOptions()),
                Tables\Filters\SelectFilter::make('priority')
                    ->label(__('Priority'))
                    ->options(static::priorityOptions()),
                Tables\Filters\Filter::make('open_only')
                    ->label(__('Open Only'))
                    ->query(fn (Builder $query): Builder => $query->open())
                    ->toggle(),
                Tables\Filters\Filter::make('my_tasks')
                    ->label(__('My Tasks'))
                    ->query(fn (Builder $query): Builder => $query->byTechnician(auth()->id()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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

    private static function statusOptions(): array
    {
        return collect(['new', 'pending', 'in_progress', 'resolved', 'cancelled'])
            ->mapWithKeys(fn (string $status) => [$status => __('maintenance.statuses.'.$status)])
            ->all();
    }

    private static function priorityOptions(): array
    {
        return collect(['low', 'medium', 'high', 'emergency'])
            ->mapWithKeys(fn (string $priority) => [$priority => __('maintenance.priorities.'.$priority)])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenanceRequests::route('/'),
            'create' => Pages\CreateMaintenanceRequest::route('/create'),
            'view' => Pages\ViewMaintenanceRequest::route('/{record}'),
            'edit' => Pages\EditMaintenanceRequest::route('/{record}/edit'),
        ];
    }
}
