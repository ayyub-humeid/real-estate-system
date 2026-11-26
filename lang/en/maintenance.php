<?php

return [
    'navigation' => 'Maintenance Management',
    'help' => [
        'unit' => 'A Ready unit moves to Maintenance when the request is saved. Rented, sold and reserved units keep their status.',
        'status' => 'Resolving or cancelling the last open request returns a unit that was moved automatically to Ready.',
        'completed_at' => 'Filled in automatically when the request is resolved.',
    ],
    'unit_notice' => [
        'held' => ['title' => 'Unit :unit is now under maintenance', 'body' => 'It cannot be reserved meanwhile. It returns to Ready automatically once every open request is resolved or cancelled.'],
        'already_in_maintenance' => ['title' => 'Unit :unit was already under maintenance', 'body' => 'Its status was set by a person, so it will not change automatically. Set it back to Ready on the unit screen when the work is finished.'],
        'not_moved' => ['title' => 'Unit :unit stays :status', 'body' => 'Only a Ready unit is moved to Maintenance automatically. Arrange access with the people concerned, or change the status on the unit screen if appropriate.'],
        'released' => ['title' => 'Unit :unit is Ready', 'body' => 'It has no maintenance work holding it and can be reserved, sold or rented.'],
        'other_requests_open' => ['title' => 'Unit :unit stays under maintenance', 'body' => 'It still has other open maintenance requests.'],
        'set_by_person' => ['title' => 'Unit :unit stays under maintenance', 'body' => 'Its status was set by a person. Set it back to Ready on the unit screen when it is available.'],
    ],
    'widgets'   => [
        'maintenance_units' => 'Under Maintenance',
        'under_repair'      => 'Units under repair',
    ],
];
