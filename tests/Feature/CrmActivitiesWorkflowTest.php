<?php

namespace Tests\Feature;

use App\Enums\{CrmActivityOutcome, CrmActivityType, CrmInquirySource, CrmRequirementType, OpportunityType};
use App\Models\{Company, CrmFollowUpTask, CrmStageRequirement, User};
use App\Services\{CrmTaskService, LeadIntakeService, OpportunityService, OpportunityWorkflowService};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CrmActivitiesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): Company { return Company::create(['name'=>$name,'email'=>strtolower($name).'@test.local']); }
    private function user(Company $company, array $permissions=[]): User { $user=User::factory()->create(['company_id'=>$company->id]); foreach($permissions as $permission)$user->givePermissionTo(Permission::findOrCreate($permission,'web')); return $user; }
    private function crmPermissions(): array { return ['create_lead','create_opportunity','view_lead','view_opportunity','move_opportunity_stage','create_crm_follow_up_task','update_crm_follow_up_task','complete_crm_follow_up_task','complete_any_crm_follow_up_task','create_crm_activity']; }
    private function opportunity(User $actor): \App\Models\Opportunity { $lead=app(LeadIntakeService::class)->createLead($actor,['name'=>'Ahmad','phone'=>'0591111111','source'=>CrmInquirySource::Manual->value]); return app(OpportunityService::class)->create($actor,['lead_id'=>$lead->id,'type'=>OpportunityType::Sale->value,'source'=>CrmInquirySource::Manual->value]); }

    public function test_task_completion_creates_one_inherited_activity_and_optional_next_follow_up_atomically(): void
    {
        $company=$this->company('a'); $actor=$this->user($company,$this->crmPermissions()); $opportunity=$this->opportunity($actor);
        $task=app(CrmTaskService::class)->create($actor,['opportunity_id'=>$opportunity->id,'assigned_to'=>$actor->id,'type'=>CrmActivityType::Call->value,'title'=>'Call Ahmad','due_at'=>now()->addHour()]);
        $activity=app(CrmTaskService::class)->complete($actor,$task,['outcome'=>CrmActivityOutcome::Interested->value,'notes'=>'Interested in viewing','create_next_follow_up'=>true,'next_type'=>CrmActivityType::Viewing->value,'next_title'=>'Book viewing','next_due_at'=>now()->addDay()]);
        $this->assertSame(CrmActivityType::Call,$activity->type); $this->assertSame(CrmActivityOutcome::Interested,$activity->outcome); $this->assertSame($actor->id,$activity->performed_by);
        $this->assertTrue($task->fresh()->status->value === 'completed'); $this->assertSame($actor->id,$task->fresh()->completed_by); $this->assertSame(1,$opportunity->activities()->count()); $this->assertSame(2,$opportunity->followUpTasks()->count());
        $this->expectException(ValidationException::class); app(CrmTaskService::class)->complete($actor,$task->fresh(),['outcome'=>CrmActivityOutcome::Interested->value]);
    }

    public function test_cross_company_assignee_and_unpermitted_completion_are_rejected(): void
    {
        $a=$this->company('a'); $b=$this->company('b'); $actor=$this->user($a,$this->crmPermissions()); $other=$this->user($b); $opportunity=$this->opportunity($actor);
        $this->expectException(ValidationException::class); app(CrmTaskService::class)->create($actor,['opportunity_id'=>$opportunity->id,'assigned_to'=>$other->id,'type'=>'call','title'=>'Bad']);
    }

    public function test_task_due_date_cannot_be_before_today_on_create_or_update(): void
    {
        $company=$this->company('a'); $actor=$this->user($company,$this->crmPermissions()); $opportunity=$this->opportunity($actor); $service=app(CrmTaskService::class);

        try {
            $service->create($actor,['opportunity_id'=>$opportunity->id,'assigned_to'=>$actor->id,'type'=>'call','title'=>'Past task','due_at'=>now()->subMinutes(5)]);
            $this->fail('A past due date must be rejected when a task is created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('due_at',$exception->errors());
        }

        $task=$service->create($actor,['opportunity_id'=>$opportunity->id,'assigned_to'=>$actor->id,'type'=>'call','title'=>'Future task','due_at'=>now()->addDay()]);
        try {
            $service->update($actor,$task,['assigned_to'=>$actor->id,'type'=>'call','title'=>'Past update','due_at'=>now()->subMinutes(5)]);
            $this->fail('A past due date must be rejected when a task is edited.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('due_at',$exception->errors());
        }
        $this->assertSame('Future task',$task->fresh()->title);
    }

    public function test_activity_requirement_uses_real_structured_activity_without_auto_moving_kanban(): void
    {
        $company=$this->company('a'); $actor=$this->user($company,$this->crmPermissions()); $opportunity=$this->opportunity($actor); $pipeline=$opportunity->pipeline; $new=$opportunity->stage; $contacted=$pipeline->stages()->where('code','contacted')->firstOrFail();
        CrmStageRequirement::withoutGlobalScopes()->create(['company_id'=>$company->id,'pipeline_stage_id'=>$new->id,'key'=>'successful_call','label'=>'Successful call','requirement_type'=>CrmRequirementType::ActivityExists->value,'config_json'=>['activity_type'=>'call','allowed_outcomes'=>['interested']],'is_required'=>true,'is_active'=>true]);
        app(CrmTaskService::class)->logActivity($actor,['opportunity_id'=>$opportunity->id,'type'=>'call','outcome'=>'no_answer']);
        $this->expectException(ValidationException::class); app(OpportunityWorkflowService::class)->moveToStage($actor,$opportunity,$contacted);
    }
}
