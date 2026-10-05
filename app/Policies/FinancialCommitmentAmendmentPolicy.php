<?php
namespace App\Policies;
use App\Models\{FinancialCommitmentAmendment,User}; use App\Policies\Concerns\CompanyOwnedPolicy;
class FinancialCommitmentAmendmentPolicy { use CompanyOwnedPolicy; public function viewAny(User $u):bool{return $this->canForResource($u,'view_any_financial_commitment_amendment');} public function view(User $u,FinancialCommitmentAmendment $r):bool{return $this->canForRecord($u,'view_financial_commitment_amendment',$r);} public function create(User $u):bool{return $this->canForResource($u,'create_financial_commitment_amendment');} public function approve(User $u,FinancialCommitmentAmendment $r):bool{return $this->canForRecord($u,'approve_financial_commitment_amendment',$r);} }
