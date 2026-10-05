<?php
namespace App\Policies;
use App\Models\{BudgetCategory,User}; use App\Policies\Concerns\CompanyOwnedPolicy;
class BudgetCategoryPolicy { use CompanyOwnedPolicy; public function viewAny(User $u):bool{return $this->canForResource($u,'view_any_budget_category');} public function view(User $u,BudgetCategory $r):bool{return $this->canForRecord($u,'view_budget_category',$r);} public function create(User $u):bool{return $this->canForResource($u,'create_budget_category');} public function update(User $u,BudgetCategory $r):bool{return $r->budget->status==='draft'&&$this->canForRecord($u,'update_budget_category',$r);} public function delete(User $u,BudgetCategory $r):bool{return $r->budget->status==='draft'&&$this->canForRecord($u,'delete_budget_category',$r);} }
