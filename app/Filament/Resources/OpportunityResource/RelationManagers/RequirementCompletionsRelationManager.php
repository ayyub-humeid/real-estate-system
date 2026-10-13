<?php

namespace App\Filament\Resources\OpportunityResource\RelationManagers;

use App\Enums\CrmRequirementType;
use App\Models\CrmStageRequirement;
use App\Services\CrmPipelineStageService;
use App\Services\OpportunityWorkflowService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class RequirementCompletionsRelationManager extends RelationManager
{
    protected static string $relationship = 'requirementCompletions';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('crm.navigation.requirement_completions');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with(['requirement.stage', 'completedBy']))
            ->columns([
                Tables\Columns\TextColumn::make('requirement.stage.label')->label(__('crm.fields.stage'))
                    ->formatStateUsing(fn ($state, $record) => $record->requirement?->stage?->display_label ?? $state)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('requirement.label')->label(__('crm.navigation.requirements'))->weight('medium')
                    ->formatStateUsing(fn ($state, $record) => $record->requirement?->display_label ?? $state),
                Tables\Columns\TextColumn::make('completedBy.name')->label(__('crm.fields.owner'))->placeholder('—'),
                Tables\Columns\TextColumn::make('completed_at')->label(__('crm.actions.complete'))->dateTime(),
                Tables\Columns\TextColumn::make('notes')->label(__('crm.fields.notes'))->limit(60)->placeholder('—'),
            ])
            ->emptyStateHeading(__('crm.help.requirement_completions_empty_heading'))
            ->emptyStateDescription(fn (): string => $this->checklistRequirements()->isEmpty()
                ? $this->automaticRequirementsGuidance()
                : __('crm.help.pending_manual_requirements'))
            ->headerActions([
                Tables\Actions\Action::make('completeRequirement')
                    ->label(__('crm.actions.complete_requirement'))
                    ->icon('heroicon-o-check-circle')
                    ->modalHeading(fn (): string => __('crm.actions.complete_requirement') . ' — ' . ($this->getOwnerRecord()->stage?->display_label ?? ''))
                    ->modalDescription(__('crm.help.complete_requirement'))
                    ->visible(fn (): bool => $this->checklistRequirements()->isNotEmpty())
                    ->form([
                        Forms\Components\Select::make('requirement_id')
                            ->label(__('crm.navigation.requirements'))
                            ->options(fn (): array => $this->checklistRequirements()
                                ->mapWithKeys(fn (CrmStageRequirement $requirement) => [
                                    $requirement->id => ($requirement->stage?->display_label ?? $this->getOwnerRecord()->stage?->display_label)
                                        . ' — ' . $requirement->display_label,
                                ])
                                ->all())
                            ->required(),
                        Forms\Components\Textarea::make('notes')->label(__('crm.fields.notes')),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $requirement = CrmStageRequirement::withoutGlobalScopes()->findOrFail($data['requirement_id']);
                            app(OpportunityWorkflowService::class)->completeRequirement(
                                auth()->user(),
                                $this->getOwnerRecord(),
                                $requirement,
                                $data['notes'] ?? null,
                            );
                            Notification::make()->success()->title(__('crm.actions.complete'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title(__('crm.actions.complete'))
                                ->body(collect($exception->errors())->flatten()->first())->send();
                            throw $exception;
                        } catch (AuthorizationException $exception) {
                            Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send();
                            throw $exception;
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('crm.actions.details'))
                    ->modalHeading(__('crm.actions.requirement_completion_details')),
            ]);
    }

    private function checklistRequirements()
    {
        return CrmStageRequirement::withoutGlobalScopes()
            ->where('company_id', $this->getOwnerRecord()->company_id)
            ->where('pipeline_stage_id', $this->getOwnerRecord()->pipeline_stage_id)
            ->where('requirement_type', CrmRequirementType::ChecklistCompleted->value)
            ->where('is_active', true)
            ->whereNotIn('id', $this->getOwnerRecord()->requirementCompletions()->select('stage_requirement_id'))
            ->orderBy('position')
            ->get();
    }

    private function automaticRequirementsGuidance(): string
    {
        $requirements = CrmStageRequirement::withoutGlobalScopes()
            ->where('company_id', $this->getOwnerRecord()->company_id)
            ->where('pipeline_stage_id', $this->getOwnerRecord()->pipeline_stage_id)
            ->where('is_active', true)
            ->where('is_required', true)
            ->where('requirement_type', '!=', CrmRequirementType::ChecklistCompleted->value)
            ->orderBy('position')
            ->get();

        if ($requirements->isEmpty()) {
            return __('crm.help.no_manual_requirements');
        }

        return __('crm.help.automatic_requirements') . "\n\n" . $requirements
            ->map(fn (CrmStageRequirement $requirement): string => '• ' . $requirement->display_label . "\n" . $this->guidance($requirement))
            ->implode("\n\n");
    }

    private function guidance(CrmStageRequirement $requirement): string
    {
        $config = $requirement->config_json ?? [];

        return match ($requirement->requirement_type) {
            CrmRequirementType::RelatedRecordExists => (($config['relation'] ?? null) === 'inquiries' && ($config['field'] ?? null) === 'contact_result')
                ? __('crm.requirement_guidance.contact_result')
                : __('crm.help.no_manual_requirements'),
            CrmRequirementType::FieldRequired => __('crm.requirement_guidance.field', [
                'field' => CrmPipelineStageService::fieldOptions()[$config['field'] ?? null] ?? '',
            ]),
            CrmRequirementType::FieldEquals => __('crm.requirement_guidance.field_equals', [
                'field' => CrmPipelineStageService::fieldOptions()[$config['field'] ?? null] ?? '',
                'value' => $config['value'] ?? '—',
            ]),
            default => __('crm.help.no_manual_requirements'),
        };
    }
}
