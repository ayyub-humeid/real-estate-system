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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Project Members');
    }

    public function isReadOnly(): bool { return false; }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('No project members assigned'))
            ->emptyStateDescription(__('Assign the people responsible for planning and delivering this project.'))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label(__('Member'))->sortable()->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('role')->label(__('Role'))->badge()->color('info')->sortable()->searchable()
                    ->formatStateUsing(fn (?string $state): string => $this->roleLabel($state)),
                Tables\Columns\TextColumn::make('started_at')->label(__('Assigned'))->date()->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->label(__('Ended'))->date()->placeholder(__('Active'))->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('assign')->label(__('Assign Member'))->icon('heroicon-o-user-plus')
                    ->visible(fn () => auth()->user()->can('manageMembers', $this->getOwnerRecord()))
                    ->form([
                        Forms\Components\Select::make('user_id')->label(__('Employee'))->options(fn () => User::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                        Forms\Components\TextInput::make('role')->label(__('Role'))->required()->placeholder(__('project.member_roles.project_manager')),
                        Forms\Components\DateTimePicker::make('started_at')->label(__('Started at')),
                        Forms\Components\Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
                    ])->action(function (array $data): void {
                        try {
                            app(ProjectPlanningService::class)->assignMember(auth()->user(), $this->getOwnerRecord(), User::withoutGlobalScopes()->findOrFail($data['user_id']), collect($data)->except('user_id')->all());
                            Notification::make()->success()->title(__('Project member assigned'))->send();
                        } catch (ValidationException $e) { $this->error(__('Cannot assign member'), $e); }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('Details'))->icon('heroicon-o-eye')->infolist([
                    Infolists\Components\Section::make()->schema([
                        Infolists\Components\TextEntry::make('user.name')->label(__('Member')),
                        Infolists\Components\TextEntry::make('user.email')->label(__('Email')),
                        Infolists\Components\TextEntry::make('role')->label(__('Role'))->badge()
                            ->formatStateUsing(fn (?string $state): string => $this->roleLabel($state)),
                        Infolists\Components\TextEntry::make('started_at')->label(__('Assigned'))->dateTime(),
                        Infolists\Components\TextEntry::make('ended_at')->label(__('Ended'))->dateTime()->placeholder(__('Active assignment')),
                        Infolists\Components\TextEntry::make('notes')->label(__('Notes'))->columnSpanFull()->placeholder(__('No notes')),
                    ])->columns(2),
                ]),
                Tables\Actions\Action::make('editAssignment')->label(__('Edit'))->icon('heroicon-o-pencil-square')
                    ->visible(fn ($record) => ! $record->ended_at && auth()->user()->can('update', $record))
                    ->fillForm(fn ($record) => $record->only(['role', 'started_at', 'notes']))
                    ->form([Forms\Components\TextInput::make('role')->label(__('Role'))->required(), Forms\Components\DateTimePicker::make('started_at')->label(__('Started at'))->required(), Forms\Components\Textarea::make('notes')->label(__('Notes'))->columnSpanFull()])
                    ->action(function ($record, array $data): void {
                        try { app(ProjectPlanningService::class)->updateMember(auth()->user(), $record, $data); Notification::make()->success()->title(__('Project member updated'))->send(); }
                        catch (ValidationException $e) { $this->error(__('Cannot edit membership'), $e); }
                    }),
                Tables\Actions\Action::make('end')->label(__('End assignment'))->icon('heroicon-o-user-minus')->color('danger')
                    ->visible(fn ($record) => ! $record->ended_at && auth()->user()->can('delete', $record))
                    ->requiresConfirmation()->modalDescription(__('The membership record will be retained as project history.'))
                    ->action(function ($record): void {
                        try { app(ProjectPlanningService::class)->endMember(auth()->user(), $record); Notification::make()->success()->title(__('Project membership ended'))->body(__('The assignment history was retained.'))->send(); }
                        catch (ValidationException $e) { $this->error(__('Cannot end membership'), $e); }
                    }),
            ]);
    }

    private function error(string $title, ValidationException $exception): void
    {
        Notification::make()->danger()->title($title)->body(collect($exception->errors())->flatten()->first())->send();
    }

    /** Keeps historical/custom role text intact while localizing known company roles. */
    private function roleLabel(?string $role): string
    {
        if (blank($role)) {
            return __('—');
        }

        $key = Str::of($role)->lower()->replace([' ', '-'], '_')->toString();
        $translationKey = 'project.member_roles.'.$key;
        $translated = __($translationKey);

        return $translated === $translationKey ? $role : $translated;
    }
}
