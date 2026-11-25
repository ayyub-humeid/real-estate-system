<?php
return [
    'action' => 'Export to Excel',
    'file_name' => 'plans-:date-:id',
    'columns' => [
        'name' => 'Plan name',
        'price_monthly' => 'Monthly price',
        'price_annual' => 'Annual price',
        'trial_days' => 'Trial days',
        'status' => 'Status',
    ],
    'features' => [
        'maintenance_tracking' => 'Maintenance tracking',
        'rental_requests' => 'Rental requests',
        'expense_tracking' => 'Expense tracking',
        'max_projects' => 'Max projects',
        'max_properties' => 'Max properties',
        'max_units' => 'Max units',
        'max_employees' => 'Max employees',
        'max_users' => 'Max users',
        'construction_tracking' => 'Construction tracking',
        'due_diligence' => 'Due diligence & acquisition',
        'advanced_reports' => 'Advanced reports',
        'export_data' => 'Export data to Excel',
    ],
    'limits' => [
        'unlimited' => 'Unlimited',
    ],
    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],
    'boolean' => [
        'yes' => 'Yes',
        'no' => 'No',
    ],
];
