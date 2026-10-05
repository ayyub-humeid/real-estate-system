<?php
namespace App\Policies;
use App\Models\{PaymentAllocation,User}; use App\Policies\Concerns\CompanyOwnedPolicy;
class PaymentAllocationPolicy { use CompanyOwnedPolicy; public function viewAny(User $u):bool{return $this->canForResource($u,'view_any_payment_allocation');} public function view(User $u,PaymentAllocation $r):bool{return $this->canForRecord($u,'view_payment_allocation',$r);} public function create(User $u):bool{return $this->canForResource($u,'allocate_payment');} public function update(User $u,PaymentAllocation $r):bool{return false;} public function delete(User $u,PaymentAllocation $r):bool{return false;} }
