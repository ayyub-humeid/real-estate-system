<?php
namespace App\Policies;
use App\Models\{ProjectMember, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
class ProjectMemberPolicy
{
    use CompanyOwnedPolicy;
    public function viewAny(User $u): bool
    {
        return $this->canForResource($u, 'view_any_project_member');
    }
    public function view(User $u, ProjectMember $r): bool
    {
        return $this->canForRecord($u, 'view_project_member', $r);
    }
    public function create(User $u): bool
    {
        return $this->canForResource($u, 'manage_project_members');
    }
    public function update(User $u, ProjectMember $r): bool
    {
        return $this->canForRecord($u, 'manage_project_members', $r);
    }
    public function delete(User $u, ProjectMember $r): bool
    {
        return $this->canForRecord($u, 'manage_project_members', $r);
    }
}
