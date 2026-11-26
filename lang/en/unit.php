<?php

return [
    'fields' => [
        'sale_price' => 'Sale Price',
        'sale_currency' => 'Sale Currency',
        'rent_price' => 'Rent Price',
        'rent_currency' => 'Rent Currency',
    ],
    'notes' => [
        'label' => 'Notes',
        'section' => 'Internal notes',
        'placeholder' => 'Anything your team should remember about this unit, e.g. corner unit, sea view, owner prefers calls after 5pm.',
        'help' => 'Only your team sees these notes. They are never shown to customers.',
        'empty' => 'No notes yet.',
        'saved' => 'Notes saved',
        'save_failed' => 'Could not save the notes',
        'read_only' => 'You can read these notes but not change them.',
    ],
    'statuses' => ['draft' => 'Draft', 'ready' => 'Ready', 'reserved' => 'Reserved', 'maintenance' => 'Maintenance', 'inactive' => 'Inactive', 'sold' => 'Sold', 'rented' => 'Rented'],
    'status_descriptions' => [
        'draft' => 'Being set up. Not available to customers yet.',
        'ready' => 'Available. It can be reserved, sold or rented.',
        'reserved' => 'Held for one customer until the reservation date. No one else can reserve it.',
        'maintenance' => 'Temporarily unavailable because of works or repairs.',
        'inactive' => 'Switched off. Not offered to customers.',
        'sold' => 'Sold through an activated sale contract. This is final.',
        'rented' => 'Rented through an activated lease contract. It becomes Ready again when the contract is ended.',
    ],
    'help' => ['manual_status' => 'Use Sold or Rented by hand only for deals made outside the system. A reason is required, and it is refused while an active contract or reservation holds the unit.'],
    'history_reasons' => [
        'reserved_by_activation' => 'Reservation activated',
        'released_on_cancellation_or_expiry' => 'Reservation cancelled or expired',
        'sold_by_contract' => 'Sale contract activated',
        'rented_by_contract' => 'Lease contract activated',
        'released_by_contract_end' => 'Lease contract ended',
        'maintenance_request_opened' => 'Maintenance request opened (automatic)',
        'maintenance_requests_closed' => 'All maintenance requests closed (automatic)',
    ],
    'errors' => ['not_marketable' => 'A :status unit cannot be marketed.', 'cannot_change_status' => 'The unit cannot change from :from to :to.', 'manual_reason_required' => 'Enter the reason. Sold and Rented are normally set by a contract, so a manual change must be explained.', 'has_active_contract' => 'This unit has an active contract, which controls its status. End the contract (or amend it) instead of changing the status by hand.', 'has_active_reservation' => 'This unit has an active reservation. Cancel the reservation first, or create the contract from it.'],
];
