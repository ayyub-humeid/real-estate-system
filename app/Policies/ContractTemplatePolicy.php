<?php
namespace App\Policies;
use App\Models\{ContractTemplate,User};
use App\Policies\Concerns\CompanyOwnedPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;
class ContractTemplatePolicy
{
    use HandlesAuthorization, CompanyOwnedPolicy;
    public function viewAny(User $user):bool{return $user->can('view_any_contract::template');}
    public function view(User $user,ContractTemplate $record):bool{return $this->canForRecord($user,'view_contract::template',$record);}
    public function create(User $user):bool{return $user->can('create_contract::template');}
    public function update(User $user,ContractTemplate $record):bool{return $this->canForRecord($user,'update_contract::template',$record);}
    public function delete(User $user,ContractTemplate $record):bool{return $this->canForRecord($user,'delete_contract::template',$record);}
    public function deleteAny(User $user):bool{return $user->can('delete_any_contract::template');}
    public function restore(User $user,ContractTemplate $record):bool{return $this->canForRecord($user,'restore_contract::template',$record);}
    public function restoreAny(User $user):bool{return $user->can('restore_any_contract::template');}
    public function forceDelete(User $user,ContractTemplate $record):bool{return $this->canForRecord($user,'force_delete_contract::template',$record);}
    public function forceDeleteAny(User $user):bool{return $user->can('force_delete_any_contract::template');}
    public function replicate(User $user,ContractTemplate $record):bool{return $this->canForRecord($user,'replicate_contract::template',$record);}
    public function reorder(User $user):bool{return $user->can('reorder_contract::template');}
}
