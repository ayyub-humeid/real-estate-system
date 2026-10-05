<?php
namespace App\Policies;
use App\Models\{DocumentVersion, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
class DocumentVersionPolicy
{
    use CompanyOwnedPolicy;
    public function viewAny(User $u): bool
    {
        return $this->canForResource($u, 'view_any_document_version');
    }
    public function view(User $u, DocumentVersion $r): bool
    {
        return $this->canForRecord($u, 'view_document_version', $r);
    }
    public function create(User $u): bool
    {
        return $this->canForResource($u, 'create_document_version');
    }
    public function update(User $u, DocumentVersion $r): bool
    {
        return false;
    }
    public function delete(User $u, DocumentVersion $r): bool
    {
        return false;
    }
}
