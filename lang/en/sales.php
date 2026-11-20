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
];
