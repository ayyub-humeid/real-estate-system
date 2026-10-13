<?php

namespace App\Policies;

use App\Models\{OpportunityStageHistory, User};
use App\Policies\Concerns\CompanyOwnedPolicy;

class OpportunityStageHistoryPolicy
{
    use CompanyOwnedPolicy;

    public function viewAny(User $user): bool { return $user->can('view_any_opportunity_stage_history'); }
    public function view(User $user, OpportunityStageHistory $record): bool { return $this->canForRecord($user, 'view_opportunity_stage_history', $record); }
    public function create(User $user): bool { return false; }
    public function update(User $user, OpportunityStageHistory $record): bool { return false; }
    public function delete(User $user, OpportunityStageHistory $record): bool { return false; }
}
