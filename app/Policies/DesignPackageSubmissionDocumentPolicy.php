<?php
namespace App\Policies;
use App\Models\{DesignPackageSubmissionDocument,User}; use App\Policies\Concerns\{CompanyOwnedPolicy,DesignOwnedPolicy};
class DesignPackageSubmissionDocumentPolicy { use CompanyOwnedPolicy,DesignOwnedPolicy; public function viewAny(User $u):bool{return $this->designCanCreate($u,'view_any_design_package_submission_document');} public function view(User $u,DesignPackageSubmissionDocument $r):bool{return $this->designCan($u,'view_design_package_submission_document',$r);} public function create(User $u):bool{return $this->designCanCreate($u,'create_design_package_submission_document');} public function update(User $u,DesignPackageSubmissionDocument $r):bool{return false;} public function delete(User $u,DesignPackageSubmissionDocument $r):bool{return false;} }
