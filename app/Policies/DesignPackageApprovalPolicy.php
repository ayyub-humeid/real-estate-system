<?php
namespace App\Policies;
use App\Models\{DesignPackageApproval,User}; use App\Policies\Concerns\{CompanyOwnedPolicy,DesignOwnedPolicy};
class DesignPackageApprovalPolicy { use CompanyOwnedPolicy,DesignOwnedPolicy; public function viewAny(User $u):bool{return $this->designCanCreate($u,'view_any_design_package_approval');} public function view(User $u,DesignPackageApproval $r):bool{return $this->designCan($u,'view_design_package_approval',$r);} public function create(User $u):bool{return $this->designCanCreate($u,'create_design_package_approval');} public function update(User $u,DesignPackageApproval $r):bool{return false;} public function delete(User $u,DesignPackageApproval $r):bool{return false;} }
