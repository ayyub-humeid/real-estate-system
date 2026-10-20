<?php

namespace App\Services;

use App\Models\{ContractTemplate, ContractTemplateVersion, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ContractTemplateService
{
    /**
     * Template revisions are append-only. Locking the template row serializes
     * version-number allocation on both MySQL and PostgreSQL.
     */
    public function createVersion(User $actor, ContractTemplate $template, array $attributes): ContractTemplateVersion
    {
        if (! $actor->can('update', $template)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $template, $attributes): ContractTemplateVersion {
            $locked = ContractTemplate::withoutGlobalScopes()->lockForUpdate()->findOrFail($template->id);
            if (! $actor->isSuperAdmin() && (int) $actor->company_id !== (int) $locked->company_id) {
                throw new AuthorizationException;
            }

            $number = ((int) ContractTemplateVersion::withoutGlobalScopes()
                ->where('contract_template_id', $locked->id)
                ->max('version_number')) + 1;

            return ContractTemplateVersion::withoutGlobalScopes()->create([
                'company_id' => $locked->company_id,
                'contract_template_id' => $locked->id,
                'version_number' => $number,
                'body' => $attributes['body'] ?? null,
                'clauses' => $attributes['clauses'] ?? null,
                'created_by' => $actor->id,
            ]);
        });
    }
}
