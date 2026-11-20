<?php

/**
 * Sales pipeline wording that explains the "why" of each step to sales admins.
 * Kept in its own group so the dense crm.php arrays stay untouched.
 */
return [
    'fields' => [
        'project' => 'Project',
        'unit' => 'Unit',
        'unit_label' => 'Unit :number — :property',
    ],
    'help' => [
        'opportunity_project' => 'Optional. Choose the project to narrow the unit list.',
        'opportunity_unit' => 'The unit this customer is interested in. It is carried to the offer, the reservation and the contract, so nobody has to pick it again. Only Ready units are listed.',
        'offer_unit' => 'Taken from the opportunity. Change it only if the customer moved to another unit.',
    ],
    'errors' => [
        'unit_project_mismatch' => 'This unit does not belong to the selected project.',
        'unit_not_available' => 'This unit is :status, so it cannot be offered to a new customer. Choose a Ready unit.',
    ],
    'offer' => [
        'price_section' => 'Our price',
        'price_section_help' => 'Record the price you are quoting now. Leave the amount empty to create the offer first and add the price later from the Price history tab.',
        'amount_help' => 'Pre-filled with the unit list price. Change it if you are giving a discount or special terms.',
        'down_payment_help' => 'The part the customer pays up front. It cannot be more than the price.',
        'rent_frequency_help' => 'How often the tenant pays rent.',
        'expires_help' => 'After this date the offer can be marked expired. Leave empty for no deadline.',
        'send_now' => 'Send this price to the customer now',
        'send_now_help' => 'On: the price is recorded as sent and the offer moves to Negotiating. Off: it stays a draft you can still edit.',
    ],
];
