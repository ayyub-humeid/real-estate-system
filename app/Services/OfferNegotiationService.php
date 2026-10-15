<?php

namespace App\Services;

use App\Enums\{OfferApprovalStatus, OfferPaymentMethod, OfferProposedBySide, OfferRevisionStatus, OfferStatus, OpportunityType, RentFrequency};
use App\Models\{Company, CrmOffer, CrmOfferApproval, CrmOfferNumberSequence, CrmOfferRevision, Listing, Opportunity, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferNegotiationService
{
    public function createOffer(User $actor, array $data): CrmOffer
    {
        $company = $this->company($actor, $data['company_id'] ?? null);
        $this->authorize($actor, 'create', new CrmOffer(['company_id' => $company->id]));
        if (!empty($data['assigned_to']))
            $this->authorize($actor, 'assign', new CrmOffer(['company_id' => $company->id]));

        return DB::transaction(function () use ($actor, $company, $data): CrmOffer {
            Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($company->id);
            $opportunity = $this->owned(Opportunity::class, $data['opportunity_id'] ?? null, $company->id, 'opportunity_id');
            $listing = $this->listing($data['listing_id'] ?? $opportunity->listing_id, $company->id, $opportunity);
            $type = $this->type($data['transaction_type'] ?? $opportunity->type?->value, $opportunity, $listing);
            $assignee = $this->user($data['assigned_to'] ?? null, $company->id, 'assigned_to');

            $offer = new CrmOffer;
            $offer->forceFill([
                'company_id' => $company->id,
                'opportunity_id' => $opportunity->id,
                'listing_id' => $listing?->id,
                'reference_number' => $this->nextReference($company),
                'transaction_type' => $type->value,
                'status' => OfferStatus::Draft->value,
                'assigned_to' => $assignee?->id,
                'created_by' => $actor->id,
            ]);
            $offer->save();
            return $offer;
        });
    }

    public function updateOffer(User $actor, CrmOffer $offer, array $data): CrmOffer
    {
        $this->authorize($actor, 'update', $offer);
        return DB::transaction(function () use ($actor, $offer, $data): CrmOffer {
            $locked = $this->lockedOffer($offer);
            $this->assertOfferOpen($locked);
            $assignee = $this->user($data['assigned_to'] ?? null, $locked->company_id, 'assigned_to');
            if ((int) ($locked->assigned_to ?? 0) !== (int) ($assignee?->id ?? 0))
                $this->authorize($actor, 'assign', $locked);
            $locked->update(['assigned_to' => $assignee?->id]);
            return $locked->refresh();
        });
    }

    public function createDraftRevision(User $actor, CrmOffer $offer, array $data): CrmOfferRevision
    {
        $this->authorize($actor, 'createRevision', $offer);
        return DB::transaction(function () use ($actor, $offer, $data): CrmOfferRevision {
            $locked = $this->lockedOffer($offer);
            $this->assertOfferOpen($locked);
            return $this->createRevision($actor, $locked, $data);
        });
    }

    public function updateDraftRevision(User $actor, CrmOfferRevision $revision, array $data): CrmOfferRevision
    {
        $this->authorize($actor, 'update', $revision);
        return DB::transaction(function () use ($actor, $revision, $data): CrmOfferRevision {
            $locked = CrmOfferRevision::withoutGlobalScopes()->lockForUpdate()->findOrFail($revision->id);
            $offer = $this->lockedOffer($locked->offer);
            if ($locked->status !== OfferRevisionStatus::Draft)
                $this->invalid('revision', __('crm.offers.errors.revision_not_draft'));
            $attributes = $this->revisionAttributes($offer, [...$locked->only($this->revisionInputKeys()), ...$data]);
            $locked->update($attributes);
            return $locked->refresh();
        });
    }

    public function submitRevision(User $actor, CrmOfferRevision $revision): CrmOfferRevision
    {
        $this->authorize($actor, 'submit', $revision);
        return DB::transaction(function () use ($actor, $revision): CrmOfferRevision {
            $locked = CrmOfferRevision::withoutGlobalScopes()->lockForUpdate()->findOrFail($revision->id);
            $offer = $this->lockedOffer($locked->offer);
            $this->assertOfferOpen($offer);
            if ($locked->status !== OfferRevisionStatus::Draft)
                $this->invalid('revision', __('crm.offers.errors.revision_not_draft'));
            if ($locked->expires_at && $locked->expires_at->lt(now()))
                $this->invalid('expires_at', __('crm.offers.errors.expiry_past'));
            $offer->revisions()->where('status', OfferRevisionStatus::Submitted->value)->lockForUpdate()->get()->each(fn(CrmOfferRevision $item) => $item->forceFill(['status' => OfferRevisionStatus::Superseded->value])->save());
            $locked->forceFill(['status' => OfferRevisionStatus::Submitted->value, 'submitted_by' => $actor->id, 'submitted_at' => now()])->save();
            if ($offer->status === OfferStatus::Draft)
                $offer->forceFill(['status' => OfferStatus::Negotiating->value])->save();
            return $locked->refresh();
        });
    }

    public function createCounteroffer(User $actor, CrmOffer $offer, array $data): CrmOfferRevision
    {
        $revision = $this->createDraftRevision($actor, $offer, $data);
        return $this->submitRevision($actor, $revision);
    }

    public function requestApproval(User $actor, CrmOfferRevision $revision): CrmOfferApproval
    {
        $this->authorize($actor, 'requestApproval', $revision);
        return DB::transaction(function () use ($actor, $revision): CrmOfferApproval {
            $locked = CrmOfferRevision::withoutGlobalScopes()->lockForUpdate()->findOrFail($revision->id);
            $this->lockedOffer($locked->offer);
            if ($locked->status !== OfferRevisionStatus::Submitted)
                $this->invalid('revision', __('crm.offers.errors.approval_requires_submitted'));
            if ($locked->approvals()->where('status', OfferApprovalStatus::Pending->value)->lockForUpdate()->exists())
                $this->invalid('approval', __('crm.offers.errors.approval_pending'));
            return CrmOfferApproval::withoutGlobalScopes()->create(['company_id' => $locked->company_id, 'offer_revision_id' => $locked->id, 'requested_by' => $actor->id, 'assigned_approver_id' => null, 'status' => OfferApprovalStatus::Pending->value]);
        });
    }

    public function decideApproval(User $actor, CrmOfferApproval $approval, bool $approved, ?string $notes = null): CrmOfferApproval
    {
        $this->authorize($actor, $approved ? 'approveRevision' : 'rejectRevision', $approval->revision);
        return DB::transaction(function () use ($actor, $approval, $approved, $notes): CrmOfferApproval {
            $locked = CrmOfferApproval::withoutGlobalScopes()->lockForUpdate()->findOrFail($approval->id);
            $revision = CrmOfferRevision::withoutGlobalScopes()->lockForUpdate()->findOrFail($locked->offer_revision_id);
            $this->lockedOffer($revision->offer);
            if ($locked->status !== OfferApprovalStatus::Pending)
                $this->invalid('approval', __('crm.offers.errors.approval_decided'));
            if ($locked->assigned_approver_id && $locked->assigned_approver_id !== $actor->id)
                throw new AuthorizationException;
            $locked->update(['status' => $approved ? OfferApprovalStatus::Approved->value : OfferApprovalStatus::Rejected->value, 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_notes' => $notes]);
            return $locked->refresh();
        });
    }

    public function confirmAgreement(User $actor, CrmOfferRevision $revision): CrmOfferRevision
    {
        $this->authorize($actor, 'confirmAgreement', $revision);
        return DB::transaction(function () use ($actor, $revision): CrmOfferRevision {
            $locked = CrmOfferRevision::withoutGlobalScopes()->lockForUpdate()->findOrFail($revision->id);
            $offer = $this->lockedOffer($locked->offer);
            $this->assertOfferOpen($offer);
            if ($locked->status !== OfferRevisionStatus::Submitted)
                $this->invalid('revision', __('crm.offers.errors.agreement_requires_submitted'));
            $approvals = $locked->approvals()->lockForUpdate()->get();
            if ($approvals->contains(fn(CrmOfferApproval $approval) => $approval->status !== OfferApprovalStatus::Approved))
                $this->invalid('approval', __('crm.offers.errors.approval_required'));
            if ($offer->agreedRevision()->lockForUpdate()->exists())
                $this->invalid('revision', __('crm.offers.errors.agreement_exists'));
            $offer->revisions()->where('id', '!=', $locked->id)
                ->where('status', OfferRevisionStatus::Submitted->value)
                ->get()
                ->each(fn(CrmOfferRevision $item) =>
                    $item->forceFill(['status' => OfferRevisionStatus::Superseded->value])
                        ->save());
            $locked->forceFill([
                'status' => OfferRevisionStatus::Agreed->value,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now()
            ])->save();
            $offer->forceFill(['status' => OfferStatus::Accepted->value, 'closed_at' => now()])->save();
            return $locked->refresh();
        });
    }

    public function withdrawOffer(User $actor, CrmOffer $offer): CrmOffer
    {
        return $this->closeOffer($actor, $offer, OfferStatus::Withdrawn, 'withdraw');
    }

    public function rejectOffer(User $actor, CrmOffer $offer): CrmOffer
    {
        return $this->closeOffer($actor, $offer, OfferStatus::Rejected, 'close');
    }

    public function expireOffer(User $actor, CrmOffer $offer): CrmOffer
    {
        $this->authorize($actor, 'expire', $offer);
        return DB::transaction(function () use ($offer): CrmOffer {
            $locked = $this->lockedOffer($offer);
            $this->assertOfferOpen($locked);
            $active = $locked->revisions()->where('status', OfferRevisionStatus::Submitted->value)->lockForUpdate()->first();
            if (!$active?->expires_at || $active->expires_at->gt(now()))
                $this->invalid('offer', __('crm.offers.errors.expiry_not_reached'));
            $active->forceFill(['status' => OfferRevisionStatus::Expired->value])->save();
            $locked->forceFill(['status' => OfferStatus::Expired->value, 'closed_at' => now()])->save();
            return $locked->refresh();
        });
    }

    private function closeOffer(User $actor, CrmOffer $offer, OfferStatus $status, string $ability): CrmOffer
    {
        $this->authorize($actor, $ability, $offer);
        return DB::transaction(function () use ($offer, $status): CrmOffer {
            $locked = $this->lockedOffer($offer);
            $this->assertOfferOpen($locked);
            $revisionStatus = $status === OfferStatus::Rejected ? OfferRevisionStatus::Rejected : OfferRevisionStatus::Withdrawn;
            $locked->revisions()->where('status', OfferRevisionStatus::Submitted->value)->lockForUpdate()->get()->each(fn(CrmOfferRevision $item) => $item->forceFill(['status' => $revisionStatus->value])->save());
            $locked->forceFill(['status' => $status->value, 'closed_at' => now()])->save();
            return $locked->refresh();
        });
    }

    private function createRevision(User $actor, CrmOffer $offer, array $data): CrmOfferRevision
    {
        $attributes = $this->revisionAttributes($offer, $data);
        $number = $offer->next_revision_number;
        $offer->forceFill(['next_revision_number' => $number + 1])->save();
        $revision = new CrmOfferRevision;
        $revision->forceFill([...$attributes, 'company_id' => $offer->company_id, 'offer_id' => $offer->id, 'revision_number' => $number, 'status' => OfferRevisionStatus::Draft->value, 'recorded_by' => $actor->id]);
        $revision->save();
        return $revision;
    }

    private function revisionAttributes(CrmOffer $offer, array $data): array
    {
        $side = OfferProposedBySide::tryFrom((string) ($data['proposed_by_side'] ?? ''));
        if (!$side)
            $this->invalid('proposed_by_side', __('crm.offers.errors.proposed_side_required'));
        $amount = $this->money($data['amount'] ?? null, 'amount', true);
        $currency = $this->currency($data['currency'] ?? null);
        $downPayment = $this->money($data['down_payment_amount'] ?? null, 'down_payment_amount');
        $deposit = $this->money($data['deposit_amount'] ?? null, 'deposit_amount');
        $paymentMethod = $data['payment_method'] ?? null;
        if (filled($paymentMethod) && ! ($paymentMethod = OfferPaymentMethod::tryFrom((string) $paymentMethod))) {
            $this->invalid('payment_method', __('crm.offers.errors.payment_method_invalid'));
        }
        if ($downPayment !== null && $downPayment > $amount)
            $this->invalid('down_payment_amount', __('crm.offers.errors.down_payment_exceeds'));
        $expiresAt = $this->dateTime($data['expires_at'] ?? null, 'expires_at');
        if ($expiresAt && $expiresAt->lt(now()))
            $this->invalid('expires_at', __('crm.offers.errors.expiry_past'));

        $rentFrequency = $data['rent_frequency'] ?? null;
        $leaseTermValue = $data['lease_term_value'] ?? null;
        $leaseTermUnit = $data['lease_term_unit'] ?? null;
        if ($offer->transaction_type === OpportunityType::Rent) {
            if (!RentFrequency::tryFrom((string) $rentFrequency))
                $this->invalid('rent_frequency', __('crm.offers.errors.rent_terms_required'));
            if (!is_numeric($leaseTermValue) || (int) $leaseTermValue < 1 || blank($leaseTermUnit))
                $this->invalid('lease_term_value', __('crm.offers.errors.rent_terms_required'));
        } else {
            $rentFrequency = $leaseTermValue = $leaseTermUnit = $deposit = null;
        }
        if ($offer->transaction_type === OpportunityType::Rent && !in_array($leaseTermUnit, ['month', 'year'], true))
            $this->invalid('lease_term_unit', __('crm.offers.errors.rent_terms_required'));
        $terms = $data['terms_json'] ?? null;
        if ($terms !== null && !is_array($terms))
            $this->invalid('terms_json', __('crm.offers.errors.terms_invalid'));
        return ['proposed_by_side' => $side->value, 'amount' => $amount, 'currency' => $currency, 'down_payment_amount' => $downPayment, 'payment_method' => $paymentMethod?->value, 'payment_terms' => filled($data['payment_terms'] ?? null) ? trim((string) $data['payment_terms']) : null, 'rent_frequency' => $rentFrequency, 'lease_term_value' => $leaseTermValue ? (int) $leaseTermValue : null, 'lease_term_unit' => $leaseTermUnit, 'deposit_amount' => $deposit, 'terms_json' => $terms, 'notes' => $data['notes'] ?? null, 'expires_at' => $expiresAt];
    }

    private function nextReference(Company $company): string
    {
        $year = (int) now()->format('Y');
        $sequence = CrmOfferNumberSequence::query()->where('company_id', $company->id)->where('year', $year)->lockForUpdate()->first();
        if (!$sequence)
            $sequence = CrmOfferNumberSequence::create(['company_id' => $company->id, 'year' => $year, 'next_number' => 1]);
        $number = $sequence->next_number;
        $sequence->update(['next_number' => $number + 1]);
        return sprintf('OFF-%d-%04d', $year, $number);
    }

    private function lockedOffer(CrmOffer $offer): CrmOffer
    {
        return CrmOffer::withoutGlobalScopes()->lockForUpdate()->findOrFail($offer->id);
    }
    private function assertOfferOpen(CrmOffer $offer): void
    {
        if (in_array($offer->status, [OfferStatus::Accepted, OfferStatus::Rejected, OfferStatus::Withdrawn, OfferStatus::Expired], true))
            $this->invalid('offer', __('crm.offers.errors.offer_terminal'));
    }
    private function type(mixed $value, Opportunity $opportunity, ?Listing $listing): OpportunityType
    {
        $type = OpportunityType::tryFrom((string) $value);
        if (!$type)
            $this->invalid('transaction_type', __('crm.offers.errors.type_required'));
        if ($type !== $opportunity->type || ($listing && $listing->listing_type->value !== $type->value))
            $this->invalid('transaction_type', __('crm.offers.errors.type_mismatch'));
        return $type;
    }
    private function listing(mixed $id, int $companyId, Opportunity $opportunity): ?Listing
    {
        if (!$id)
            return null;
        $listing = $this->owned(Listing::class, $id, $companyId, 'listing_id');
        if ($opportunity->listing_id && (int) $listing->id !== (int) $opportunity->listing_id)
            $this->invalid('listing_id', __('crm.offers.errors.listing_mismatch'));
        return $listing;
    }
    private function company(User $actor, ?int $id): Company
    {
        if ($actor->isSuperAdmin()) {
            if (!$id)
                $this->invalid('company_id', __('crm.errors.company_required'));
            return Company::findOrFail($id);
        }
        if (!$actor->company_id || ($id && (int) $id !== (int) $actor->company_id))
            $this->invalid('company_id', $id ? __('crm.errors.cross_company') : __('crm.errors.company_required'));
        return Company::findOrFail($actor->company_id);
    }
    private function owned(string $class, mixed $id, int $companyId, string $field): object
    {
        if (!$id)
            $this->invalid($field, __('crm.offers.errors.opportunity_required'));
        $record = $class::withoutGlobalScopes()->findOrFail($id);
        if ((int) $record->company_id !== $companyId)
            $this->invalid($field, __('crm.errors.cross_company'));
        return $record;
    }
    private function user(?int $id, int $companyId, string $field): ?User
    {
        if (!$id)
            return null;
        $user = User::withoutGlobalScopes()->findOrFail($id);
        if ((int) $user->company_id !== $companyId)
            $this->invalid($field, __('crm.errors.cross_company'));
        return $user;
    }
    private function money(mixed $value, string $field, bool $required = false): ?float
    {
        if ($value === null || $value === '') {
            if ($required)
                $this->invalid($field, __('crm.offers.errors.amount_required'));
            return null;
        }
        if (!is_numeric($value) || (float) $value < ($required ? 0.01 : 0))
            $this->invalid($field, __('crm.offers.errors.amount_invalid'));
        return (float) $value;
    }
    private function currency(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));
        if (!preg_match('/^[A-Z]{3}$/', $value))
            $this->invalid('currency', __('crm.errors.currency'));
        return $value;
    }
    private function dateTime(mixed $value, string $field): ?Carbon
    {
        if ($value === null || $value === '')
            return null;
        try {
            return $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);
        } catch (\Throwable) {
            $this->invalid($field, __('crm.offers.errors.date_invalid'));
        }
    }
    private function revisionInputKeys(): array
    {
        return ['proposed_by_side', 'amount', 'currency', 'down_payment_amount', 'payment_method', 'payment_terms', 'rent_frequency', 'lease_term_value', 'lease_term_unit', 'deposit_amount', 'terms_json', 'notes', 'expires_at'];
    }
    private function authorize(User $actor, string $ability, object $record): void
    {
        if (!$actor->can($ability, $record))
            throw new AuthorizationException;
    }
    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
