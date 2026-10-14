<?php
namespace App\Services;
use App\Enums\{CrmAssignmentMode, CrmInquirySource, LeadStatus, OpportunityType};
use App\Models\{Company, CompanySetting, Lead, LeadInquiry, Listing, Opportunity, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class LeadIntakeService
{
    public function createLead(User $actor, array $data): Lead
    {
        $company = $this->company($actor, $data['company_id'] ?? null);
        $this->authorize($actor, 'create', new Lead(['company_id' => $company->id]));
        return DB::transaction(function () use ($actor, $company, $data) {
            app(CrmPipelineProvisioningService::class)->ensureDefault($company);
            $name = $this->name($data);
            [$phone, $email] = $this->normalized($data);
            $matches = $this->matches($company->id, $phone, $email);
            if ($matches->isNotEmpty())
                $this->invalid('phone', __('crm.errors.duplicate_lead', ['lead' => $matches->first()->name]));
            $owner = $this->owner($actor, $company, $data['assigned_to'] ?? null);
            return Lead::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => $name, 'phone' => $data['phone'] ?? null, 'normalized_phone' => $phone, 'email' => $data['email'] ?? null, 'normalized_email' => $email, 'status' => LeadStatus::Active, 'first_source' => $data['source'] ?? CrmInquirySource::Manual->value, 'assigned_to' => $owner?->id, 'created_by' => $actor->id, 'updated_by' => $actor->id]); });
    }
    public function updateLead(User $actor, Lead $lead, array $data): Lead
    {
        $company = $this->recordCompany($actor, $lead);
        $this->authorize($actor, 'update', $lead);
        return DB::transaction(function () use ($actor, $company, $lead, $data) {
            Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($company->id);
            $name = $this->name($data);
            [$phone, $email] = $this->normalized($data);
            $matches = $this->matches($company->id, $phone, $email, $lead->id);
            if ($matches->isNotEmpty())
                $this->invalid('phone', __('crm.errors.duplicate_lead', ['lead' => $matches->first()->name]));
            $owner = $this->owner($actor, $company, $data['assigned_to'] ?? null, true);
            $lead->update(['name' => $name, 'phone' => $data['phone'] ?? null, 'normalized_phone' => $phone, 'email' => $data['email'] ?? null, 'normalized_email' => $email, 'status' => $data['status'] ?? $lead->status, 'first_source' => $data['source'] ?? $lead->first_source, 'assigned_to' => $owner?->id, 'updated_by' => $actor->id]);
            return $lead->refresh(); });
    }
    public function intake(User $actor, array $data): array
    {
        $company = $this->company($actor, $data['company_id'] ?? null);
        $this->authorize($actor, 'create', new Lead(['company_id' => $company->id]));
        return DB::transaction(function () use ($actor, $company, $data) {
            app(CrmPipelineProvisioningService::class)->ensureDefault($company);
            $name = $this->name($data);
            [$phone, $email] = $this->normalized($data);
            $matches = $this->matches($company->id, $phone, $email);
            if ($matches->count() > 1)
                $this->invalid('phone', __('crm.errors.dedup_conflict'));
            $lead = $matches->first();
            $duplicate = (bool) $lead;
            if (!$lead) {
                $owner = $this->owner($actor, $company, $data['assigned_to'] ?? null);
                $lead = Lead::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => $name, 'phone' => $data['phone'] ?? null, 'normalized_phone' => $phone, 'email' => $data['email'] ?? null, 'normalized_email' => $email, 'status' => LeadStatus::Active, 'first_source' => $data['source'] ?? CrmInquirySource::Manual->value, 'assigned_to' => $owner?->id, 'created_by' => $actor->id, 'updated_by' => $actor->id]); }$listing = $this->listing($company, $data['listing_id'] ?? null);
            $inquiry = LeadInquiry::withoutGlobalScopes()->create(['company_id' => $company->id, 'lead_id' => $lead->id, 'listing_id' => $listing?->id, 'source' => $data['source'] ?? CrmInquirySource::Manual->value, 'source_reference' => $data['source_reference'] ?? null, 'contact_result' => $data['contact_result'] ?? null, 'message' => $data['message'] ?? null, 'metadata' => $data['metadata'] ?? null, 'created_by' => $actor->id]);
            $opportunity = null;
            if ($listing) {
                $opportunity = app(OpportunityService::class)->create($actor, ['company_id' => $company->id, 'lead_id' => $lead->id, 'listing_id' => $listing->id, 'type' => $data['opportunity_type'] ?? $listing->listing_type->value, 'assigned_to' => $data['opportunity_assigned_to'] ?? $lead->assigned_to, 'source' => $data['source'] ?? CrmInquirySource::Manual->value, 'source_reference' => $data['source_reference'] ?? null]); }return compact('lead', 'inquiry', 'opportunity', 'duplicate'); });
    }
    public function assign(User $actor, Lead $lead, ?int $userId): Lead
    {
        $this->authorize($actor, 'assign', $lead);
        $owner = $this->owner($actor, $this->recordCompany($actor, $lead), $userId, true);
        $lead->update(['assigned_to' => $owner?->id, 'updated_by' => $actor->id]);
        return $lead->refresh();
    }
    private function normalized(array $data): array
    {
        $phone = $this->phone($data['phone'] ?? null);
        $email = filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null;
        if (!$phone && !$email)
            $this->invalid('phone', __('crm.errors.contact_required'));
        return [$phone, $email];
    }
    private function name(array $data): string
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '')
            $this->invalid('name', __('validation.required', ['attribute' => __('crm.fields.name')]));
        return $name;
    }
    private function phone(?string $phone): ?string
    {
        if (blank($phone))
            return null;
        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '00'))
            return '+' . substr($digits, 2);
        if (str_starts_with($digits, '0') && strlen($digits) === 10)
            return '+970' . substr($digits, 1);
        return '+' . $digits;
    }
    private function matches(int $companyId, ?string $phone, ?string $email, ?int $exceptId = null)
    {
        return Lead::withoutGlobalScopes()->where('company_id', $companyId)->when($exceptId, fn($q) => $q->whereKeyNot($exceptId))->where(fn($q) => $q->when($phone, fn($q) => $q->orWhere('normalized_phone', $phone))->when($email, fn($q) => $q->orWhere('normalized_email', $email)))->get();
    }
    private function listing(Company $company, ?int $id): ?Listing
    {
        if (!$id)
            return null;
        $record = Listing::withoutGlobalScopes()->findOrFail($id);
        if ($record->company_id !== $company->id)
            $this->invalid('listing_id', __('crm.errors.cross_company'));
        return $record;
    }
    private function owner(User $actor, Company $company, ?int $id, bool $allowNull = false): ?User
    {
        if ($id) {
            $user = User::withoutGlobalScopes()->findOrFail($id);
            if ((int) $user->company_id !== $company->id)
                $this->invalid('assigned_to', __('crm.errors.cross_company'));
            return $user;
        }
        if ($allowNull)
            return null;
        $mode = CompanySetting::withoutGlobalScopes()->where('company_id', $company->id)->first()?->crm_assignment_mode ?? CrmAssignmentMode::Manual;
        if ($mode instanceof CrmAssignmentMode)
            $mode = $mode->value;
        if ($mode === CrmAssignmentMode::RoundRobin->value)
            return $this->nextOwner($company);
        return null;
    }
    private function nextOwner(Company $company): ?User
    {
        $settings = CompanySetting::withoutGlobalScopes()->firstOrCreate(['company_id' => $company->id]);
        $settings = CompanySetting::withoutGlobalScopes()->lockForUpdate()->find($settings->id);
        $users = User::withoutGlobalScopes()->where('company_id', $company->id)->orderBy('id')->get();
        if ($users->isEmpty())
            return null;
        $next = $users->firstWhere('id', '>', $settings->crm_round_robin_last_user_id) ?? $users->first();
        $settings->update(['crm_round_robin_last_user_id' => $next->id]);
        return $next;
    }
    private function company(User $actor, ?int $id): Company
    {
        if ($actor->isSuperAdmin()) {
            if (!$id)
                $this->invalid('company_id', __('crm.errors.company_required'));
            return Company::findOrFail($id);
        }
        if (!$actor->company_id)
            $this->invalid('company_id', __('crm.errors.company_required'));
        if ($id && (int) $id !== (int) $actor->company_id)
            $this->invalid('company_id', __('crm.errors.cross_company'));
        return Company::findOrFail($actor->company_id);
    }
    private function recordCompany(User $actor, object $r): Company
    {
        if (!$actor->isSuperAdmin() && (int) $actor->company_id !== (int) $r->company_id)
            throw new AuthorizationException;
        return Company::findOrFail($r->company_id);
    }
    private function authorize(User $u, string $a, object $r): void
    {
        if (!$u->can($a, $r))
            throw new AuthorizationException;
    }
    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
