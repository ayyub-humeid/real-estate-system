<?php

namespace App\Services;

use App\Models\{ContractTemplate, ContractTemplateVersion, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

            if (blank(trim(strip_tags((string) ($attributes['body'] ?? ''))))) {
                throw ValidationException::withMessages([
                    'body' => __('Enter the legal text that should appear in this contract template.'),
                ]);
            }

            return ContractTemplateVersion::withoutGlobalScopes()->create([
                'company_id' => $locked->company_id,
                'contract_template_id' => $locked->id,
                'version_number' => $number,
                // Blade may compile literal {{...}} text in a PHP source file
                // into an echo expression. Persist only our stable token syntax.
                'body' => ContractTemplateTokenService::normalise($attributes['body'] ?? null),
                'clauses' => $attributes['clauses'] ?? null,
                'created_by' => $actor->id,
            ]);
        });
    }
}
