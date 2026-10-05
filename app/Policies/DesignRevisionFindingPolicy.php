<?php
namespace App\Policies;
use App\Models\{DesignRevisionFinding,User}; use App\Policies\Concerns\{CompanyOwnedPolicy,DesignOwnedPolicy};
class DesignRevisionFindingPolicy { use CompanyOwnedPolicy,DesignOwnedPolicy; public function viewAny(User $u):bool{return $this->designCanCreate($u,'view_any_design_revision_finding');} public function view(User $u,DesignRevisionFinding $r):bool{return $this->designCan($u,'view_design_revision_finding',$r);} public function create(User $u):bool{return $this->designCanCreate($u,'create_design_revision_finding');} public function update(User $u,DesignRevisionFinding $r):bool{return false;} public function delete(User $u,DesignRevisionFinding $r):bool{return false;} }
