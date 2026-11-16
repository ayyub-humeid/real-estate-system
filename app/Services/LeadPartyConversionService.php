<?php

namespace App\Services;

use App\Models\{Company, Lead, Party, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Converts a Lead into a Party (customer/contact record), linking them via
 * Party::$lead_id so duplicates cannot be created from the same Lead.
 */
class LeadPartyConversionService
{
    public function convert(User $actor, Lead $lead): Party
    {
        $this->authorize($actor, 'convert', $lead);

        return DB::transaction(function () use ($actor, $lead): Party {
            if ($lead->party)
                throw ValidationException::withMessages(['lead' => __('crm.errors.already_converted', ['lead' => $lead->name])]);

            $company = Company::withoutGlobalScopes()->findOrFail($lead->company_id);
            if ((int) $actor->company_id !== (int) $company->id && !$actor->isSuperAdmin())
                throw new AuthorizationException;

            $party = Party::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'type' => 'individual',
                'lead_id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'notes' => __('crm.notes.converted_from_lead', ['lead_id' => $lead->id]),
                'is_active' => true,
            ]);

            return $party->refresh();
        });
    }

    private function authorize(User $actor, string $ability, object $record): void
    {
        if (!$actor->can($ability, $record))
            throw new AuthorizationException;
    }
}
