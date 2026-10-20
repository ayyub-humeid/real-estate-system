<?php

namespace App\Filament\Resources\LeadResource\RelationManagers;

use App\Enums\OpportunityType;
use App\Models\{CrmPipeline, CrmPipelineStage, Opportunity, User};
use App\Services\OpportunityService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class OpportunitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'opportunities';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('crm.navigation.opportunities');
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('listing_id')
                ->label(__('crm.fields.listing'))
                ->options(fn () => \App\Models\Listing::withoutGlobalScopes()
                    ->where('company_id', $this->getOwnerRecord()->company_id)
                    ->orderBy('title')->pluck('title', 'id'))
                ->searchable(),
            Forms\Components\Select::make('type')->label(__('crm.fields.type'))->options(OpportunityType::options())->required(),
            Forms\Components\Select::make('assigned_to')
                ->label(__('crm.fields.owner'))
                // CRM ownership is assigned to the Employee's linked User
                // account, so actions and history always have an auditable actor.
                ->options(fn () => User::withoutGlobalScopes()
                    ->where('company_id', $this->getOwnerRecord()->company_id)
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('pipeline_id')
                ->label(__('crm.fields.pipeline'))
                ->options(fn () => CrmPipeline::withoutGlobalScopes()
                    ->where('company_id', $this->getOwnerRecord()->company_id)
                    ->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get()
                    ->mapWithKeys(fn (CrmPipeline $pipeline) => [$pipeline->id => $pipeline->display_name]))
                ->required()
                ->live()
                ->disabled(fn (?Opportunity $record) => $record !== null),
            Forms\Components\Select::make('pipeline_stage_id')
                ->label(__('crm.fields.stage'))
                ->options(fn (Forms\Get $get) => CrmPipelineStage::withoutGlobalScopes()
                    ->where('company_id', $this->getOwnerRecord()->company_id)
                    ->where('pipeline_id', $get('pipeline_id'))
                    ->where('is_active', true)->orderBy('position')->get()
                    ->mapWithKeys(fn (CrmPipelineStage $stage) => [$stage->id => $stage->display_label]))
                ->required()
                ->disabled(fn (?Opportunity $record) => $record !== null),
            Forms\Components\TextInput::make('budget_min')->label(__('crm.fields.budget_min'))->numeric()->minValue(0)->lte('budget_max'),
            Forms\Components\TextInput::make('budget_max')->label(__('crm.fields.budget_max'))->numeric()->minValue(0)->gte('budget_min'),
            Forms\Components\TextInput::make('currency')->label(__('crm.fields.currency'))->length(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('crm.empty.opportunities.heading'))
            ->emptyStateDescription(__('crm.empty.opportunities.description'))
            ->emptyStateIcon('heroicon-o-briefcase')
            ->columns([
                Tables\Columns\TextColumn::make('listing.title')->label(__('crm.fields.listing'))->placeholder(__('—')),
                Tables\Columns\TextColumn::make('type')->label(__('crm.fields.type'))->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof OpportunityType ? $state->getLabel() : __('crm.opportunity_types.' . $state)),
                Tables\Columns\TextColumn::make('stage.label')->label(__('crm.fields.stage'))->badge()
                    ->formatStateUsing(fn ($state, Opportunity $record) => $record->stage?->display_label ?? $state),
                Tables\Columns\TextColumn::make('assignedTo.name')->label(__('crm.fields.owner'))->placeholder(__('—')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('crm.actions.create_opportunity'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    })
                    ->using(fn (array $data): Opportunity => $this->run(function () use ($data): Opportunity {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;
                        $data['lead_id'] = $this->getOwnerRecord()->id;

                        return app(OpportunityService::class)->create(auth()->user(), $data);
                    })),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('crm.actions.details')),
                Tables\Actions\EditAction::make()
                    ->using(fn (Opportunity $record, array $data): Opportunity => $this->run(
                        fn () => app(OpportunityService::class)->update(auth()->user(), $record, $data),
                    )),
            ]);
    }

    private function run(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('crm.navigation.opportunities'))
                ->body(collect($exception->errors())->flatten()->first())->send();
            throw $exception;
        } catch (AuthorizationException $exception) {
            Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send();
            throw $exception;
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getOwnerRecord()->company_id;

        return $data;
    }
}
