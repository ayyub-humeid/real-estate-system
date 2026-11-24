<?php

namespace App\Services;

use App\Filament\Resources\{ContractResource, CrmOfferResource, LeadResource, OpportunityResource, UnitReservationResource};
use App\Models\{Contract, ContractVersion, CrmOfferApproval, Lead, Opportunity, UnitReservation, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tells the right person about each sales event: approvers when something waits for them,
 * and the person who asked (or owns the deal) when a decision is made. Nothing is sent
 * when the surrounding transaction rolls back, and a failure here never blocks the action.
 */
class SalesNotifier
{
    public function __construct(private WorkflowNotifier $notifier) {}

    // ---- Offers ----

    public function offerApprovalRequested(User $actor, CrmOfferApproval $approval): void
    {
        $this->later(function () use ($actor, $approval): void {
            $revision = $approval->revision()->withoutGlobalScopes()->with('offer')->first();
            if (! $revision) {
                return;
            }
            $this->notifier->send(
                $this->notifier->withPermission((int) $approval->company_id, 'approve_offer_revision', $actor),
                'notifications.offer_approval_requested',
                [
                    'user' => $actor->name,
                    'amount' => $this->money($revision->amount, $revision->currency),
                    'offer' => $revision->offer?->displayNameWithReference() ?? '#' . $revision->offer_id
                ],
                $this->url(CrmOfferResource::class, 'view', $revision->offer_id),
            );
        });
    }

    public function offerApprovalDecided(User $actor, CrmOfferApproval $approval, bool $approved): void
    {
        $this->later(function () use ($actor, $approval, $approved): void {
            $revision = $approval->revision()->withoutGlobalScopes()->with('offer')->first();
            if (! $revision) {
                return;
            }
            $recipients = $this->notifier->users((int) $approval->company_id, [$approval->requested_by, $revision->offer?->assigned_to], $actor);
            $this->notifier->send(
                $recipients,
                $approved ? 'notifications.offer_approval_approved' : 'notifications.offer_approval_rejected',
                ['amount' => $this->money($revision->amount, $revision->currency), 'offer' => $revision->offer?->displayNameWithReference() ?? '#' . $revision->offer_id],
                $this->url(CrmOfferResource::class, 'view', $revision->offer_id),
            );
        });
    }

    // ---- Reservations ----

    public function reservationSentForApproval(User $actor, UnitReservation $reservation): void
    {
        $this->later(fn() => $this->notifier->send(
            $this->notifier->withPermission((int) $reservation->company_id, 'approve_and_activate_reservation', $actor),
            'notifications.reservation_approval_requested',
            ['user' => $actor->name, 'number' => $reservation->reservation_number],
            $this->url(UnitReservationResource::class, 'view', $reservation->id),
        ));
    }

    public function reservationActivated(User $actor, UnitReservation $reservation): void
    {
        $this->later(fn() => $this->notifier->send(
            $this->reservationOwners($reservation, $actor),
            'notifications.reservation_activated',
            ['number' => $reservation->reservation_number, 'date' => $reservation->valid_until?->format('Y-m-d') ?? '—'],
            $this->url(UnitReservationResource::class, 'view', $reservation->id),
        ));
    }

    public function reservationRejected(User $actor, UnitReservation $reservation, ?string $reason): void
    {
        $this->later(fn() => $this->notifier->send(
            $this->reservationOwners($reservation, $actor),
            'notifications.reservation_rejected',
            ['number' => $reservation->reservation_number, 'reason' => filled($reason) ? $reason : '—'],
            $this->url(UnitReservationResource::class, 'view', $reservation->id),
        ));
    }

    public function reservationCancelled(User $actor, UnitReservation $reservation): void
    {
        $this->later(fn() => $this->notifier->send(
            $this->reservationOwners($reservation, $actor),
            'notifications.reservation_cancelled',
            ['number' => $reservation->reservation_number],
            $this->url(UnitReservationResource::class, 'view', $reservation->id),
        ));
    }

    /** `$actor` is null when the scheduler expires the reservation. */
    public function reservationExpired(?User $actor, UnitReservation $reservation): void
    {
        $this->later(fn() => $this->notifier->send(
            $this->reservationOwners($reservation, $actor),
            'notifications.reservation_expired',
            ['number' => $reservation->reservation_number],
            $this->url(UnitReservationResource::class, 'view', $reservation->id),
            null,
            ['dedupe' => 'reservation_expired:' . $reservation->id],
        ));
    }

    /**
     * Warns owners of active reservations that run out within `$days` days. Sent once per
     * reservation and end date, so extending a reservation re-arms the warning.
     */
    public function notifyExpiringSoon(int $days = 2): int
    {
        $count = 0;

        UnitReservation::withoutGlobalScopes()
            ->with(['offer', 'opportunity'])
            ->where('status', 'active')
            ->whereNotNull('valid_until')
            ->where('valid_until', '>', now())
            ->where('valid_until', '<=', now()->addDays($days))
            ->orderBy('id')
            ->each(function (UnitReservation $reservation) use (&$count): void {
                $marker = 'reservation_expiring:' . $reservation->id . ':' . $reservation->valid_until->format('Y-m-d');
                $already = DB::table('notifications')->where('data', 'like', '%' . $marker . '"%')->exists();
                if ($already) {
                    return;
                }

                $sent = $this->notifier->send(
                    $this->reservationOwners($reservation, null),
                    'notifications.reservation_expiring',
                    ['number' => $reservation->reservation_number, 'date' => $reservation->valid_until->format('Y-m-d')],
                    $this->url(UnitReservationResource::class, 'view', $reservation->id),
                    null,
                    ['dedupe' => $marker],
                );
                if ($sent > 0) {
                    $count++;
                }
            });

        return $count;
    }

    // ---- Contracts ----

    public function contractSubmitted(User $actor, ContractVersion $version): void
    {
        $this->later(function () use ($actor, $version): void {
            $contract = Contract::withoutGlobalScopes()->find($version->contract_id);
            if (! $contract) {
                return;
            }
            $this->notifier->send(
                $this->notifier->withPermission((int) $contract->company_id, 'approve_contract_version', $actor),
                'notifications.contract_submitted',
                ['user' => $actor->name, 'number' => $contract->contract_number],
                $this->url(ContractResource::class, 'view', $contract->id),
            );
        });
    }

    public function contractDecided(User $actor, ContractVersion $version, string $event, array $params = []): void
    {
        $this->later(function () use ($actor, $version, $event, $params): void {
            $contract = Contract::withoutGlobalScopes()->find($version->contract_id);
            if (! $contract) {
                return;
            }
            $this->notifier->send(
                $this->notifier->users((int) $contract->company_id, [$contract->created_by, $version->created_by], $actor),
                'notifications.' . $event,
                ['number' => $contract->contract_number] + $params,
                $this->url(ContractResource::class, 'view', $contract->id),
            );
        });
    }

    // ---- Assignment ----

    public function leadAssigned(User $actor, Lead $lead, ?int $previousOwner): void
    {
        $this->assigned($actor, (int) $lead->company_id, $lead->assigned_to, $previousOwner, 'notifications.lead_assigned', ['name' => $lead->name], $this->url(LeadResource::class, 'view', $lead->id));
    }

    public function opportunityAssigned(User $actor, Opportunity $opportunity, ?int $previousOwner): void
    {
        $name = $opportunity->lead?->name ?? '#' . $opportunity->id;
        $this->assigned($actor, (int) $opportunity->company_id, $opportunity->assigned_to, $previousOwner, 'notifications.opportunity_assigned', ['name' => $name], $this->url(OpportunityResource::class, 'view', $opportunity->id));
    }

    // ---- Helpers ----

    private function assigned(User $actor, int $companyId, ?int $owner, ?int $previous, string $event, array $params, ?string $url): void
    {
        if (! $owner || $owner === $previous) {
            return;
        }
        $this->later(fn() => $this->notifier->send($this->notifier->users($companyId, [$owner], $actor), $event, $params, $url));
    }

    /** The people behind a reservation: who submitted it, and who owns its offer or opportunity. */
    private function reservationOwners(UnitReservation $reservation, ?User $except): Collection
    {
        $reservation->loadMissing(['offer', 'opportunity']);

        return $this->notifier->users((int) $reservation->company_id, [
            $reservation->getAttribute('submitted_by'),
            $reservation->getAttribute('created_by'),
            $reservation->offer?->assigned_to,
            $reservation->opportunity?->assigned_to,
        ], $except);
    }

    /** Runs after the transaction commits, and never lets a notification failure reach the action. */
    private function later(callable $send): void
    {
        DB::afterCommit(function () use ($send): void {
            try {
                $send();
            } catch (\Throwable $exception) {
                report($exception);
            }
        });
    }

    private function url(string $resource, string $page, int|string|null $id): ?string
    {
        try {
            return $id ? $resource::getUrl($page, ['record' => $id]) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function money(mixed $amount, ?string $currency): string
    {
        return number_format((float) $amount, 2) . ' ' . ($currency ?? '');
    }
}
