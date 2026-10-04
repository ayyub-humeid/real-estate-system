<?php
namespace App\Policies;
use App\Models\{ProjectBuilding, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
class ProjectBuildingPolicy
{
    use CompanyOwnedPolicy;
    public function viewAny(User $u): bool
    {
        return $this->canForResource($u, 'view_any_project_building');
    }
    public function view(User $u, ProjectBuilding $r): bool
    {
        return $this->canForRecord($u, 'view_project_building', $r);
    }
    public function create(User $u): bool
    {
        return $this->canForResource($u, 'create_project_building');
    }
    public function update(User $u, ProjectBuilding $r): bool
    {
        return $this->canForRecord($u, 'update_project_building', $r);
    }
    public function delete(User $u, ProjectBuilding $r): bool
    {
        return $this->canForRecord($u, 'delete_project_building', $r);
    }
}
