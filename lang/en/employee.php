<?php

return [
    'resource' => [
        'singular' => 'Employee',
        'plural'   => 'Employees',
    ],
    'sections' => [
        'account' => 'Account information',
        'employment' => 'Employment details',
        'emergency' => 'Emergency contact',
        'additional' => 'Additional information',
    ],
    'fields' => [
        'full_name' => 'Full name',
        'email' => 'Email',
        'phone' => 'Phone',
        'password' => 'Password',
        'system_role' => 'System role',
        'company' => 'Company',
        'photo' => 'Employee photo',
        'employee_number' => 'Employee number',
        'position' => 'Position / job title',
        'department' => 'Department',
        'hire_date' => 'Hire date',
        'salary' => 'Salary',
        'contact_name' => 'Contact name',
        'contact_phone' => 'Contact phone',
        'relationship' => 'Relationship',
        'status' => 'Status',
        'notes' => 'Notes',
    ],
    'departments' => [
        'management' => 'Management',
        'operations' => 'Operations',
        'finance' => 'Finance',
        'maintenance' => 'Maintenance',
        'sales' => 'Sales / leasing',
        'customer_service' => 'Customer service',
        'other' => 'Other',
    ],
    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'on_leave' => 'On leave',
        'terminated' => 'Terminated',
    ],
    'help' => [
        'number_generated' => 'Generated automatically after creation.',
        'login_password' => 'The employee will use this password to sign in.',
        'keep_password' => 'Leave blank to retain the current password.',
    ],
    'notifications' => [
        'created'      => 'Employee created successfully',
        'updated'      => 'Employee updated successfully',
        'limit_reached'=> 'Limit reached',
        'plan_limit'   => 'Your plan does not allow adding more employees.',
        'create_failed'=> 'Could not create employee',
    ],
    'validation' => [
        'role_required' => 'A company role is required.',
    ],
];
