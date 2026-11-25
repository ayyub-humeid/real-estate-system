<?php
return [
    'file_name' => 'expenses-:date-:id',
    'columns' => [
        'date' => 'Date',
        'title' => 'Title',
        'category' => 'Category',
        'amount' => 'Amount',
        'currency' => 'Currency',
        'status' => 'Status',
        'property' => 'Property',
        'unit' => 'Unit',
        'payment_method' => 'Payment method',
        'reference' => 'Reference number',
        'paid_at' => 'Paid on',
        'description' => 'Description',
        'created_by' => 'Created by',
        'record_id' => 'Record ID',
    ],
    'categories' => [
        'maintenance' => 'Maintenance',
        'utilities' => 'Utilities',
        'salaries' => 'Salaries',
        'insurance' => 'Insurance',
        'taxes' => 'Taxes',
        'marketing' => 'Marketing',
        'other' => 'Other',
    ],
    'statuses' => [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ],
];
