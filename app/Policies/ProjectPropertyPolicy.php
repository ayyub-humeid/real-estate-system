<?php
namespace App\Policies;
use App\Models\{ProjectProperty,User}; use App\Policies\Concerns\CompanyOwnedPolicy;
class ProjectPropertyPolicy { use CompanyOwnedPolicy; public function viewAny(User $u):bool{return $this->canForResource($u,'view_any_project_property');} public function view(User $u,ProjectProperty $r):bool{return $this->canForRecord($u,'view_project_property',$r);} public function create(User $u):bool{return $this->canForResource($u,'attach_project_property');} public function update(User $u,ProjectProperty $r):bool{return $this->canForRecord($u,'attach_project_property',$r);} public function delete(User $u,ProjectProperty $r):bool{return $this->canForRecord($u,'detach_project_property',$r);} }
