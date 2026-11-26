<?php

return [
    'navigation' => 'Rent Roll',
    'title' => 'Rent Roll',
    'subheading' => 'Active rentals and what each one owes. Rent counts as late the day after it was due.',
    'subheading_with_grace' => 'Active rentals and what each one owes. Rent counts as late :days days after it was due, as set in Company Settings.',
    'nothing_due' => 'Nothing due',
    'schedule_issued' => 'Issued',
    'schedule_missing' => 'No schedule',
    'columns' => [
        'tenant' => 'Tenant',
        'unit' => 'Unit',
        'rent' => 'Rent',
        'next_due' => 'Next due',
        'overdue' => 'Overdue',
        'days_late' => 'Days late',
        'outstanding' => 'Outstanding',
        'ends' => 'Contract ends',
        'schedule' => 'Payment schedule',
    ],
    'filters' => [
        'overdue' => 'Overdue only',
        'due_within' => 'Due within',
        'days' => ':days days',
        'ending_soon' => 'Ending within 60 days',
        'no_schedule' => 'No payment schedule',
        'property' => 'Property',
    ],
    'actions' => [
        'open_contract' => 'Open contract',
    ],
    'empty' => [
        'heading' => 'No active rentals',
        'description' => 'A rental appears here once its lease contract is activated. Rent is recorded on the contract\'s payment schedule.',
    ],
];
