<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\Property;
use App\Services\ProjectPlanningService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ProjectPropertiesRelationManager extends RelationManager
{
    protected static string $relationship = 'projectProperties';
    protected static ?string $title = 'Project Properties';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No properties attached')
            ->emptyStateDescription('Attach the land or existing property that this project will be planned on.')
            ->columns([
                Tables\Columns\TextColumn::make('property.name')->label('Property')->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('role')->placeholder('—')->badge()->color('info'),
                Tables\Columns\TextColumn::make('attached_at')->label('Attached')->dateTime()->toggleable(),
                Tables\Columns\IconColumn::make('detached_at')->label('Active')->boolean()->getStateUsing(fn($record) => !$record->detached_at),
            ])
            ->headerActions([
                Tables\Actions\Action::make('attach')->label('Attach Property')->icon('heroicon-o-plus')
                    ->visible(fn() => auth()->user()->can('attachProperty', $this->getOwnerRecord()))
                    ->form([
                        Forms\Components\Select::make('property_id')->label('Property')->options(fn() => Property::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                        Forms\Components\TextInput::make('role')->placeholder('e.g. primary land'),
                        Forms\Components\Textarea::make('notes')->columnSpanFull(),
                    ])->action(function (array $data): void {
                        try {
                            app(ProjectPlanningService::class)->attachProperty(auth()->user(), $this->getOwnerRecord(), Property::withoutGlobalScopes()->findOrFail($data['property_id']), collect($data)->except('property_id')->all());
                            Notification::make()->success()->title('Property attached')->body('It is now available for this project.')->send();
                        } catch (ValidationException $e) {
                            $this->error('Cannot attach property', $e);
                        } catch (AuthorizationException) {
                            Notification::make()->danger()->title('Unauthorized')->body('You do not have permission to attach a property.')->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Details')->icon('heroicon-o-eye')->infolist([
                    Infolists\Components\Section::make()->schema([
                        Infolists\Components\TextEntry::make('property.name')->label('Property'),
                        Infolists\Components\TextEntry::make('role')->placeholder('Not specified')->badge(),
                        Infolists\Components\TextEntry::make('attached_at')->label('Attached')->dateTime(),
                        Infolists\Components\TextEntry::make('detached_at')->label('Detached')->dateTime()->placeholder('Active link'),
                        Infolists\Components\TextEntry::make('notes')->columnSpanFull()->placeholder('No notes'),
                    ])->columns(2),
                ]),
                Tables\Actions\Action::make('editLink')->label('Edit')->icon('heroicon-o-pencil-square')
                    ->visible(fn($record) => !$record->detached_at && auth()->user()->can('update', $record))
                    ->fillForm(fn($record) => $record->only(['role', 'notes']))
                    ->form([Forms\Components\TextInput::make('role'), Forms\Components\Textarea::make('notes')->columnSpanFull()])
                    ->action(function ($record, array $data): void {
                        try {
                            app(ProjectPlanningService::class)->updateProjectProperty(auth()->user(), $record, $data);
                            Notification::make()->success()->title('Property link updated')->send();
                        } catch (ValidationException $e) {
                            $this->error('Cannot edit property link', $e);
                        }
                    }),
                Tables\Actions\Action::make('detach')->label('Detach')->icon('heroicon-o-link-slash')->color('danger')
                    ->visible(fn($record) => !$record->detached_at && auth()->user()->can('delete', $record))
                    ->requiresConfirmation()->modalDescription('The attachment history is retained, but this property will no longer count as active for this project.')
                    ->action(function ($record): void {
                        try {
                            app(ProjectPlanningService::class)->detachProperty(auth()->user(), $record);
                            Notification::make()->success()->title('Property detached')->body('The attachment history was retained.')->send();
                        } catch (ValidationException $e) {
                            $this->error('Cannot detach property', $e);
                        }
                    }),
            ]);
    }

    private function error(string $title, ValidationException $exception): void
    {
        Notification::make()->danger()->title($title)->body(collect($exception->errors())->flatten()->first())->send();
    }
}
