<?php

namespace App\Filament\Pages;

use App\Enums\{CrmRequirementType, CrmStageType, OpportunityLostReason};
use App\Models\{Company, CrmPipeline, CrmPipelineStage, CrmStageRequirement, Opportunity};
use App\Filament\Resources\{LeadResource, OpportunityResource};
use App\Services\OpportunityWorkflowService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class CrmKanban extends Page
{
    protected static string $view = 'filament.pages.crm-kanban';
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected static ?int $navigationSort = 3;

    public ?int $companyId = null;
    public ?int $pipelineId = null;

    public static function getNavigationLabel(): string
    {
        return __('crm.navigation.kanban');
    }
    public static function getNavigationGroup(): ?string
    {
        return '💼 ' . __('crm.navigation.crm');
    }
    public function getTitle(): string
    {
        return __('crm.kanban.title');
    }
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_any_opportunity');
    }

    public function mount(): void
    {
        if (!auth()->user()->isSuperAdmin())
            $this->companyId = auth()->user()->company_id;
        $this->pipelineId = $this->pipelines()->value('id');
    }

    public function updatedCompanyId(): void
    {
        $this->pipelineId = $this->pipelines()->value('id');
    }
    public function updatedPipelineId(): void
    {
        $this->dispatch('$refresh');
    }
    public function companies()
    {
        return Company::query()->orderBy('name')->pluck('name', 'id');
    }

    public function pipelines()
    {
        if (!$this->companyId)
            return CrmPipeline::query()->whereRaw('1 = 0');
        return CrmPipeline::withoutGlobalScopes()->where('company_id', $this->companyId)->where('is_active', true)->orderByDesc('is_default')->orderBy('name');
    }

    public function stages()
    {
        if (!$this->pipelineId || !$this->companyId)
            return collect();
        return CrmPipelineStage::withoutGlobalScopes()->where('company_id', $this->companyId)->where('pipeline_id', $this->pipelineId)->where('is_active', true)
            ->with(['opportunities' => fn($query) => $query->with(['lead', 'listing', 'assignedTo'])->orderByDesc('updated_at')])->orderBy('position')->get();
    }

    public function moveOpportunity(int $opportunityId, int|string|null $stageId): void
    {
        $opportunity = null;
        $stage = null;

        try {
            if (!filled($stageId) || !ctype_digit((string) $stageId)) {
                throw ValidationException::withMessages(['stage' => __('crm.errors.stage_required')]);
            }
            $opportunity = Opportunity::withoutGlobalScopes()->findOrFail($opportunityId);
            $stage = CrmPipelineStage::withoutGlobalScopes()->findOrFail((int) $stageId);
            if ((int) $opportunity->company_id !== (int) $this->companyId || (int) $stage->company_id !== (int) $this->companyId)
                throw ValidationException::withMessages(['company_id' => __('crm.errors.cross_company')]);
            if ($stage->stage_type !== CrmStageType::Open) {
                $this->mountAction('terminalMove', [
                    'opportunityId' => $opportunity->id,
                    'stageId' => $stage->id,
                ]);

                return;
            }

            $this->performMove($opportunity, $stage);
        } catch (ValidationException $exception) {
            if ($opportunity) {
                $this->sendBlockerNotification($exception, $opportunity, $stage);
            } else {
                Notification::make()
                    ->danger()
                    ->title(__('crm.actions.move'))
                    ->body(collect($exception->errors())->flatten()->implode("\n\n"))
                    ->send();
            }
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send();
        } catch (ModelNotFoundException) {
            Notification::make()->danger()->title(__('crm.actions.move'))->body(__('crm.errors.stage_invalid'))->send();
        }
    }

    public function terminalMoveAction(): Action
    {
        return Action::make('terminalMove')
            ->modalHeading(fn (Action $action): string => $this->terminalStage($action->getArguments())?->stage_type === CrmStageType::Won
                ? __('crm.workflow.mark_won_heading')
                : __('crm.workflow.mark_lost_heading'))
            ->modalDescription(fn (Action $action): string => $this->terminalDescription($action->getArguments()))
            ->modalSubmitActionLabel(fn (Action $action): string => $this->terminalStage($action->getArguments())?->stage_type === CrmStageType::Won
                ? __('crm.actions.mark_won')
                : __('crm.actions.mark_lost'))
            ->form(fn (Action $action): array => $this->terminalForm($action->getArguments()))
            ->action(function (array $data, array $arguments): void {
                $opportunity = Opportunity::withoutGlobalScopes()->findOrFail((int) $arguments['opportunityId']);
                $stage = CrmPipelineStage::withoutGlobalScopes()->findOrFail((int) $arguments['stageId']);

                try {
                    $this->performMove($opportunity, $stage, $data);
                } catch (ValidationException $exception) {
                    $this->sendBlockerNotification($exception, $opportunity, $stage);
                    throw $exception;
                }
            });
    }

    /** @param array<string, mixed> $context */
    private function performMove(Opportunity $opportunity, CrmPipelineStage $stage, array $context = []): void
    {
        app(OpportunityWorkflowService::class)->moveToStage(auth()->user(), $opportunity, $stage, $context);
        Notification::make()->success()->title(__('crm.actions.move'))->send();
        $this->dispatch('$refresh');
    }

    /** @param array<string, mixed> $arguments */
    private function terminalStage(array $arguments): ?CrmPipelineStage
    {
        $id = $arguments['stageId'] ?? null;

        return $id ? CrmPipelineStage::withoutGlobalScopes()->find($id) : null;
    }

    /** @param array<string, mixed> $arguments */
    private function terminalDescription(array $arguments): string
    {
        $opportunity = Opportunity::withoutGlobalScopes()->with('stage')->find($arguments['opportunityId'] ?? null);
        $stage = $this->terminalStage($arguments);

        return __('crm.workflow.terminal_description', [
            'from' => $opportunity?->stage?->display_label ?? '—',
            'to' => $stage?->display_label ?? '—',
        ]);
    }

    /** @param array<string, mixed> $arguments
     *  @return array<int, \Filament\Forms\Components\Component> */
    private function terminalForm(array $arguments): array
    {
        $stage = $this->terminalStage($arguments);

        if ($stage?->stage_type === CrmStageType::Lost) {
            return [
                Forms\Components\Select::make('lost_reason')
                    ->label(__('crm.fields.lost_reason'))
                    ->options(OpportunityLostReason::options())
                    ->required(),
                Forms\Components\Textarea::make('closure_notes')
                    ->label(__('crm.fields.closure_notes')),
            ];
        }

        return [
            Forms\Components\Textarea::make('closure_notes')
                ->label(__('crm.fields.closure_notes'))
                ->helperText(__('crm.workflow.closure_notes_help'))
                ->required(),
        ];
    }

    private function sendBlockerNotification(ValidationException $exception, Opportunity $opportunity, ?CrmPipelineStage $stage): void
    {
        [$actionName, $label, $url] = $this->nextStepAction($opportunity, $stage);

        Notification::make()
            ->danger()
            ->title(__('crm.actions.move'))
            ->body(collect($exception->errors())->flatten()->implode("\n\n"))
            ->actions([NotificationAction::make($actionName)->label($label)->url($url)])
            ->send();
    }

    public function getViewData(): array
    {
        return ['companies' => $this->companies(), 'pipelines' => $this->pipelines()->get(), 'stages' => $this->stages(), 'rtl' => app()->getLocale() === 'ar'];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function nextStepAction(Opportunity $opportunity, ?CrmPipelineStage $target): array
    {
        $opportunity->loadMissing(['stage', 'lead']);
        $workflow = app(OpportunityWorkflowService::class);
        $missing = $opportunity->stage
            ? $workflow->missingRequirements($opportunity, $opportunity->stage)
            : collect();

        // Terminal requirements are checked after their closure context is
        // applied, so use them only when the current Stage has no blocker.
        if ($missing->isEmpty() && $target) {
            $missing = $workflow->missingRequirements($opportunity, $target);
        }

        /** @var CrmStageRequirement|null $requirement */
        $requirement = $missing->first();
        $config = $requirement?->config_json ?? [];
        $leadField = str_starts_with((string) ($config['field'] ?? ''), 'lead.');
        $inquiryCondition = $requirement?->requirement_type === CrmRequirementType::RelatedRecordExists
            && ($config['relation'] ?? null) === 'inquiries';

        if ($inquiryCondition || $leadField) {
            return [
                'open_lead',
                $inquiryCondition ? __('crm.workflow_actions.open_lead_inquiries') : __('crm.workflow_actions.open_lead'),
                LeadResource::getUrl('view', ['record' => $opportunity->lead]) . ($inquiryCondition ? '?activeRelationManager=0' : ''),
            ];
        }

        if ($requirement?->requirement_type === CrmRequirementType::ChecklistCompleted) {
            return [
                'complete_checklist',
                __('crm.workflow_actions.complete_checklist'),
                OpportunityResource::getUrl('view', ['record' => $opportunity]) . '?activeRelationManager=0',
            ];
        }

        if (in_array($requirement?->requirement_type, [CrmRequirementType::FieldRequired, CrmRequirementType::FieldEquals], true)) {
            return [
                'edit_opportunity',
                __('crm.workflow_actions.edit_opportunity'),
                OpportunityResource::getUrl('edit', ['record' => $opportunity]),
            ];
        }

        return [
            'open_opportunity',
            __('crm.actions.open_opportunity'),
            OpportunityResource::getUrl('view', ['record' => $opportunity]),
        ];
    }
}
