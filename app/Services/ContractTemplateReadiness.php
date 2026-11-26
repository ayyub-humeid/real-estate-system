<?php

namespace App\Services;

use App\Enums\ContractType;
use App\Models\ContractTemplate;
use App\Models\ProjectPlannedUnit;

/**
 * Which kinds of contract a company can actually issue. A kind is ready once an
 * active template of that type holds at least one version of legal text.
 */
class ContractTemplateReadiness
{
    /**
     * Kinds the company sells or rents but cannot issue yet. Off-plan sales only
     * count for a company that has planned units to sell that way.
     *
     * @return list<ContractType>
     */
    public function missingTypes(int $companyId): array
    {
        $ready = $this->readyTypes($companyId);

        return array_values(array_filter(
            $this->requiredTypes($companyId),
            fn (ContractType $type): bool => ! in_array($type->value, $ready, true),
        ));
    }

    /** @return list<ContractType> */
    public function requiredTypes(int $companyId): array
    {
        $types = [ContractType::Sale, ContractType::Lease];

        if (ProjectPlannedUnit::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
            $types[] = ContractType::OffPlanSale;
        }

        return $types;
    }

    public function isReady(int $companyId, string $contractType): bool
    {
        return in_array($contractType, $this->readyTypes($companyId), true);
    }

    /** @return list<string> */
    private function readyTypes(int $companyId): array
    {
        return ContractTemplate::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereHas('versions', fn ($query) => $query->withoutGlobalScopes())
            ->toBase()
            ->distinct()
            ->pluck('contract_type')
            ->all();
    }
}
