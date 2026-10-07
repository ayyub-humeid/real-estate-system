<?php
namespace App\Policies;
use App\Models\{ConstructionInspection,User}; use App\Policies\Concerns\CompanyOwnedPolicy;
class ConstructionInspectionPolicy { use CompanyOwnedPolicy; public function viewAny(User $u):bool{return $this->canForResource($u,'view_any_construction_inspection');} public function view(User $u,ConstructionInspection $r):bool{return $this->canForRecord($u,'view_construction_inspection',$r);} public function create(User $u):bool{return $this->canForResource($u,'create_construction_inspection');} public function update(User $u,ConstructionInspection $r):bool{return false;} public function delete(User $u,ConstructionInspection $r):bool{return false;} }
