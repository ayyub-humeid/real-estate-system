<?php

namespace App\Services;

use App\Models\{Company, CrmPipeline, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrmPipelineService
{
    public function create(User $actor, array $data): CrmPipeline
    {
        $company = $this->company($actor, $data['company_id'] ?? null);
        if (! $actor->can('create', new CrmPipeline(['company_id' => $company->id]))) throw new AuthorizationException;

        return DB::transaction(function () use ($company, $data) {
            Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($company->id);
            CrmPipeline::withoutGlobalScopes()->where('company_id', $company->id)->lockForUpdate()->get();
            $name = $this->validatedName($company->id, $data['name'] ?? null);
            if (! empty($data['is_default'])) CrmPipeline::withoutGlobalScopes()->where('company_id', $company->id)->update(['is_default' => false]);
            $pipeline = CrmPipeline::withoutGlobalScopes()->create([
                'company_id' => $company->id, 'name' => $name,
                'is_default' => (bool) ($data['is_default'] ?? false), 'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if ($pipeline->is_default) {
                app(CrmPipelineProvisioningService::class)->ensureDefaultStages($pipeline);
            }

            return $pipeline->refresh();
        });
    }

    public function update(User $actor, CrmPipeline $pipeline, array $data): CrmPipeline
    {
        $company = $this->recordCompany($actor, $pipeline);
        if (! $actor->can('update', $pipeline)) throw new AuthorizationException;

        return DB::transaction(function () use ($company, $pipeline, $data) {
            Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($company->id);
            $locked = CrmPipeline::withoutGlobalScopes()->lockForUpdate()->findOrFail($pipeline->id);
            $name = $this->validatedName($company->id, $data['name'] ?? null, $locked->id);
            if (! empty($data['is_default'])) CrmPipeline::withoutGlobalScopes()->where('company_id', $company->id)->whereKeyNot($locked->id)->update(['is_default' => false]);
            $locked->update(['name' => $name, 'is_default' => (bool) ($data['is_default'] ?? false), 'is_active' => (bool) ($data['is_active'] ?? true)]);

            if ($locked->is_default) {
                app(CrmPipelineProvisioningService::class)->ensureDefaultStages($locked);
            }

            return $locked->refresh();
        });
    }

    private function company(User $actor, ?int $companyId): Company
    {
        if ($actor->isSuperAdmin()) {
            if (! $companyId) throw ValidationException::withMessages(['company_id' => __('crm.errors.company_required')]);
            return Company::findOrFail($companyId);
        }
        if (! $actor->company_id || ($companyId && (int) $companyId !== (int) $actor->company_id)) throw ValidationException::withMessages(['company_id' => __('crm.errors.cross_company')]);
        return Company::findOrFail($actor->company_id);
    }

    private function recordCompany(User $actor, CrmPipeline $pipeline): Company
    {
        if (! $actor->isSuperAdmin() && (int) $actor->company_id !== (int) $pipeline->company_id) throw new AuthorizationException;
        return Company::findOrFail($pipeline->company_id);
    }

    private function validatedName(int $companyId, mixed $value, ?int $exceptId = null): string
    {
        $name = trim((string) $value);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => __('validation.required', ['attribute' => __('crm.fields.name')])]);
        }

        $exists = CrmPipeline::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => __('crm.errors.pipeline_name_taken')]);
        }

        return $name;
    }
}
