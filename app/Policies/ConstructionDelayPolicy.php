<?php
namespace App\Policies;
use App\Models\{ConstructionDelay,User}; use App\Policies\Concerns\CompanyOwnedPolicy;
class ConstructionDelayPolicy { use CompanyOwnedPolicy; public function viewAny(User $u):bool{return $this->canForResource($u,'view_any_construction_delay');} public function view(User $u,ConstructionDelay $r):bool{return $this->canForRecord($u,'view_construction_delay',$r);} public function create(User $u):bool{return $this->canForResource($u,'create_construction_delay');} public function update(User $u,ConstructionDelay $r):bool{return false;} public function delete(User $u,ConstructionDelay $r):bool{return false;} }
