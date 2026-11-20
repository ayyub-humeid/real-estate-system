<?php

return [
    'fields' => [
        'sale_price' => 'Sale Price',
        'sale_currency' => 'Sale Currency',
        'rent_price' => 'Rent Price',
        'rent_currency' => 'Rent Currency',
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
    'errors' => ['not_marketable' => 'A :status unit cannot be marketed.', 'cannot_change_status' => 'The unit cannot change from :from to :to.', 'manual_reason_required' => 'Enter the reason. Sold and Rented are normally set by a contract, so a manual change must be explained.', 'has_active_contract' => 'This unit has an active contract, which controls its status. End the contract (or amend it) instead of changing the status by hand.', 'has_active_reservation' => 'This unit has an active reservation. Cancel the reservation first, or create the contract from it.'],
];
