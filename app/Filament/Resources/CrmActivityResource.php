<?php

namespace App\Filament\Resources;

use App\Enums\{CrmActivityOutcome, CrmActivityType};
use App\Filament\Resources\CrmActivityResource\Pages;
use App\Models\{Company, CrmActivity, Lead, Opportunity};
use App\Services\CrmTaskService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CrmActivityResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = CrmActivity::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    public static function getNavigationGroup(): ?string { return '💼 ' . __('crm.navigation.crm'); }
    public static function getNavigationLabel(): string { return __('crm.activities.navigation'); }
    public static function getModelLabel(): string { return __('crm.activities.models.activity'); }
    public static function getPluralModelLabel(): string { return __('crm.activities.models.activities'); }
    public static function form(Forms\Form $form): Forms\Form { return $form->schema([
        Forms\Components\Select::make('company_id')->label(__('crm.fields.company'))->options(Company::withoutGlobalScopes()->pluck('name','id'))->visible(fn()=>auth()->user()->isSuperAdmin())->required(fn()=>auth()->user()->isSuperAdmin())->live(),
        Forms\Components\Select::make('lead_id')->label(__('crm.fields.lead'))->options(fn(Forms\Get $get)=>Lead::withoutGlobalScopes()->where('company_id',static::companyId($get) ?: 0)->pluck('name','id'))->searchable(),
        Forms\Components\Select::make('opportunity_id')->label(__('crm.models.opportunity'))->options(fn(Forms\Get $get)=>Opportunity::withoutGlobalScopes()->where('company_id',static::companyId($get) ?: 0)->with(['lead','listing'])->orderByDesc('updated_at')->get()->mapWithKeys(fn(Opportunity $opportunity)=>[$opportunity->id=>static::opportunityLabel($opportunity)]))->searchable(),
        Forms\Components\Select::make('type')->label(__('crm.activities.fields.type'))->options(CrmActivityType::options())->required()->live(),
        Forms\Components\Select::make('outcome')->label(__('crm.activities.fields.outcome'))->options(fn(Forms\Get $get)=>(($type=CrmActivityType::tryFrom((string)$get('type')))?CrmActivityOutcome::optionsFor($type):[])),
        Forms\Components\DateTimePicker::make('occurred_at')->label(__('crm.activities.fields.occurred_at'))->required()->default(now()),
        Forms\Components\Textarea::make('notes')->label(__('crm.activities.fields.notes'))->columnSpanFull(),
    ])->columns(2); }
    public static function table(Table $table): Table { return $table->modifyQueryUsing(fn($query)=>$query->with(['lead','opportunity','performedBy']))->columns([
        Tables\Columns\TextColumn::make('type')->label(__('crm.activities.fields.type'))->badge()->formatStateUsing(fn($state)=>$state instanceof CrmActivityType?$state->getLabel():__('crm.activities.types.'.$state)),
        Tables\Columns\TextColumn::make('outcome')->label(__('crm.activities.fields.outcome'))->badge()->formatStateUsing(fn($state)=>$state instanceof CrmActivityOutcome?$state->getLabel():($state?__('crm.activities.outcomes.'.$state):'—')),
        Tables\Columns\TextColumn::make('lead.name')->label(__('crm.fields.lead'))->placeholder(__('—')),
        Tables\Columns\TextColumn::make('performedBy.name')->label(__('crm.activities.fields.performed_by')),
        Tables\Columns\TextColumn::make('occurred_at')->label(__('crm.activities.fields.occurred_at'))->dateTime(),
    ])->actions([Tables\Actions\ViewAction::make()]); }
    public static function companyId(Forms\Get $get): ?int { return auth()->user()?->isSuperAdmin()?($get('company_id')?:null):auth()->user()?->company_id; }
    public static function opportunityLabel(Opportunity $opportunity): string { return collect([$opportunity->lead?->name, $opportunity->listing?->title])->filter()->implode(' — ') ?: __('crm.models.opportunity') . ' #' . $opportunity->id; }
    public static function getPages():array{return ['index'=>Pages\ListCrmActivities::route('/'),'create'=>Pages\CreateCrmActivity::route('/create'),'view'=>Pages\ViewCrmActivity::route('/{record}')];}
}
