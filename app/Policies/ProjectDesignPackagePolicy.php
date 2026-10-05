<?php
namespace App\Policies;
use App\Models\{ProjectDesignPackage, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;
class ProjectDesignPackagePolicy
{
    use HandlesAuthorization, CompanyOwnedPolicy;
    public function viewAny(User $u): bool
    {
        return $u->can('view_any_project::design::package');
    }
    public function view(User $u, ProjectDesignPackage $r): bool
    {
        return $this->canForRecord($u, 'view_project::design::package', $r);
    }
    public function create(User $u): bool
    {
        return $u->can('create_project::design::package');
    }
    public function update(User $u, ProjectDesignPackage $r): bool
    {
        return !in_array($r->status, ['approved', 'closed'], true) && $this->canForRecord($u, 'update_project::design::package', $r);
    }
    public function delete(User $u, ProjectDesignPackage $r): bool
    {
        return $r->status === 'planned' && $this->canForRecord($u, 'delete_project::design::package', $r);
    }
    public function deleteAny(User $u): bool
    {
        return false;
    }
    public function forceDelete(User $u, ProjectDesignPackage $r): bool
    {
        return false;
    }
    public function forceDeleteAny(User $u): bool
    {
        return false;
    }
    public function restore(User $u, ProjectDesignPackage $r): bool
    {
        return false;
    }
    public function restoreAny(User $u): bool
    {
        return false;
    }
    public function replicate(User $u, ProjectDesignPackage $r): bool
    {
        return false;
    }
    public function reorder(User $u): bool
    {
        return false;
    }
    public function assignOffice(User $u, ProjectDesignPackage $r): bool
    {
        return $this->canForRecord($u, 'assign_design_package', $r);
    }
    public function submit(User $u, ProjectDesignPackage $r): bool
    {
        return $this->canForRecord($u, 'submit_design_package', $r);
    }
    public function approve(User $u, ProjectDesignPackage $r): bool
    {
        return $this->canForRecord($u, 'approve_design_package', $r);
    }
    public function close(User $u, ProjectDesignPackage $r): bool
    {
        return $this->canForRecord($u, 'close_design_package', $r);
    }
}
