<?php

namespace App\Services;

use App\Models\{ContractTemplate, ContractTemplateVersion, User};
use App\Support\ContractPdfContentLimits;
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

            $body = ContractTemplateTokenService::normalise($attributes['body'] ?? null);
            if (blank(trim(strip_tags((string) $body)))) {
                throw ValidationException::withMessages([
                    'body' => __('Enter the legal text that should appear in this contract template.'),
                ]);
            }
            if (mb_strlen($body) > ContractPdfContentLimits::TEMPLATE_BODY_MAX_CHARACTERS) {
                throw ValidationException::withMessages([
                    'body' => __('contract_domain.content.template_too_large', ['limit' => number_format(ContractPdfContentLimits::TEMPLATE_BODY_MAX_CHARACTERS)]),
                ]);
            }
            $this->assertClausesAreSafe($attributes['clauses'] ?? []);

            return ContractTemplateVersion::withoutGlobalScopes()->create([
                'company_id' => $locked->company_id,
                'contract_template_id' => $locked->id,
                'version_number' => $number,
                // Blade may compile literal {{...}} text in a PHP source file
                // into an echo expression. Persist only our stable token syntax.
                'body' => $body,
                'clauses' => $attributes['clauses'] ?? null,
                'created_by' => $actor->id,
            ]);
        });
    }

    /** @param array<int, array<string, mixed>> $clauses */
    private function assertClausesAreSafe(array $clauses): void
    {
        if (count($clauses) > ContractPdfContentLimits::TERMS_MAX_COUNT) {
            throw ValidationException::withMessages([
                'clauses' => __('contract_domain.content.too_many_terms', ['limit' => ContractPdfContentLimits::TERMS_MAX_COUNT]),
            ]);
        }

        $totalLength = 0;
        foreach (array_values($clauses) as $index => $clause) {
            $length = mb_strlen((string) ($clause['text'] ?? ''));
            if ($length > ContractPdfContentLimits::TERM_TEXT_MAX_CHARACTERS) {
                throw ValidationException::withMessages([
                    "clauses.{$index}.text" => __('contract_domain.content.term_too_large', ['limit' => number_format(ContractPdfContentLimits::TERM_TEXT_MAX_CHARACTERS)]),
                ]);
            }
            $totalLength += $length;
        }

        if ($totalLength > ContractPdfContentLimits::TERMS_TOTAL_MAX_CHARACTERS) {
            throw ValidationException::withMessages([
                'clauses' => __('contract_domain.content.terms_too_large', ['limit' => number_format(ContractPdfContentLimits::TERMS_TOTAL_MAX_CHARACTERS)]),
            ]);
        }
    }
}
