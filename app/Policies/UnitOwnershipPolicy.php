<?php
namespace App\Policies;
use App\Models\{UnitOwnership, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
class UnitOwnershipPolicy { use CompanyOwnedPolicy; public function viewAny(User $u): bool { return $this->canForResource($u, 'view_any_unit_ownership'); } public function view(User $u, UnitOwnership $r): bool { return $this->canForRecord($u, 'view_unit_ownership', $r); } }
