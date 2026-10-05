<?php

namespace App\Filament\Resources\ProjectDesignPackageResource\RelationManagers;

use App\Models\{DesignPackageScopeItem, ProjectDesignPackage};
use App\Services\DesignEngineeringService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ScopeItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'scopeItems';
    protected static ?string $title = 'Scope Items';

    public function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('title')->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('code')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'gray' => 'not_started', 'info' => 'in_progress', 'success' => 'ready', 'danger' => 'cancelled',
                ]),
                Tables\Columns\TextColumn::make('target_date')->date()->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add scope item')->icon('heroicon-o-plus')
                    ->tooltip('Add a required design scope item')
                    ->form([
                        Forms\Components\TextInput::make('title')->required(),
                        Forms\Components\TextInput::make('code'),
                        Forms\Components\DatePicker::make('target_date'),
                        Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                        Forms\Components\Textarea::make('description')->columnSpanFull(),
                    ])
                    ->using(function (array $data, ProjectDesignPackage $ownerRecord): DesignPackageScopeItem {
                        $assignment = $ownerRecord->activeAssignment;
                        abort_unless($assignment, 422, 'Assign an engineering office before adding scope items.');
                        return app(DesignEngineeringService::class)->addScopeItem(auth()->user(), $assignment, $data);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('updateStatus')
                    ->label('Update status')->icon('heroicon-o-arrow-path')->tooltip('Update scope item status')
                    ->form([Forms\Components\Select::make('status')->options([
                        'in_progress' => 'Start work', 'ready' => 'Mark ready', 'cancelled' => 'Cancel',
                    ])->required()])
                    ->action(fn (DesignPackageScopeItem $record, array $data) => app(DesignEngineeringService::class)
                        ->transitionScopeItem(auth()->user(), $record, $data['status'])),
                Tables\Actions\ViewAction::make()->tooltip('View scope item details'),
            ]);
    }
}
