<?php

namespace App\Filament\Resources;

use App\Enums\{CrmActivityOutcome, CrmActivityType, CrmTaskStatus};
use App\Filament\Resources\CrmFollowUpTaskResource\Pages;
use App\Models\{Company, CrmFollowUpTask, Lead, Opportunity, User};
use App\Services\CrmTaskService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class CrmFollowUpTaskResource extends Resource
{
    protected static ?string $model = CrmFollowUpTask::class;
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    public static function getNavigationGroup(): ?string { return '💼 ' . __('crm.navigation.crm'); }
    public static function getNavigationLabel(): string { return __('crm.tasks.navigation'); }
    public static function getModelLabel(): string { return __('crm.tasks.models.task'); }
    public static function getPluralModelLabel(): string { return __('crm.tasks.models.tasks'); }
    public static function form(Forms\Form $form): Forms\Form { return $form->schema(static::formSchema())->columns(2); }
    public static function formSchema(): array
    {
        return [
            Forms\Components\Select::make('company_id')->label(__('crm.fields.company'))->options(Company::withoutGlobalScopes()->pluck('name','id'))->visible(fn()=>auth()->user()->isSuperAdmin())->required(fn()=>auth()->user()->isSuperAdmin())->live(),
            Forms\Components\Select::make('lead_id')->label(__('crm.fields.lead'))->options(fn(Forms\Get $get)=>Lead::withoutGlobalScopes()->where('company_id', static::companyId($get) ?: 0)->pluck('name','id'))->searchable(),
            Forms\Components\Select::make('opportunity_id')->label(__('crm.models.opportunity'))->options(fn(Forms\Get $get)=>Opportunity::withoutGlobalScopes()->where('company_id', static::companyId($get) ?: 0)->with(['lead','listing'])->orderByDesc('updated_at')->get()->mapWithKeys(fn(Opportunity $opportunity)=>[$opportunity->id=>static::opportunityLabel($opportunity)]))->searchable(),
            Forms\Components\Select::make('assigned_to')->label(__('crm.tasks.fields.assigned_to'))->options(fn(Forms\Get $get)=>User::withoutGlobalScopes()->where('company_id', static::companyId($get) ?: 0)->pluck('name','id'))->required()->searchable(),
            Forms\Components\Select::make('type')->label(__('crm.activities.fields.type'))->options(CrmActivityType::options())->required(),
            Forms\Components\TextInput::make('title')->label(__('crm.tasks.fields.title'))->required()->maxLength(255),
            Forms\Components\DateTimePicker::make('due_at')->label(__('crm.tasks.fields.due_at'))->minDate(now()),
            Forms\Components\Textarea::make('description')->label(__('crm.tasks.fields.description'))->columnSpanFull(),
        ];
    }
    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn($query) => auth()->user()->can('view_team_crm_follow_up_tasks') || auth()->user()->can('view_any_crm::follow_up_task') ? $query->with(['assignedTo','lead','opportunity']) : $query->where('assigned_to', auth()->id())->with(['assignedTo','lead','opportunity']))
            ->columns([
                Tables\Columns\TextColumn::make('title')->label(__('crm.tasks.fields.title'))->searchable(),
                Tables\Columns\TextColumn::make('type')->label(__('crm.activities.fields.type'))->badge()->formatStateUsing(fn($state)=>$state instanceof CrmActivityType?$state->getLabel():__('crm.activities.types.'.$state)),
                Tables\Columns\TextColumn::make('status')->label(__('crm.fields.status'))->badge()->formatStateUsing(fn($state)=>$state instanceof CrmTaskStatus?$state->getLabel():__('crm.tasks.statuses.'.$state))->color(fn($state)=>$state instanceof CrmTaskStatus?$state->color():CrmTaskStatus::tryFrom((string)$state)?->color()),
                Tables\Columns\TextColumn::make('assignedTo.name')->label(__('crm.tasks.fields.assigned_to')),
                Tables\Columns\TextColumn::make('due_at')->label(__('crm.tasks.fields.due_at'))->dateTime()->color(fn(CrmFollowUpTask $record)=>$record->is_overdue?'danger':null),
            ])->filters([
                Tables\Filters\SelectFilter::make('status')->label(__('crm.fields.status'))->options(CrmTaskStatus::options()),
                Tables\Filters\SelectFilter::make('type')->label(__('crm.activities.fields.type'))->options(CrmActivityType::options()),
                Tables\Filters\TernaryFilter::make('overdue')->label(__('crm.tasks.fields.overdue'))->queries(
                    true: fn ($query) => $query->whereNotIn('status', [CrmTaskStatus::Completed->value, CrmTaskStatus::Cancelled->value])->where('due_at', '<', now()),
                    false: fn ($query) => $query->where(function ($query): void {
                        $query->whereIn('status', [CrmTaskStatus::Completed->value, CrmTaskStatus::Cancelled->value])
                            ->orWhereNull('due_at')
                            ->orWhere('due_at', '>=', now());
                    }),
                ),
            ])->actions([static::completeAction(), Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()->visible(fn(CrmFollowUpTask $r)=>in_array($r->status,[CrmTaskStatus::Pending,CrmTaskStatus::InProgress],true))]);
    }
    public static function completeAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('complete')->label(__('crm.tasks.actions.complete'))->icon('heroicon-o-check')->tooltip(__('crm.tasks.actions.complete'))
            ->visible(fn(CrmFollowUpTask $r)=>auth()->user()->can('complete',$r) && in_array($r->status,[CrmTaskStatus::Pending,CrmTaskStatus::InProgress],true))
            ->form(fn(CrmFollowUpTask $r)=>[
                Forms\Components\Select::make('outcome')->label(__('crm.activities.fields.outcome'))->options(CrmActivityOutcome::optionsFor($r->type))->required($r->type !== CrmActivityType::Note),
                Forms\Components\Textarea::make('notes')->label(__('crm.activities.fields.notes')),
                Forms\Components\Toggle::make('create_next_follow_up')->label(__('crm.tasks.actions.create_next'))->live(),
                Forms\Components\Select::make('next_type')->label(__('crm.activities.fields.type'))->options(CrmActivityType::options())->visible(fn(Forms\Get $get)=>$get('create_next_follow_up')),
                Forms\Components\TextInput::make('next_title')->label(__('crm.tasks.fields.title'))->visible(fn(Forms\Get $get)=>$get('create_next_follow_up')),
                Forms\Components\DateTimePicker::make('next_due_at')->label(__('crm.tasks.fields.due_at'))->minDate(now())->visible(fn(Forms\Get $get)=>$get('create_next_follow_up')),
            ])->action(function(CrmFollowUpTask $record,array $data):void { try { app(CrmTaskService::class)->complete(auth()->user(),$record,$data); Notification::make()->success()->title(__('crm.tasks.actions.complete'))->send(); } catch(ValidationException $e) { Notification::make()->danger()->title(__('crm.tasks.actions.complete'))->body(collect($e->errors())->flatten()->first())->send(); throw $e;} catch(AuthorizationException){Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send();} });
    }
    public static function companyId(Forms\Get $get): ?int { return auth()->user()?->isSuperAdmin() ? ($get('company_id') ?: null) : auth()->user()?->company_id; }
    public static function opportunityLabel(Opportunity $opportunity): string { return collect([$opportunity->lead?->name, $opportunity->listing?->title])->filter()->implode(' — ') ?: __('crm.models.opportunity') . ' #' . $opportunity->id; }
    public static function getPages(): array { return ['index'=>Pages\ListCrmFollowUpTasks::route('/'),'create'=>Pages\CreateCrmFollowUpTask::route('/create'),'view'=>Pages\ViewCrmFollowUpTask::route('/{record}'),'edit'=>Pages\EditCrmFollowUpTask::route('/{record}/edit')]; }
}
