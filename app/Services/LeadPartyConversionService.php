<?php

namespace App\Services;

use App\Models\{Company, Lead, Party, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a Lead into a Party (the client record used by reservations and
 * contracts), linking them through Party::$lead_id.
 *
 * A client who already exists (same phone or email in the same company, for
 * example someone who bought before) is LINKED to the lead instead of being
 * duplicated. Anything unclear (two matches, a client already tied to another
 * lead, an inactive client) is refused with a message, never merged silently.
 *
 * `$party->wasRecentlyCreated` tells the caller whether a new client was created
 * (true) or an existing one was linked (false).
 */
class LeadPartyConversionService
{
    public function convert(User $actor, Lead $lead): Party
    {
        $this->authorize($actor, 'convert', $lead);

        return DB::transaction(function () use ($actor, $lead): Party {
            $locked = Lead::withoutGlobalScopes()->lockForUpdate()->findOrFail($lead->id);

            if ($locked->party()->exists())
                throw ValidationException::withMessages(['lead' => __('crm.errors.already_converted', ['lead' => $locked->name])]);

            $company = Company::withoutGlobalScopes()->findOrFail($locked->company_id);
            if ((int) $actor->company_id !== (int) $company->id && !$actor->isSuperAdmin())
                throw new AuthorizationException;

            if ($existing = $this->existingClient($locked)) {
                $existing->update(['lead_id' => $locked->id]);

                return $existing->refresh();
            }

            return Party::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'type' => 'individual',
                'lead_id' => $locked->id,
                'name' => $locked->name,
                'phone' => $locked->phone,
                'email' => $locked->email,
                'notes' => __('crm.notes.converted_from_lead', ['lead_id' => $locked->id]),
                'is_active' => true,
            ])->refresh();
        });
    }

    /** The client for this lead: the one already linked, or the result of converting it now. */
    public function partyFor(User $actor, Lead $lead): Party
    {
        return $lead->party ?? $this->convert($actor, $lead);
    }

    /** Like partyFor() but skips the "convert" permission check — the caller has already authorized the broader action (e.g. convertToReservation). */
    public function partyForAuthorized(User $actor, Lead $lead): Party
    {
        return $lead->party ?? $this->convertWithoutAuthCheck($actor, $lead);
    }

    private function convertWithoutAuthCheck(User $actor, Lead $lead): Party
    {
        return DB::transaction(function () use ($actor, $lead): Party {
            $locked = Lead::withoutGlobalScopes()->lockForUpdate()->findOrFail($lead->id);

            if ($locked->party()->exists())
                throw ValidationException::withMessages(['lead' => __('crm.errors.already_converted', ['lead' => $locked->name])]);

            $company = Company::withoutGlobalScopes()->findOrFail($locked->company_id);
            if ((int) $actor->company_id !== (int) $company->id && !$actor->isSuperAdmin())
                throw new AuthorizationException;

            if ($existing = $this->existingClient($locked)) {
                $existing->update(['lead_id' => $locked->id]);
                return $existing->refresh();
            }

            return Party::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'type' => 'individual',
                'lead_id' => $locked->id,
                'name' => $locked->name,
                'phone' => $locked->phone,
                'email' => $locked->email,
                'notes' => __('crm.notes.converted_from_lead', ['lead_id' => $locked->id]),
                'is_active' => true,
            ])->refresh();
        });
    }

    private function existingClient(Lead $lead): ?Party
    {
        $phone = $lead->normalized_phone;
        $email = $lead->normalized_email;
        if (! $phone && ! $email) {
            return null;
        }

        $matches = Party::withoutGlobalScopes()
            ->where('company_id', $lead->company_id)
            ->where(fn ($query) => $query
                ->when($phone, fn ($q) => $q->orWhere('normalized_phone', $phone))
                ->when($email, fn ($q) => $q->orWhere('normalized_email', $email)))
            ->get();

        if ($matches->isEmpty()) {
            return null;
        }
        if ($matches->count() > 1) {
            throw ValidationException::withMessages(['lead' => __('sales.party.ambiguous', ['names' => $matches->pluck('name')->implode(', ')])]);
        }

        $party = $matches->first();
        if ($party->lead_id && (int) $party->lead_id !== (int) $lead->id) {
            $other = Lead::withoutGlobalScopes()->find($party->lead_id);
            throw ValidationException::withMessages(['lead' => __('sales.party.linked_to_other_lead', ['client' => $party->name, 'lead' => $other?->name ?? '#'.$party->lead_id])]);
        }
        if (! $party->is_active) {
            throw ValidationException::withMessages(['lead' => __('sales.party.inactive', ['client' => $party->name])]);
        }

        return $party;
    }

    private function authorize(User $actor, string $ability, object $record): void
    {
        if (!$actor->can($ability, $record))
            throw new AuthorizationException;
    }
}
