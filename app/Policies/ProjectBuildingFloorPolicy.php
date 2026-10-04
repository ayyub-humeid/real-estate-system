<?php
namespace App\Policies;
use App\Models\{ProjectBuildingFloor, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
class ProjectBuildingFloorPolicy
{
    use CompanyOwnedPolicy;
    public function viewAny(User $u): bool
    {
        return $this->canForResource($u, 'view_any_project_building_floor');
    }
    public function view(User $u, ProjectBuildingFloor $r): bool
    {
        return $this->canForRecord($u, 'view_project_building_floor', $r);
    }
    public function create(User $u): bool
    {
        return $this->canForResource($u, 'create_project_building_floor');
    }
    public function update(User $u, ProjectBuildingFloor $r): bool
    {
        return $this->canForRecord($u, 'update_project_building_floor', $r);
    }
    public function delete(User $u, ProjectBuildingFloor $r): bool
    {
        return $this->canForRecord($u, 'delete_project_building_floor', $r);
    }
}
