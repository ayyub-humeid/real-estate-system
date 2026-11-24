<?php
return [
    'labels' => ['payment' => 'Payment', 'payments' => 'Payments', 'unavailable_record' => 'Linked record is unavailable', 'original_payment' => 'Original payment'],
    'headings' => ['directory' => 'Payments', 'overview' => 'Payment overview', 'purpose' => 'Payment purpose', 'contract_allocations' => 'Installment allocations', 'project_allocations' => 'Project cost allocations'],
    'descriptions' => ['directory' => 'Review incoming and outgoing payments across contracts and projects. Payments are recorded from their original workflow to preserve financial history.', 'summary' => ':total payment records · :incoming incoming · :outgoing outgoing · :pending pending review'],
    'fields' => ['payment_date' => 'Payment date', 'direction' => 'Direction', 'amount' => 'Amount', 'purpose' => 'Purpose', 'party' => 'Party', 'status' => 'Status', 'reference' => 'Reference number', 'source' => 'Payment source', 'currency' => 'Currency', 'payment_method' => 'Payment method', 'recorded_by' => 'Recorded by', 'linked_record' => 'Linked record', 'reversal_of' => 'Reversal of', 'installment' => 'Installment', 'due_date' => 'Due date', 'allocated_amount' => 'Allocated amount', 'actual_cost' => 'Actual cost', 'cost_amount' => 'Cost amount', 'project_amount' => 'Project-currency amount', 'contract' => 'Contract', 'payer' => 'Who paid', 'installment_to_settle' => 'Installment to settle'],
    'directions' => ['incoming' => 'Incoming', 'outgoing' => 'Outgoing'],
    'statuses' => ['pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed', 'voided' => 'Voided'],
    'purposes' => ['contract_receipt' => 'Contract receipt', 'project_cost' => 'Project cost payment', 'reversal' => 'Payment reversal / refund', 'other' => 'Other payment'],
    'actions' => ['details' => 'Details', 'record_payment' => 'Record a payment', 'save_payment' => 'Record payment'],
    'filters' => ['date_from' => 'Paid from', 'date_until' => 'Paid until'],
    'placeholders' => ['choose_contract' => 'Search by contract number or unit…', 'choose_payer' => 'Select the paying party', 'choose_installment' => 'Select which installment this covers'],
    'installment_option' => 'Installment #:sequence · :amount :currency outstanding · due :due',
    'descriptions' => ['record_payment' => 'Records a receipt against an issued contract payment schedule. To split one payment across several installments, use the contract\'s payment schedule screen instead.'],
    'help' => [
        'details' => 'Shows the payment source, linked record, and every installment or cost allocation covered by this payment.',
        'record_payment' => 'Record a customer payment against a contract installment without leaving this screen.',
        'contract_choice' => 'Only contracts with an issued payment schedule can receive a payment.',
        'installment_choice' => 'The amount owed on each installment is shown so you can pick the right one.',
        'amount' => 'Pre-filled with the full outstanding amount. Lower it to record a partial payment.',
        'reference' => 'The bank transfer, cheque or receipt number. Used later for reconciliation.',
    ],
    'notifications' => [
        'payment_recorded' => 'Payment recorded',
        'payment_recorded_body' => ':amount :currency received for :contract. The installment balance has been updated.',
    ],
    'errors' => [
        'no_issued_schedule_title' => 'This contract cannot receive a payment yet',
        'no_issued_schedule_body' => 'Its payment schedule has not been issued. Open the contract, then submit and approve the schedule before recording payments.',
    ],
    'empty' => ['heading' => 'No payments found', 'description' => 'Payments appear here after they are recorded from a Contract payment schedule or a Project cost workflow.'],
];
