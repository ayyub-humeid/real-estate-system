<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\User;
use App\Services\ProjectPlanningService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class ProjectMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';
    protected static ?string $title = 'Project Members';

    public function isReadOnly(): bool { return false; }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No project members assigned')
            ->emptyStateDescription('Assign the people responsible for planning and delivering this project.')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Member')->sortable()->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('role')->badge()->color('info')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('started_at')->label('Assigned')->date()->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->label('Ended')->date()->placeholder('Active')->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('assign')->label('Assign Member')->icon('heroicon-o-user-plus')
                    ->visible(fn () => auth()->user()->can('manageMembers', $this->getOwnerRecord()))
                    ->form([
                        Forms\Components\Select::make('user_id')->label('Employee')->options(fn () => User::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                        Forms\Components\TextInput::make('role')->required()->placeholder('e.g. project manager'),
                        Forms\Components\DateTimePicker::make('started_at'),
                        Forms\Components\Textarea::make('notes')->columnSpanFull(),
                    ])->action(function (array $data): void {
                        try {
                            app(ProjectPlanningService::class)->assignMember(auth()->user(), $this->getOwnerRecord(), User::withoutGlobalScopes()->findOrFail($data['user_id']), collect($data)->except('user_id')->all());
                            Notification::make()->success()->title('Project member assigned')->send();
                        } catch (ValidationException $e) { $this->error('Cannot assign member', $e); }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Details')->icon('heroicon-o-eye')->infolist([
                    Infolists\Components\Section::make()->schema([
                        Infolists\Components\TextEntry::make('user.name')->label('Member'),
                        Infolists\Components\TextEntry::make('user.email')->label('Email'),
                        Infolists\Components\TextEntry::make('role')->badge(),
                        Infolists\Components\TextEntry::make('started_at')->label('Assigned')->dateTime(),
                        Infolists\Components\TextEntry::make('ended_at')->label('Ended')->dateTime()->placeholder('Active assignment'),
                        Infolists\Components\TextEntry::make('notes')->columnSpanFull()->placeholder('No notes'),
                    ])->columns(2),
                ]),
                Tables\Actions\Action::make('editAssignment')->label('Edit')->icon('heroicon-o-pencil-square')
                    ->visible(fn ($record) => ! $record->ended_at && auth()->user()->can('update', $record))
                    ->fillForm(fn ($record) => $record->only(['role', 'started_at', 'notes']))
                    ->form([Forms\Components\TextInput::make('role')->required(), Forms\Components\DateTimePicker::make('started_at')->required(), Forms\Components\Textarea::make('notes')->columnSpanFull()])
                    ->action(function ($record, array $data): void {
                        try { app(ProjectPlanningService::class)->updateMember(auth()->user(), $record, $data); Notification::make()->success()->title('Project member updated')->send(); }
                        catch (ValidationException $e) { $this->error('Cannot edit membership', $e); }
                    }),
                Tables\Actions\Action::make('end')->label('End assignment')->icon('heroicon-o-user-minus')->color('danger')
                    ->visible(fn ($record) => ! $record->ended_at && auth()->user()->can('delete', $record))
                    ->requiresConfirmation()->modalDescription('The membership record will be retained as project history.')
                    ->action(function ($record): void {
                        try { app(ProjectPlanningService::class)->endMember(auth()->user(), $record); Notification::make()->success()->title('Project membership ended')->body('The assignment history was retained.')->send(); }
                        catch (ValidationException $e) { $this->error('Cannot end membership', $e); }
                    }),
            ]);
    }

    private function error(string $title, ValidationException $exception): void
    {
        Notification::make()->danger()->title($title)->body(collect($exception->errors())->flatten()->first())->send();
    }
}
