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
            ->label('Company')
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
                            Forms\Components\Section::make('Issue Details')
                                ->schema([
                                    self::companyField(),
                                    
                                    Forms\Components\Select::make('unit_id')
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
                                        ->required()
                                        ->maxLength(255),

                                    Forms\Components\Textarea::make('description')
                                        ->required()
                                        ->rows(5)
                                        ->columnSpanFull(),
                                ])->columns(1),

                            Forms\Components\Section::make('Technician Notes')
                                ->description('Internal details and findings')
                                ->schema([
                                    Forms\Components\Textarea::make('internal_notes')
                                        ->label('Repair Progress / Notes')
                                        ->rows(4)
                                        ->placeholder('Notes for internal use or technician findings...')
                                        ->columnSpanFull(),
                                ]),

                            Forms\Components\Section::make('Evidence')
                                ->description('Upload photos of the issue')
                                ->schema([
                                    Forms\Components\Repeater::make('images')
                                        ->relationship('images')
                                        ->schema([
                                            Forms\Components\FileUpload::make('path')
                                                ->label('Photo')
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
                            Forms\Components\Section::make('Status & Assignment')
                                ->schema([
                                    Forms\Components\Select::make('status')
                                        ->options([
                                            'new' => 'New',
                                            'pending' => 'Pending',
                                            'in_progress' => 'In Progress',
                                            'resolved' => 'Resolved',
                                            'cancelled' => 'Cancelled',
                                        ])
                                        ->default('new')
                                        ->required()
                                        ->native(false),

                                    Forms\Components\Select::make('priority')
                                        ->options([
                                            'low' => 'Low',
                                            'medium' => 'Medium',
                                            'high' => 'High',
                                            'emergency' => 'Emergency',
                                        ])
                                        ->default('medium')
                                        ->required()
                                        ->native(false),

                                    Forms\Components\Select::make('assigned_to_id')
                                        ->label('Assigned Technician')
                                        ->relationship('technician', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select a user...'),

                                    Forms\Components\Hidden::make('reported_by_id')
                                        ->default(fn () => auth()->id()),
                                ]),

                            Forms\Components\Section::make('Financials')
                                ->schema([
                                    Forms\Components\TextInput::make('estimated_cost')
                                        ->numeric()
                                        ->prefix('$'),
                                    Forms\Components\TextInput::make('actual_cost')
                                        ->numeric()
                                        ->prefix('$'),
                                ]),

                            Forms\Components\Section::make('Scheduling')
                                ->schema([
                                    Forms\Components\DateTimePicker::make('scheduled_at'),
                                    Forms\Components\DateTimePicker::make('completed_at'),
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
                    ->label('Property')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.unit_number')
                    ->label('Unit #')
                    ->sortable()
                    ->searchable()
                    ->badge(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('technician.name')
                    ->label('Assigned To')
                    ->sortable()
                    ->placeholder('Unassigned')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'new',
                        'warning' => 'pending',
                        'info' => 'in_progress',
                        'success' => 'resolved',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->colors([
                        'gray' => 'low',
                        'info' => 'medium',
                        'warning' => 'high',
                        'danger' => 'emergency',
                    ]),
                Tables\Columns\TextColumn::make('actual_cost')
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
                    ->options([
                        'new' => 'New',
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'emergency' => 'Emergency',
                    ]),
                Tables\Filters\Filter::make('open_only')
                    ->label('Open Only')
                    ->query(fn (Builder $query): Builder => $query->open())
                    ->toggle(),
                Tables\Filters\Filter::make('my_tasks')
                    ->label('My Tasks')
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
