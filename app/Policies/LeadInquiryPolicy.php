<?php

namespace App\Policies;

use App\Models\{LeadInquiry, User};
use App\Policies\Concerns\CompanyOwnedPolicy;

class LeadInquiryPolicy
{
    use CompanyOwnedPolicy;

    public function viewAny(User $user): bool { return $user->can('view_any_lead_inquiry'); }
    public function view(User $user, LeadInquiry $record): bool { return $this->canForRecord($user, 'view_lead_inquiry', $record); }
    public function create(User $user): bool { return $user->can('create_lead_inquiry'); }
    public function update(User $user, LeadInquiry $record): bool { return $this->canForRecord($user, 'update_lead_inquiry', $record); }
    public function delete(User $user, LeadInquiry $record): bool { return false; }
}
