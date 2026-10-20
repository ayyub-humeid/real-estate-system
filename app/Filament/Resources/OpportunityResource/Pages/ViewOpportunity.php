<?php

namespace App\Filament\Resources\OpportunityResource\Pages;

use App\Filament\Resources\OpportunityResource;
use App\Filament\Resources\OpportunityResource\RelationManagers\{ActivitiesRelationManager, FollowUpTasksRelationManager, OffersRelationManager, RequirementCompletionsRelationManager, StageHistoriesRelationManager};
use App\Models\Opportunity;
use App\Services\OpportunityWorkflowService;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOpportunity extends ViewRecord
{
    protected static string $resource = OpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }

    public function getRelationManagers(): array
    {
        return [RequirementCompletionsRelationManager::class, StageHistoriesRelationManager::class, FollowUpTasksRelationManager::class, ActivitiesRelationManager::class, OffersRelationManager::class];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('lead.name')
                    ->label(__('crm.fields.lead'))
                    ->weight('bold')
                    ->url(fn ($record) => \App\Filament\Resources\LeadResource::getUrl('view', ['record' => $record->lead])),
                Infolists\Components\TextEntry::make('listing.title')->label(__('crm.fields.listing'))->placeholder(__('—')),
                Infolists\Components\TextEntry::make('type')->label(__('crm.fields.type'))->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\OpportunityType ? $state->getLabel() : __('crm.opportunity_types.' . $state)),
                Infolists\Components\TextEntry::make('source')->label(__('crm.fields.source'))->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\CrmInquirySource ? $state->getLabel() : __('crm.sources.' . $state)),
                Infolists\Components\TextEntry::make('assignedTo.name')->label(__('crm.fields.owner'))->placeholder(__('crm.kanban.unassigned')),
                Infolists\Components\TextEntry::make('pipeline.name')->label(__('crm.fields.pipeline'))
                    ->formatStateUsing(fn ($state, $record) => $record->pipeline?->display_name ?? $state),
                Infolists\Components\TextEntry::make('stage.label')->label(__('crm.fields.stage'))->badge()
                    ->formatStateUsing(fn ($state, $record) => $record->stage?->display_label ?? $state),
            ])->columns(3),
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('budget_min')->label(__('crm.fields.budget_min'))->formatStateUsing(fn ($state, $record) => $state === null ? '—' : number_format((float) $state, 2) . ' ' . ($record->currency ?? '')),
                Infolists\Components\TextEntry::make('budget_max')->label(__('crm.fields.budget_max'))->formatStateUsing(fn ($state, $record) => $state === null ? '—' : number_format((float) $state, 2) . ' ' . ($record->currency ?? '')),
                Infolists\Components\TextEntry::make('expected_close_date')->label(__('crm.fields.expected_close_date'))->date()->placeholder(__('—')),
                Infolists\Components\TextEntry::make('notes')->label(__('crm.fields.notes'))->columnSpanFull()->placeholder(__('—')),
            ])->columns(3),
            Infolists\Components\Section::make(__('crm.navigation.requirements'))
                ->description(__('crm.help.requirements_configuration'))
                ->schema([
                    Infolists\Components\RepeatableEntry::make('current_stage_requirements')
                        ->label('')
                        ->state(fn (Opportunity $record): array => $this->requirementRows($record, $record->stage))
                        ->schema([
                            Infolists\Components\TextEntry::make('label')
                                ->label(__('crm.fields.label'))
                                ->weight('semibold'),
                            Infolists\Components\TextEntry::make('status')
                                ->label(__('crm.fields.status'))
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => __('crm.requirement_status.' . $state))
                                ->color(fn (string $state): string => $state === 'complete' ? 'success' : 'warning'),
                            Infolists\Components\TextEntry::make('guidance')
                                ->label(__('crm.fields.how_it_works'))
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ])
                ->visible(fn (Opportunity $record): bool => $record->stage?->requirements()
                    ->where('is_active', true)
                    ->where('is_required', true)
                    ->exists()),
            Infolists\Components\Section::make(__('crm.workflow_overview.title'))
                ->description(__('crm.workflow_overview.description'))
                ->schema([
                    Infolists\Components\RepeatableEntry::make('workflow_requirements')
                        ->label('')
                        ->state(function (Opportunity $record): array {
                            $record->loadMissing(['pipeline.stages.requirements', 'lead', 'requirementCompletions']);

                            return $record->pipeline?->stages
                                ->flatMap(fn ($stage) => collect($this->requirementRows($record, $stage))
                                    ->map(fn (array $requirement): array => [
                                        ...$requirement,
                                        'stage' => $stage->display_label . ($stage->id === $record->pipeline_stage_id
                                            ? ' · ' . __('crm.workflow_overview.current_stage')
                                            : ''),
                                    ]))
                                ->values()
                                ->all() ?? [];
                        })
                        ->schema([
                            Infolists\Components\TextEntry::make('stage')
                                ->label(__('crm.fields.stage'))
                                ->badge()
                                ->color('primary'),
                            Infolists\Components\TextEntry::make('label')
                                ->label(__('crm.navigation.requirements'))
                                ->weight('semibold'),
                            Infolists\Components\TextEntry::make('status')
                                ->label(__('crm.fields.status'))
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => __('crm.requirement_status.' . $state))
                                ->color(fn (string $state): string => $state === 'complete' ? 'success' : 'warning'),
                            Infolists\Components\TextEntry::make('guidance')
                                ->label(__('crm.fields.how_it_works'))
                                ->columnSpanFull(),
                        ])
                        ->columns(3),
                ]),
        ]);
    }

    private function requirementRows(Opportunity $record, $stage): array
    {
        if (! $stage) {
            return [];
        }

        $record->loadMissing(['lead', 'requirementCompletions']);
        $workflow = app(OpportunityWorkflowService::class);

        return $stage->requirements
            ->where('is_active', true)
            ->where('is_required', true)
            ->sortBy('position')
            ->map(fn ($requirement): array => [
                'label' => $requirement->display_label,
                'status' => $workflow->requirementIsSatisfied($record, $requirement) ? 'complete' : 'pending',
                'guidance' => $workflow->requirementGuidance($requirement),
            ])
            ->values()
            ->all();
    }
}
