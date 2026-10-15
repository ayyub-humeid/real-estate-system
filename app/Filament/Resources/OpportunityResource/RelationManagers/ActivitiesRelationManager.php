<?php

namespace App\Filament\Resources\OpportunityResource\RelationManagers;

use App\Enums\{CrmActivityOutcome, CrmActivityType};
use App\Models\{CrmActivity, Lead, Opportunity};
use App\Services\CrmTaskService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Validation\ValidationException;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string { return __('crm.activities.models.activities'); }
    public function isReadOnly(): bool { return false; }
    public function form(Forms\Form $form): Forms\Form { return $form->schema([]); }
    public function table(Tables\Table $table): Tables\Table { return $table->columns([
        Tables\Columns\TextColumn::make('type')->label(__('crm.activities.fields.type'))->badge()->formatStateUsing(fn($state)=>$state?->getLabel() ?? __('crm.activities.types.'.$state)),
        Tables\Columns\TextColumn::make('outcome')->label(__('crm.activities.fields.outcome'))->badge()->formatStateUsing(fn($state)=>$state?->getLabel() ?? __('crm.activities.outcomes.'.$state)),
        Tables\Columns\TextColumn::make('performedBy.name')->label(__('crm.activities.fields.performed_by')),
        Tables\Columns\TextColumn::make('occurred_at')->label(__('crm.activities.fields.occurred_at'))->dateTime(),
    ])->headerActions([Tables\Actions\Action::make('log_activity')->label(__('crm.tasks.actions.log_activity'))->icon('heroicon-o-plus')->tooltip(__('crm.tasks.actions.log_activity'))->form([
        Forms\Components\Select::make('type')->label(__('crm.activities.fields.type'))->options(CrmActivityType::options())->required()->live(),
        Forms\Components\Select::make('outcome')->label(__('crm.activities.fields.outcome'))->options(fn(Forms\Get $get)=>(($type=CrmActivityType::tryFrom((string)$get('type')))?CrmActivityOutcome::optionsFor($type):[])),
        Forms\Components\Textarea::make('notes')->label(__('crm.activities.fields.notes')),
    ])->action(function(array $data):void { try { $owner=$this->getOwnerRecord(); app(CrmTaskService::class)->logActivity(auth()->user(), [...$data,'company_id'=>$owner->company_id,'opportunity_id'=>$owner instanceof Opportunity ? $owner->id : null,'lead_id'=>$owner instanceof Opportunity ? $owner->lead_id : ($owner instanceof Lead ? $owner->id : null)]); Notification::make()->success()->title(__('crm.tasks.actions.log_activity'))->send(); } catch(ValidationException $e){Notification::make()->danger()->title(__('crm.tasks.actions.log_activity'))->body(collect($e->errors())->flatten()->first())->send();throw $e;} })]); }
    protected function mutateFormDataBeforeCreate(array $data): array { $data['company_id']=$this->getOwnerRecord()->company_id; return $data; }
}
