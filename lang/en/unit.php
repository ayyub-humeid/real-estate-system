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
    'errors' => ['not_marketable' => 'A :status unit cannot be marketed.', 'cannot_change_status' => 'The unit cannot change from :from to :to.', 'sold_or_rented_by_contract' => 'Sold and Rented are set automatically when a contract is activated or ended. They cannot be chosen by hand.'],
];
