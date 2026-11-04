<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Party;
use Illuminate\Support\Facades\DB;

/** Resolves the single Party record that legally represents a tenant company. */
class CompanyLegalPartyService
{
    public function resolve(Company $company): Party
    {
        return DB::transaction(function () use ($company): Party {
            $locked = Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($company->id);

            if ($locked->self_party_id) {
                $existing = Party::withoutGlobalScopes()
                    ->whereKey($locked->self_party_id)
                    ->where('company_id', $locked->id)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            // Existing companies may already have a Party for themselves from
            // older ownership or contract data. Reuse that exact legal entity
            // instead of creating a duplicate company party.
            $party = Party::withoutGlobalScopes()
                ->where('company_id', $locked->id)
                ->where('type', 'company')
                ->where('is_active', true)
                ->where(function ($query) use ($locked): void {
                    $query->where('name', $locked->name)
                        ->orWhere('legal_name', $locked->name);
                })
                ->orderBy('id')
                ->first();

            $party ??= Party::withoutGlobalScopes()->create([
                'company_id' => $locked->id,
                'type' => 'company',
                'name' => $locked->name,
                'legal_name' => $locked->name,
                'email' => $locked->email,
                'phone' => $locked->phone,
                'address' => $locked->address,
                'is_active' => true,
            ]);

            $locked->forceFill(['self_party_id' => $party->id])->save();

            return $party;
        });
    }
}
