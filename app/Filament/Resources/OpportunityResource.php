<?php

namespace App\Filament\Resources;

use App\Enums\{CrmInquirySource, OpportunityType};
use App\Filament\Resources\OpportunityResource\{Pages, RelationManagers};
use App\Models\{Company, CrmPipeline, CrmPipelineStage, Lead, Listing, Opportunity, User};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OpportunityResource extends Resource
{
    protected static ?string $model = Opportunity::class;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationGroup = '💼 CRM';

    public static function getNavigationLabel(): string
    {
        return __('crm.navigation.opportunities');
    }

    public static function getNavigationGroup(): ?string
    {
        return '💼 ' . __('crm.navigation.crm');
    }

    public static function getModelLabel(): string
    {
        return __('crm.models.opportunity');
    }

    public static function getPluralModelLabel(): string
    {
        return __('crm.models.opportunities');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('company_id')
                    ->label(__('crm.fields.company'))
                    ->options(Company::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->visible(fn () => auth()->user()->isSuperAdmin()),
                Forms\Components\Select::make('lead_id')
                    ->label(__('crm.fields.lead'))
                    ->options(fn (Forms\Get $get) => Lead::withoutGlobalScopes()
                        ->when(static::companyId($get), fn ($query, $companyId) => $query->where('company_id', $companyId), fn ($query) => $query->whereRaw('1 = 0'))
                        ->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('listing_id')
                    ->label(__('crm.fields.listing'))
                    ->options(fn (Forms\Get $get) => Listing::withoutGlobalScopes()
                        ->when(static::companyId($get), fn ($query, $companyId) => $query->where('company_id', $companyId), fn ($query) => $query->whereRaw('1 = 0'))
                        ->orderBy('title')->pluck('title', 'id'))
                    ->searchable(),
                Forms\Components\Select::make('type')
                    ->label(__('crm.fields.type'))
                    ->options(OpportunityType::options())
                    ->required(),
                Forms\Components\Select::make('assigned_to')
                    ->label(__('crm.fields.owner'))
                    ->options(fn (Forms\Get $get) => User::withoutGlobalScopes()
                        ->when(static::companyId($get), fn ($query, $companyId) => $query->where('company_id', $companyId), fn ($query) => $query->whereRaw('1 = 0'))
                        ->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                Forms\Components\Select::make('pipeline_id')
                    ->label(__('crm.fields.pipeline'))
                    ->options(fn (Forms\Get $get) => CrmPipeline::withoutGlobalScopes()
                        ->when(static::companyId($get), fn ($query, $companyId) => $query->where('company_id', $companyId), fn ($query) => $query->whereRaw('1 = 0'))
                        ->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get()
                        ->mapWithKeys(fn (CrmPipeline $pipeline) => [$pipeline->id => $pipeline->display_name]))
                    ->required()
                    ->live()
                    ->disabled(fn (?Opportunity $record) => $record !== null),
                Forms\Components\Select::make('pipeline_stage_id')
                    ->label(__('crm.fields.stage'))
                    ->options(fn (Forms\Get $get) => CrmPipelineStage::withoutGlobalScopes()
                        ->when(static::companyId($get), fn ($query, $companyId) => $query->where('company_id', $companyId), fn ($query) => $query->whereRaw('1 = 0'))
                        ->where('pipeline_id', $get('pipeline_id'))
                        ->where('is_active', true)
                        ->orderBy('position')->get()
                        ->mapWithKeys(fn (CrmPipelineStage $stage) => [$stage->id => $stage->display_label]))
                    ->required()
                    ->disabled(fn (?Opportunity $record) => $record !== null),
                Forms\Components\TextInput::make('budget_min')->label(__('crm.fields.budget_min'))->numeric()->minValue(0)->lte('budget_max'),
                Forms\Components\TextInput::make('budget_max')->label(__('crm.fields.budget_max'))->numeric()->minValue(0)->gte('budget_min'),
                Forms\Components\TextInput::make('currency')->label(__('crm.fields.currency'))->length(3),
                Forms\Components\TextInput::make('preferred_property_type')->label(__('crm.fields.preferred_property_type')),
                Forms\Components\TextInput::make('preferred_location')->label(__('crm.fields.preferred_location')),
                Forms\Components\TextInput::make('preferred_bedrooms')->label(__('crm.fields.preferred_bedrooms'))->numeric()->integer()->minValue(0),
                Forms\Components\DatePicker::make('expected_close_date')->label(__('crm.fields.expected_close_date')),
                Forms\Components\Textarea::make('notes')->label(__('crm.fields.notes'))->columnSpanFull(),
                Forms\Components\Select::make('source')->label(__('crm.fields.source'))->options(CrmInquirySource::options())->default(CrmInquirySource::Manual->value)->required(),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lead.name')->label(__('crm.fields.lead'))->searchable(),
                Tables\Columns\TextColumn::make('listing.title')->label(__('crm.fields.listing'))->placeholder('—'),
                Tables\Columns\TextColumn::make('type')->label(__('crm.fields.type'))->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof OpportunityType ? $state->getLabel() : __('crm.opportunity_types.' . $state)),
                Tables\Columns\TextColumn::make('stage.label')->label(__('crm.fields.stage'))->badge()
                    ->formatStateUsing(fn ($state, Opportunity $record) => $record->stage?->display_label ?? $state),
                Tables\Columns\TextColumn::make('assignedTo.name')->label(__('crm.fields.owner'))->placeholder('—'),
                Tables\Columns\TextColumn::make('source')->label(__('crm.fields.source'))->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof CrmInquirySource ? $state->getLabel() : __('crm.sources.' . $state)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function companyId(Forms\Get $get): ?int
    {
        $companyId = auth()->user()?->isSuperAdmin() ? $get('company_id') : auth()->user()?->company_id;

        return filled($companyId) ? (int) $companyId : null;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RequirementCompletionsRelationManager::class,
            RelationManagers\StageHistoriesRelationManager::class,
            RelationManagers\FollowUpTasksRelationManager::class,
            RelationManagers\ActivitiesRelationManager::class,
            RelationManagers\OffersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpportunities::route('/'),
            'create' => Pages\CreateOpportunity::route('/create'),
            'view' => Pages\ViewOpportunity::route('/{record}'),
            'edit' => Pages\EditOpportunity::route('/{record}/edit'),
        ];
    }
}
