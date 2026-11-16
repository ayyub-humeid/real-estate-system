<?php
return [
    'labels' => ['payment' => 'Payment', 'payments' => 'Payments', 'unavailable_record' => 'Linked record is unavailable', 'original_payment' => 'Original payment'],
    'headings' => ['directory' => 'Payments', 'overview' => 'Payment overview', 'purpose' => 'Payment purpose', 'contract_allocations' => 'Installment allocations', 'project_allocations' => 'Project cost allocations'],
    'descriptions' => ['directory' => 'Review incoming and outgoing payments across contracts and projects. Payments are recorded from their original workflow to preserve financial history.', 'summary' => ':total payment records · :incoming incoming · :outgoing outgoing · :pending pending review'],
    'fields' => ['payment_date' => 'Payment date', 'direction' => 'Direction', 'amount' => 'Amount', 'purpose' => 'Purpose', 'party' => 'Party', 'status' => 'Status', 'reference' => 'Reference number', 'source' => 'Payment source', 'currency' => 'Currency', 'payment_method' => 'Payment method', 'recorded_by' => 'Recorded by', 'linked_record' => 'Linked record', 'reversal_of' => 'Reversal of', 'installment' => 'Installment', 'due_date' => 'Due date', 'allocated_amount' => 'Allocated amount', 'actual_cost' => 'Actual cost', 'cost_amount' => 'Cost amount', 'project_amount' => 'Project-currency amount'],
    'directions' => ['incoming' => 'Incoming', 'outgoing' => 'Outgoing'],
    'statuses' => ['pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed', 'voided' => 'Voided'],
    'purposes' => ['contract_receipt' => 'Contract receipt', 'project_cost' => 'Project cost payment', 'reversal' => 'Payment reversal / refund', 'other' => 'Other payment'],
    'actions' => ['details' => 'Details'],
    'help' => ['details' => 'Shows the payment source, linked record, and every installment or cost allocation covered by this payment.'],
    'empty' => ['heading' => 'No payments found', 'description' => 'Payments appear here after they are recorded from a Contract payment schedule or a Project cost workflow.'],
];
