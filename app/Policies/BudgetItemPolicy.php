<?php
namespace App\Policies;
use App\Models\{BudgetItem,User}; use App\Policies\Concerns\CompanyOwnedPolicy;
class BudgetItemPolicy { use CompanyOwnedPolicy; public function viewAny(User $u):bool{return $this->canForResource($u,'view_any_budget_item');} public function view(User $u,BudgetItem $r):bool{return $this->canForRecord($u,'view_budget_item',$r);} public function create(User $u):bool{return $this->canForResource($u,'create_budget_item');} public function update(User $u,BudgetItem $r):bool{return $r->category->budget->status==='draft'&&$this->canForRecord($u,'update_budget_item',$r);} public function delete(User $u,BudgetItem $r):bool{return $r->category->budget->status==='draft'&&$this->canForRecord($u,'delete_budget_item',$r);} }
