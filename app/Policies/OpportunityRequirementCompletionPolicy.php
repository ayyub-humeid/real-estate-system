<?php

namespace App\Policies;

use App\Models\{OpportunityRequirementCompletion, User};
use App\Policies\Concerns\CompanyOwnedPolicy;

class OpportunityRequirementCompletionPolicy
{
    use CompanyOwnedPolicy;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_opportunity_requirement_completion');
    }

    public function view(User $user, OpportunityRequirementCompletion $record): bool
    {
        return $this->canForRecord($user, 'view_opportunity_requirement_completion', $record);
    }

    public function create(User $user): bool { return false; }
    public function update(User $user, OpportunityRequirementCompletion $record): bool { return false; }
    public function delete(User $user, OpportunityRequirementCompletion $record): bool { return false; }
}
