<?php
namespace App\Policies;
use App\Models\{UnitStatusHistory, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
class UnitStatusHistoryPolicy { use CompanyOwnedPolicy; public function viewAny(User $u): bool { return $this->canForResource($u, 'view_any_unit_status_history'); } public function view(User $u, UnitStatusHistory $r): bool { return $this->canForRecord($u, 'view_unit_status_history', $r); } }
