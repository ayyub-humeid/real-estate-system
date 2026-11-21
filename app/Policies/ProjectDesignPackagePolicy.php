<?php

namespace App\Policies;

use App\Models\{ProjectDesignPackage, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectDesignPackagePolicy
{
    use HandlesAuthorization, CompanyOwnedPolicy;

    public function viewAny(User $u): bool { return $u->can('view_any_project::design::package'); }
    public function view(User $u, ProjectDesignPackage $r): bool { return $this->canForRecord($u, 'view_project::design::package', $r); }
    public function create(User $u): bool { return $u->can('create_project::design::package'); }
    public function update(User $u, ProjectDesignPackage $r): bool { return $this->canForRecord($u, 'update_project::design::package', $r); }
    /** A package with drawing versions is history and cannot be deleted. */
    public function delete(User $u, ProjectDesignPackage $r): bool { return ! $r->versions()->exists() && $this->canForRecord($u, 'delete_project::design::package', $r); }
    public function deleteAny(User $u): bool { return false; }
    public function forceDelete(User $u, ProjectDesignPackage $r): bool { return false; }
    public function forceDeleteAny(User $u): bool { return false; }
    public function restore(User $u, ProjectDesignPackage $r): bool { return false; }
    public function restoreAny(User $u): bool { return false; }
    public function replicate(User $u, ProjectDesignPackage $r): bool { return false; }
    public function reorder(User $u): bool { return false; }

    public function uploadVersion(User $u, ProjectDesignPackage $r): bool { return $this->canForRecord($u, 'upload_design_version', $r); }
    public function decideVersion(User $u, ProjectDesignPackage $r): bool { return $this->canForRecord($u, 'decide_design_version', $r); }
}
