<?php

namespace App\Filament\Resources\LeadResource\RelationManagers;

use App\Filament\Resources\CrmFollowUpTaskResource;
use App\Services\CrmTaskService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class FollowUpTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'followUpTasks';
    protected static ?string $title = 'Follow-up tasks';
    public function isReadOnly(): bool { return false; }
    public function form(Forms\Form $form): Forms\Form { return $form->schema(CrmFollowUpTaskResource::formSchema()); }
    public function table(Tables\Table $table): Tables\Table { return $table->columns([
        Tables\Columns\TextColumn::make('title')->label(__('crm.tasks.fields.title')),
        Tables\Columns\TextColumn::make('status')->label(__('crm.fields.status'))->badge()->formatStateUsing(fn($state)=>$state?->getLabel() ?? __('crm.tasks.statuses.'.$state))->color(fn($state)=>$state instanceof \App\Enums\CrmTaskStatus?$state->color():\App\Enums\CrmTaskStatus::tryFrom((string)$state)?->color()),
        Tables\Columns\TextColumn::make('due_at')->label(__('crm.tasks.fields.due_at'))->dateTime(),
    ])->headerActions([Tables\Actions\CreateAction::make()->label(__('crm.tasks.actions.add_follow_up'))->mutateFormDataUsing(function(array $data):array{$data['company_id']=$this->getOwnerRecord()->company_id;$data['lead_id']=$this->getOwnerRecord()->id;return $data;})->using(fn(array $data)=>app(CrmTaskService::class)->create(auth()->user(),$data))])->actions([CrmFollowUpTaskResource::completeAction(),Tables\Actions\ViewAction::make(),Tables\Actions\EditAction::make()->using(fn(\App\Models\CrmFollowUpTask $record,array $data)=>app(CrmTaskService::class)->update(auth()->user(),$record,$data))]); }
    protected function mutateFormDataBeforeCreate(array $data):array{$data['company_id']=$this->getOwnerRecord()->company_id;$data['lead_id']=$this->getOwnerRecord()->id;return $data;}
}
