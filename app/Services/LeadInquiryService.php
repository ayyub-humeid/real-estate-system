<?php

namespace App\Services;

use App\Enums\CrmInquirySource;
use App\Models\{Lead, LeadInquiry, Listing, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadInquiryService
{
    public function create(User $actor, Lead $lead, array $data): LeadInquiry
    {
        $this->assertLeadAccess($actor, $lead);

        if (! $actor->can('create', new LeadInquiry(['company_id' => $lead->company_id]))) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $lead, $data): LeadInquiry {
            $lockedLead = Lead::withoutGlobalScopes()->lockForUpdate()->findOrFail($lead->id);

            return LeadInquiry::withoutGlobalScopes()->create([
                'company_id' => $lockedLead->company_id,
                'lead_id' => $lockedLead->id,
                'listing_id' => $this->listingId($lockedLead, $data['listing_id'] ?? null),
                'source' => $this->source($data['source'] ?? null),
                'contact_result' => $data['contact_result'] ?? null,
                'message' => $data['message'] ?? null,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function update(User $actor, LeadInquiry $inquiry, array $data): LeadInquiry
    {
        if (! $actor->can('update', $inquiry)) {
            throw new AuthorizationException;
        }
        if (! $actor->isSuperAdmin() && (int) $actor->company_id !== (int) $inquiry->company_id) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($inquiry, $data): LeadInquiry {
            $locked = LeadInquiry::withoutGlobalScopes()->lockForUpdate()->findOrFail($inquiry->id);
            $lead = Lead::withoutGlobalScopes()->findOrFail($locked->lead_id);

            $locked->update([
                'listing_id' => $this->listingId($lead, $data['listing_id'] ?? null),
                'source' => $this->source($data['source'] ?? null),
                'contact_result' => $data['contact_result'] ?? null,
                'message' => $data['message'] ?? null,
            ]);

            return $locked->refresh();
        });
    }

    private function listingId(Lead $lead, mixed $listingId): ?int
    {
        if (! filled($listingId)) {
            return null;
        }

        $listing = Listing::withoutGlobalScopes()->findOrFail($listingId);
        if ((int) $listing->company_id !== (int) $lead->company_id) {
            throw ValidationException::withMessages(['listing_id' => __('crm.errors.cross_company')]);
        }

        return $listing->id;
    }

    private function assertLeadAccess(User $actor, Lead $lead): void
    {
        if (! $actor->isSuperAdmin() && (int) $actor->company_id !== (int) $lead->company_id) {
            throw new AuthorizationException;
        }
    }

    private function source(mixed $value): string
    {
        $source = CrmInquirySource::tryFrom((string) $value);
        if (! $source) {
            throw ValidationException::withMessages(['source' => __('crm.errors.source_invalid')]);
        }

        return $source->value;
    }
}
