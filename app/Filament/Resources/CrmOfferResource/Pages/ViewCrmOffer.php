<?php

namespace App\Filament\Resources\CrmOfferResource\Pages;

use App\Enums\{OfferApprovalStatus, OfferProposedBySide, OfferRevisionStatus, OfferStatus, ReservationSource};
use App\Filament\Resources\CrmOfferResource;
use App\Filament\Resources\CrmOfferResource\RelationManagers\{OfferApprovalsRelationManager, OfferRevisionsRelationManager};
use App\Filament\Support\OfferPriceFields;
use App\Models\CrmOfferRevision;
use App\Services\OfferNegotiationService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

/**
 * The offer screen speaks in sales words: each button says what the admin is
 * doing with the customer ("Customer accepted this price"), appears only when it
 * applies, and explains why before it asks for confirmation. The technical
 * revision/approval records still exist; they are shown as "Price history".
 */
class ViewCrmOffer extends ViewRecord
{
    protected static string $resource = CrmOfferResource::class;

    private ?CrmOfferRevision $latestRevision = null;
    private ?CrmOfferRevision $sentRevision = null;
    private bool $latestLoaded = false;

    public function getTitle(): string { return $this->record->reference_number; }

    /** One sentence: where this offer stands and what to do next. */
    public function getSubheading(): string|Htmlable|null
    {
        $latest = $this->latest();
        $amount = $latest ? number_format((float) $latest->amount).' '.$latest->currency : null;

        return match (true) {
            $this->record->status === OfferStatus::Accepted => __('sales.offer_next.accepted', ['amount' => $amount]),
            $this->record->status === OfferStatus::Rejected => __('sales.offer_next.rejected'),
            $this->record->status === OfferStatus::Withdrawn => __('sales.offer_next.withdrawn'),
            $this->record->status === OfferStatus::Expired => __('sales.offer_next.expired'),
            $latest === null => __('sales.offer_next.no_price'),
            $latest->status === OfferRevisionStatus::Draft => __('sales.offer_next.draft', ['amount' => $amount]),
            $this->hasPendingApproval() => __('sales.offer_next.waiting_approval', ['amount' => $amount]),
            $this->sent()?->proposed_by_side === OfferProposedBySide::Customer => __('sales.offer_next.customer_proposed', ['amount' => number_format((float) $this->sent()->amount).' '.$this->sent()->currency]),
            $this->sent() !== null => __('sales.offer_next.waiting_customer', ['amount' => number_format((float) $this->sent()->amount).' '.$this->sent()->currency]),
            default => null,
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label(__('crm.actions.edit'))->visible(fn () => $this->isOpen()),
            $this->sendPriceAction(),
            $this->askApprovalAction(),
            $this->acceptOfferAction(),
            $this->sendNewPriceAction(),
            $this->customerCounterAction(),
            $this->convertToReservationAction(),
            Actions\ActionGroup::make([$this->declinedAction(), $this->withdrawAction()])
                ->label(__('sales.offer_actions.more'))->icon('heroicon-m-ellipsis-vertical')->color('gray')->button(),
        ];
    }

    public function getRelationManagers(): array { return [OfferRevisionsRelationManager::class, OfferApprovalsRelationManager::class]; }

    public function infolist(Infolist $infolist): Infolist { return CrmOfferResource::infolist($infolist); }

    // ── State helpers (read once per request) ───────────────────────────────

    private function isOpen(): bool
    {
        return in_array($this->record->status, [OfferStatus::Draft, OfferStatus::Negotiating], true);
    }

    private function latest(): ?CrmOfferRevision
    {
        if (! $this->latestLoaded) {
            $this->latestRevision = $this->record->revisions()->orderByDesc('revision_number')->first();
            $this->sentRevision = $this->record->revisions()->where('status', OfferRevisionStatus::Submitted->value)->orderByDesc('revision_number')->first();
            $this->latestLoaded = true;
        }

        return $this->latestRevision;
    }

    /** The price currently on the table with the customer (the latest one that was sent). */
    private function sent(): ?CrmOfferRevision
    {
        $this->latest();

        return $this->sentRevision;
    }

    private function hasPendingApproval(): bool
    {
        return (bool) $this->sent()?->approvals()->where('status', OfferApprovalStatus::Pending->value)->exists();
    }

    private function hasApproval(): bool
    {
        return (bool) $this->sent()?->approvals()->where('status', OfferApprovalStatus::Approved->value)->exists();
    }

    private function amountLabel(bool $sentPrice = false): string
    {
        $latest = $sentPrice ? $this->sent() : $this->latest();

        return $latest ? number_format((float) $latest->amount).' '.$latest->currency : '—';
    }

    /** Runs a service call and ALWAYS tells the user what happened: success, the exact rule that blocked it, or no permission. */
    private function run(string $title, callable $operation): void
    {
        try {
            $operation();
            $this->record->refresh();
            $this->latestLoaded = false;
            $this->dispatch('offer-changed');
            Notification::make()->success()->title($title)->send();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('crm.offers.errors.action_failed'))
                ->body(collect($exception->errors())->flatten()->first())->persistent()->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('crm.errors.unauthorized'))
                ->body(__('sales.offer_actions.unauthorized_body'))->persistent()->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title(__('sales.offer_actions.unexpected'))
                ->body($exception->getMessage())->persistent()->send();
        }
    }

    // ── Actions ─────────────────────────────────────────────────────────────

    private function sendPriceAction(): Action
    {
        return Action::make('send_price')
            ->label(__('sales.offer_actions.send_price'))
            ->icon('heroicon-o-paper-airplane')->color('info')
            ->requiresConfirmation()
            ->modalHeading(__('sales.offer_actions.send_price'))
            ->modalDescription(fn (): string => __('sales.offer_actions.send_price_description', ['amount' => $this->amountLabel()]))
            ->visible(fn (): bool => $this->isOpen() && $this->latest()?->status === OfferRevisionStatus::Draft && auth()->user()->can('submit', $this->latest()))
            ->action(fn () => $this->run(__('sales.offer_actions.send_price_done'), fn () => app(OfferNegotiationService::class)->submitRevision(auth()->user(), $this->latest())));
    }

    private function askApprovalAction(): Action
    {
        return Action::make('ask_approval')
            ->label(__('sales.offer_actions.ask_approval'))
            ->icon('heroicon-o-user-plus')->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('sales.offer_actions.ask_approval'))
            ->modalDescription(fn (): string => __('sales.offer_actions.ask_approval_description', ['amount' => $this->amountLabel(true)]))
            ->visible(fn (): bool => $this->isOpen() && $this->sent() !== null
                && ! $this->hasPendingApproval() && ! $this->hasApproval() && auth()->user()->can('requestApproval', $this->sent()))
            ->action(fn () => $this->run(__('sales.offer_actions.ask_approval_done'), fn () => app(OfferNegotiationService::class)->requestApproval(auth()->user(), $this->sent())));
    }

    /** One button replaces the old "Confirm agreement" (revision row) and "Accept offer" (header). */
    private function acceptOfferAction(): Action
    {
        return Action::make('accept_offer')
            ->label(__('sales.offer_actions.accept'))
            ->icon('heroicon-o-check-badge')->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('sales.offer_actions.accept'))
            ->modalDescription(fn (): string => __('sales.offer_actions.accept_description', ['amount' => $this->amountLabel(true)]))
            ->modalSubmitActionLabel(__('sales.offer_actions.accept_submit'))
            ->visible(fn (): bool => $this->record->status === OfferStatus::Negotiating
                && $this->sent() !== null && auth()->user()->can('accept', $this->record))
            ->disabled(fn (): bool => $this->hasPendingApproval())
            ->tooltip(fn (): ?string => $this->hasPendingApproval() ? __('sales.offer_actions.accept_blocked_by_approval') : null)
            ->action(fn () => $this->run(__('sales.offer_actions.accept_done'), fn () => app(OfferNegotiationService::class)->confirmAgreement(auth()->user(), $this->sent())));
    }

    /** Our new or revised price. Also the way to add the very first price to an offer created without one. */
    private function sendNewPriceAction(): Action
    {
        return Action::make('send_new_price')
            ->label(fn (): string => $this->latest() ? __('sales.offer_actions.send_new_price') : __('sales.offer_actions.add_first_price'))
            ->icon('heroicon-o-arrow-up-circle')->color('primary')
            ->modalHeading(fn (): string => $this->latest() ? __('sales.offer_actions.send_new_price') : __('sales.offer_actions.add_first_price'))
            ->modalDescription(fn (): string => $this->latest() ? __('sales.offer_actions.send_new_price_description') : __('sales.offer_actions.add_first_price_description'))
            ->modalSubmitActionLabel(__('sales.offer_actions.send_submit'))
            ->visible(fn (): bool => $this->isOpen() && ($this->latest() === null || $this->sent() !== null)
                && auth()->user()->can('createRevision', $this->record) && OfferPriceFields::canSend())
            ->form(fn (): array => OfferPriceFields::schema(fn () => $this->record->transaction_type, OfferPriceFields::defaultsFromRevision($this->latest()), amountRequired: true, withSendNow: false))
            ->action(fn (array $data) => $this->run(__('sales.offer_actions.send_new_price_done'), fn () => app(OfferNegotiationService::class)
                ->createCounteroffer(auth()->user(), $this->record, [...$data, 'proposed_by_side' => OfferProposedBySide::Company->value])));
    }

    private function customerCounterAction(): Action
    {
        return Action::make('customer_counter')
            ->label(__('sales.offer_actions.customer_counter'))
            ->icon('heroicon-o-arrow-path-rounded-square')->color('warning')
            ->modalHeading(__('sales.offer_actions.customer_counter'))
            ->modalDescription(__('sales.offer_actions.customer_counter_description'))
            ->modalSubmitActionLabel(__('sales.offer_actions.customer_counter_submit'))
            ->visible(fn (): bool => $this->isOpen() && $this->sent() !== null
                && auth()->user()->can('createRevision', $this->record) && OfferPriceFields::canSend())
            ->form(fn (): array => OfferPriceFields::schema(fn () => $this->record->transaction_type, OfferPriceFields::defaultsFromRevision($this->latest()), amountRequired: true, withSendNow: false))
            ->action(fn (array $data) => $this->run(__('sales.offer_actions.customer_counter_done'), fn () => app(OfferNegotiationService::class)
                ->createCounteroffer(auth()->user(), $this->record, [...$data, 'proposed_by_side' => OfferProposedBySide::Customer->value])));
    }

    private function declinedAction(): Action
    {
        return Action::make('customer_declined')
            ->label(__('sales.offer_actions.declined'))
            ->icon('heroicon-o-x-circle')->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('sales.offer_actions.declined'))
            ->modalDescription(__('sales.offer_actions.declined_description'))
            ->visible(fn (): bool => $this->isOpen() && auth()->user()->can('close', $this->record))
            ->action(fn () => $this->run(__('sales.offer_actions.declined_done'), fn () => app(OfferNegotiationService::class)->rejectOffer(auth()->user(), $this->record)));
    }

    private function withdrawAction(): Action
    {
        return Action::make('withdraw_offer')
            ->label(__('sales.offer_actions.withdraw'))
            ->icon('heroicon-o-arrow-uturn-left')->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('sales.offer_actions.withdraw'))
            ->modalDescription(__('sales.offer_actions.withdraw_description'))
            ->visible(fn (): bool => $this->isOpen() && auth()->user()->can('withdraw', $this->record))
            ->action(fn () => $this->run(__('sales.offer_actions.withdraw_done'), fn () => app(OfferNegotiationService::class)->withdrawOffer(auth()->user(), $this->record)));
    }

    private function convertToReservationAction(): Action
    {
        return Action::make('convert_to_reservation')
            ->label(__('crm.offers.actions.convert_to_reservation'))
            ->modalHeading(__('crm.offers.actions.convert_to_reservation'))
            ->modalDescription(__('crm.offers.actions.convert_to_reservation_description'))
            ->color('primary')
            ->requiresConfirmation()
            ->modalSubmitActionLabel(__('crm.offers.actions.convert_to_reservation_submit'))
            ->visible(fn (): bool => $this->record->status === OfferStatus::Accepted && auth()->user()->can('convert_offer_to_reservation', $this->record))
            ->action(function (): void {
                $partyId = optional($this->record->opportunity?->lead?->party)->id ?? null;
                $url = \App\Filament\Resources\UnitReservationResource::getUrl('create') . '?' . http_build_query([
                    'source' => ReservationSource::AcceptedOffer->value,
                    'offer_id' => $this->record->id,
                    'party_id' => $partyId,
                ]);
                $this->redirect($url);
            });
    }
}
