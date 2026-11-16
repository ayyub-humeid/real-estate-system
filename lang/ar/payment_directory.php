<?php
return [
    'labels' => ['payment' => 'دفعة', 'payments' => 'الدفعات', 'unavailable_record' => 'السجل المرتبط غير متاح', 'original_payment' => 'الدفعة الأصلية'],
    'headings' => ['directory' => 'الدفعات', 'overview' => 'نظرة عامة على الدفعة', 'purpose' => 'غرض الدفعة', 'contract_allocations' => 'توزيعات الأقساط', 'project_allocations' => 'توزيعات تكاليف المشروع'],
    'descriptions' => ['directory' => 'راجع الدفعات الواردة والصادرة عبر العقود والمشاريع. تُسجل الدفعات من مسار العمل الأصلي للحفاظ على التاريخ المالي.', 'summary' => ':total سجل دفعة · :incoming وارد · :outgoing صادر · :pending بانتظار المراجعة'],
    'fields' => ['payment_date' => 'تاريخ الدفعة', 'direction' => 'الاتجاه', 'amount' => 'المبلغ', 'purpose' => 'الغرض', 'party' => 'الطرف', 'status' => 'الحالة', 'reference' => 'الرقم المرجعي', 'source' => 'مصدر الدفعة', 'currency' => 'العملة', 'payment_method' => 'طريقة الدفع', 'recorded_by' => 'سجّلها', 'linked_record' => 'السجل المرتبط', 'reversal_of' => 'عكس للدفعة', 'installment' => 'القسط', 'due_date' => 'تاريخ الاستحقاق', 'allocated_amount' => 'المبلغ الموزع', 'actual_cost' => 'التكلفة الفعلية', 'cost_amount' => 'مبلغ التكلفة', 'project_amount' => 'مبلغ عملة المشروع'],
    'directions' => ['incoming' => 'وارد', 'outgoing' => 'صادر'],
    'statuses' => ['pending' => 'قيد الانتظار', 'completed' => 'مكتملة', 'failed' => 'فشلت', 'voided' => 'ملغاة'],
    'purposes' => ['contract_receipt' => 'إيصال عقد', 'project_cost' => 'دفعة تكلفة مشروع', 'reversal' => 'عكس / استرداد دفعة', 'other' => 'دفعة أخرى'],
    'actions' => ['details' => 'التفاصيل'],
    'help' => ['details' => 'يعرض مصدر الدفعة والسجل المرتبط وكل قسط أو تكلفة غطتها هذه الدفعة.'],
    'empty' => ['heading' => 'لا توجد دفعات', 'description' => 'تظهر الدفعات هنا بعد تسجيلها من جدول دفعات عقد أو من مسار تكلفة مشروع.'],
];
