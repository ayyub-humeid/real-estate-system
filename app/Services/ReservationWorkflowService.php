<?php

namespace App\Services;

use App\Enums\{OfferRevisionStatus, OfferStatus, OpportunityType, ReservationSource, ReservationStatus, RentFrequency, UnitStatus};
use App\Models\{Company, CrmOffer, CrmOfferRevision, Opportunity, Party, Unit, UnitReservation, UnitReservationNumberSequence, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationWorkflowService
{
    public function __construct(private readonly ReservationAvailabilityService $availability, private readonly ReservationSnapshotFactory $snapshots) {}

    public function createDraft(User $actor, array $data): UnitReservation
    {
        $company = $this->company($actor, $data['company_id'] ?? null);
        $this->authorize($actor, 'create', new UnitReservation(['company_id' => $company->id]));

        return DB::transaction(function () use ($actor, $company, $data): UnitReservation {
            Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($company->id);
            $attributes = $this->attributes($company->id, $data);
            return UnitReservation::withoutGlobalScopes()->create([...$attributes, 'company_id' => $company->id, 'reservation_number' => $this->nextNumber($company->id), 'status' => ReservationStatus::Draft->value, 'created_by' => $actor->id]);
        });
    }

    public function updateDraft(User $actor, UnitReservation $reservation, array $data): UnitReservation
    {
        $this->authorize($actor, 'update', $reservation);
        return DB::transaction(function () use ($reservation, $data): UnitReservation {
            $locked = $this->locked($reservation);
            if ($locked->status !== ReservationStatus::Draft) $this->invalid('status', 'draft_required');
            $locked->update($this->attributes($locked->company_id, [...$data, 'source' => $locked->source->value, 'offer_id' => $locked->offer_id, 'offer_revision_id' => $locked->offer_revision_id]));
            return $locked->refresh();
        });
    }

    public function sendForApproval(User $actor, UnitReservation $reservation): UnitReservation
    {
        $this->authorize($actor, 'sendForApproval', $reservation);
        return DB::transaction(function () use ($actor, $reservation): UnitReservation {
            $locked = $this->locked($reservation);
            if ($locked->status !== ReservationStatus::Draft) $this->invalid('status', 'draft_required');
            $this->validateSource($locked);
            $locked->forceFill(['status' => ReservationStatus::Pending->value, 'submitted_by' => $actor->id, 'submitted_at' => now()])->save();
            $this->history($locked, ReservationStatus::Draft, ReservationStatus::Pending, 'send_for_approval', $actor);
            return $locked->refresh();
        });
    }

    public function approveAndActivate(User $actor, UnitReservation $reservation): UnitReservation
    {
        $this->authorize($actor, 'approveAndActivate', $reservation);
        return DB::transaction(function () use ($actor, $reservation): UnitReservation {
            $locked = $this->locked($reservation);
            if ($locked->status !== ReservationStatus::Pending) $this->invalid('status', 'pending_required');
            $unit = Unit::withoutGlobalScopes()->where('company_id', $locked->company_id)->whereKey($locked->unit_id)->lockForUpdate()->firstOrFail();
            $this->assertReservableUnit($unit);
            $this->validateSource($locked);
            $this->availability->assertCanActivate($locked, $unit);
            $payload = $this->snapshots->make($locked->loadMissing(['offer', 'offerRevision']), $unit);
            $locked->snapshot()->create(['company_id' => $locked->company_id, ...$payload, 'created_by' => $actor->id, 'created_at' => now()]);
            $locked->forceFill(['status' => ReservationStatus::Active->value, 'approved_by' => $actor->id, 'activated_at' => now()])->save();
            $this->history($locked, ReservationStatus::Pending, ReservationStatus::Active, 'approve_and_activate', $actor);
            return $locked->fresh(['snapshot', 'unit', 'customer']);
        });
    }

    public function rejectRequest(User $actor, UnitReservation $reservation, ?string $reason = null): UnitReservation
    {
        $this->authorize($actor, 'rejectRequest', $reservation);
        return $this->transition($actor, $reservation, ReservationStatus::Pending, ReservationStatus::Rejected, 'reject_request', ['rejected_by' => $actor->id, 'rejected_at' => now(), 'rejection_reason' => $reason], $reason);
    }
    public function cancelReservation(User $actor, UnitReservation $reservation, ?string $reason = null): UnitReservation
    {
        $this->authorize($actor, 'cancelReservation', $reservation);
        return $this->transition($actor, $reservation, ReservationStatus::Active, ReservationStatus::Cancelled, 'cancel_reservation', ['cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_reason' => $reason], $reason);
    }
    public function extendReservation(User $actor, UnitReservation $reservation, string $validUntil): UnitReservation
    {
        $this->authorize($actor, 'extendReservation', $reservation);
        return DB::transaction(function () use ($actor, $reservation, $validUntil): UnitReservation {
            $locked = $this->locked($reservation);
            if ($locked->status !== ReservationStatus::Active) $this->invalid('status', 'active_required');
            try { $until = \Illuminate\Support\Carbon::parse($validUntil); } catch (\Throwable) { $this->invalid('valid_until', 'valid_until_invalid'); }
            if ($until->lessThanOrEqualTo(now()) || ($locked->valid_until && $until->lessThanOrEqualTo($locked->valid_until))) $this->invalid('valid_until', 'extension_invalid');
            $old = $locked->valid_until?->toISOString(); $locked->update(['valid_until' => $until]);
            $this->history($locked, ReservationStatus::Active, ReservationStatus::Active, 'extend_reservation', $actor, null, ['old_valid_until' => $old, 'new_valid_until' => $until->toISOString()]);
            return $locked->refresh();
        });
    }
    public function expireReservation(User $actor, UnitReservation $reservation): UnitReservation
    {
        $this->authorize($actor, 'expireReservation', $reservation);
        return DB::transaction(function () use ($actor, $reservation): UnitReservation {
            $locked = $this->locked($reservation);
            if ($locked->status !== ReservationStatus::Active) $this->invalid('status', 'active_required');
            if (! $locked->valid_until || $locked->valid_until->isFuture()) $this->invalid('valid_until', 'not_expired');
            $locked->forceFill(['status' => ReservationStatus::Expired->value, 'expired_at' => now()])->save();
            $this->history($locked, ReservationStatus::Active, ReservationStatus::Expired, 'expire_reservation', $actor);
            return $locked->refresh();
        });
    }

    /** Expire due reservations from the scheduler; no user is impersonated. */
    public function expireDueReservations(): int
    {
        $count = 0;
        UnitReservation::withoutGlobalScopes()
            ->where('status', ReservationStatus::Active->value)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$count): void {
                $expired = DB::transaction(function () use ($id): bool {
                    $locked = UnitReservation::withoutGlobalScopes()->lockForUpdate()->find($id);
                    if (! $locked || $locked->status !== ReservationStatus::Active || ! $locked->valid_until || $locked->valid_until->greaterThan(now())) return false;
                    $locked->forceFill(['status' => ReservationStatus::Expired->value, 'expired_at' => now()])->save();
                    $this->history($locked, ReservationStatus::Active, ReservationStatus::Expired, 'expire_reservation', null);
                    return true;
                });
                if ($expired) $count++;
            });
        return $count;
    }

    private function transition(User $actor, UnitReservation $reservation, ReservationStatus $from, ReservationStatus $to, string $action, array $attributes, ?string $reason): UnitReservation
    {
        return DB::transaction(function () use ($actor, $reservation, $from, $to, $action, $attributes, $reason): UnitReservation {
            $locked = $this->locked($reservation); if ($locked->status !== $from) $this->invalid('status', $from->value.'_required');
            $locked->forceFill(['status' => $to->value, ...$attributes])->save(); $this->history($locked, $from, $to, $action, $actor, $reason); return $locked->refresh();
        });
    }

    private function attributes(int $companyId, array $data): array
    {
        $source = ReservationSource::tryFrom((string) ($data['source'] ?? '')); if (! $source) $this->invalid('source', 'source_invalid');
        $party = $this->owned(Party::class, $data['customer_party_id'] ?? null, $companyId, 'customer_party_id');
        if (! $party->is_active) $this->invalid('customer_party_id', 'customer_inactive');
        if ($source === ReservationSource::AcceptedOffer) return $this->acceptedOfferAttributes($companyId, $party, $data);
        if (! empty($data['offer_id']) || ! empty($data['offer_revision_id'])) $this->invalid('source', 'direct_source_links');
        $unit = $this->owned(Unit::class, $data['unit_id'] ?? null, $companyId, 'unit_id'); $this->assertReservableUnit($unit);
        $opportunity = filled($data['opportunity_id'] ?? null) ? $this->owned(Opportunity::class, $data['opportunity_id'], $companyId, 'opportunity_id') : null;
        return $this->commercialAttributes($data, ['source' => $source->value, 'unit_id' => $unit->id, 'customer_party_id' => $party->id, 'opportunity_id' => $opportunity?->id, 'offer_id' => null, 'offer_revision_id' => null]);
    }
    private function acceptedOfferAttributes(int $companyId, Party $party, array $data): array
    {
        $offer = $this->owned(CrmOffer::class, $data['offer_id'] ?? null, $companyId, 'offer_id');
        $revision = $offer->agreedRevision()->withoutGlobalScopes()->first();
        if ($offer->status !== OfferStatus::Accepted || ! $revision || $revision->status !== OfferRevisionStatus::Agreed) $this->invalid('offer_id', 'accepted_offer_required');
        // A negotiated Offer may intentionally be created without a Listing. In
        // that case, the employee must explicitly select the ready physical Unit.
        // A Listing-backed Offer remains authoritative about its own target.
        $unitId = ! $offer->listing
            ? ($data['unit_id'] ?? null)
            : match ($offer->listing->listable_type) {
                'unit' => $offer->listing->listable_id,
                'planned_unit' => Unit::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('planned_unit_id', $offer->listing->listable_id)
                    ->value('id'),
                default => null,
            };
        if (! $unitId) $this->invalid($offer->listing ? 'offer_id' : 'unit_id', 'accepted_offer_unit_required');
        $unit = $this->owned(Unit::class, $unitId, $companyId, 'unit_id'); $this->assertReservableUnit($unit);
        return $this->commercialAttributes(['transaction_type' => $offer->transaction_type->value, 'agreed_amount' => $revision->amount, 'currency' => $revision->currency, 'down_payment_amount' => $revision->down_payment_amount, 'payment_method' => $revision->payment_method, 'payment_terms' => $revision->payment_terms, 'rent_frequency' => $revision->rent_frequency?->value, 'lease_term_value' => $revision->lease_term_value, 'lease_term_unit' => $revision->lease_term_unit, 'deposit_amount' => $revision->deposit_amount, 'terms_json' => $revision->terms_json, 'valid_until' => $data['valid_until'] ?? null], ['source' => ReservationSource::AcceptedOffer->value, 'unit_id' => $unit->id, 'customer_party_id' => $party->id, 'opportunity_id' => $offer->opportunity_id, 'offer_id' => $offer->id, 'offer_revision_id' => $revision->id]);
    }
    private function commercialAttributes(array $data, array $base): array
    {
        $type = OpportunityType::tryFrom((string) ($data['transaction_type'] ?? '')); if (! $type) $this->invalid('transaction_type', 'transaction_type_invalid');
        $amount = (float) ($data['agreed_amount'] ?? 0); if ($amount <= 0) $this->invalid('agreed_amount', 'amount_required');
        $currency = strtoupper((string) ($data['currency'] ?? '')); if (! preg_match('/^[A-Z]{3}$/', $currency)) $this->invalid('currency', 'currency_invalid');
        $frequency = filled($data['rent_frequency'] ?? null) ? RentFrequency::tryFrom((string) $data['rent_frequency']) : null;
        if ($type === OpportunityType::Rent && (! $frequency || (int) ($data['lease_term_value'] ?? 0) < 1 || ! in_array($data['lease_term_unit'] ?? null, ['month', 'year'], true))) $this->invalid('rent_frequency', 'rent_terms_required');
        if ($type === OpportunityType::Sale) { $frequency = null; $data['lease_term_value'] = null; $data['lease_term_unit'] = null; $data['deposit_amount'] = null; }
        if (filled($data['valid_until'] ?? null)) {
            try { $validUntil = \Illuminate\Support\Carbon::parse($data['valid_until']); } catch (\Throwable) { $this->invalid('valid_until', 'valid_until_invalid'); }
            if ($validUntil->lessThanOrEqualTo(now())) $this->invalid('valid_until', 'valid_until_future');
        }
        return [...$base, 'transaction_type' => $type->value, 'agreed_amount' => $amount, 'currency' => $currency, 'down_payment_amount' => filled($data['down_payment_amount'] ?? null) ? $data['down_payment_amount'] : null, 'payment_method' => $data['payment_method'] ?? null, 'payment_terms' => $data['payment_terms'] ?? null, 'rent_frequency' => $frequency?->value, 'lease_term_value' => $data['lease_term_value'] ?? null, 'lease_term_unit' => $data['lease_term_unit'] ?? null, 'deposit_amount' => $data['deposit_amount'] ?? null, 'terms_json' => $data['terms_json'] ?? null, 'valid_until' => $data['valid_until'] ?? null];
    }
    private function validateSource(UnitReservation $reservation): void { $data = $reservation->getAttributes(); $data['source'] = $reservation->source->value; $this->attributes($reservation->company_id, $data); }
    private function assertReservableUnit(Unit $unit): void { if ($unit->status !== UnitStatus::Ready->value) $this->invalid('unit_id', 'unit_not_reservable'); }
    private function company(User $actor, ?int $companyId): Company { $id = $actor->isSuperAdmin() ? $companyId : $actor->company_id; if (! $id) $this->invalid('company_id', 'company_required'); return Company::withoutGlobalScopes()->findOrFail($id); }
    private function owned(string $class, ?int $id, int $companyId, string $field): mixed { if (! $id) $this->invalid($field, 'required'); $record = $class::withoutGlobalScopes()->findOrFail($id); if ((int) $record->company_id !== $companyId) $this->invalid($field, 'cross_company'); return $record; }
    private function locked(UnitReservation $reservation): UnitReservation { return UnitReservation::withoutGlobalScopes()->lockForUpdate()->findOrFail($reservation->id); }
    private function nextNumber(int $companyId): string { $year = (int) now()->format('Y'); $sequence = UnitReservationNumberSequence::query()->where(['company_id' => $companyId, 'year' => $year])->lockForUpdate()->first(); if (! $sequence) { UnitReservationNumberSequence::create(['company_id' => $companyId, 'year' => $year, 'next_number' => 2]); $number = 1; } else { $number = $sequence->next_number; $sequence->increment('next_number'); } return sprintf('RES-%d-%06d', $year, $number); }
    private function history(UnitReservation $reservation, ?ReservationStatus $from, ReservationStatus $to, string $action, ?User $actor, ?string $reason = null, ?array $metadata = null): void { $reservation->statusHistories()->create(['company_id' => $reservation->company_id, 'from_status' => $from?->value, 'to_status' => $to->value, 'action' => $action, 'reason' => $reason, 'metadata' => $metadata, 'changed_by' => $actor?->id, 'created_at' => now()]); }
    private function authorize(User $user, string $ability, object $record): void { if (! $user->can($ability, $record)) throw new AuthorizationException; }
    private function invalid(string $field, string $key): never { throw ValidationException::withMessages([$field => __('reservations.errors.'.$key)]); }
}
