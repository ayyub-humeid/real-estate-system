<?php
namespace App\Policies\Concerns;
use App\Models\User;
trait DesignOwnedPolicy
{
    protected function designCan(User $user, string $permission, object $record): bool
    {
        return $this->canForRecord($user, $permission, $record);
    }
    protected function designCanCreate(User $user, string $permission): bool
    {
        return $this->canForResource($user, $permission);
    }
}
