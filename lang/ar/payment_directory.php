<?php
return [
    'labels' => ['payment' => 'دفعة', 'payments' => 'الدفعات', 'unavailable_record' => 'السجل المرتبط غير متاح', 'original_payment' => 'الدفعة الأصلية'],
    'headings' => ['directory' => 'الدفعات', 'overview' => 'نظرة عامة على الدفعة', 'purpose' => 'غرض الدفعة', 'contract_allocations' => 'توزيعات الأقساط', 'project_allocations' => 'توزيعات تكاليف المشروع'],
    'descriptions' => ['directory' => 'راجع الدفعات الواردة والصادرة عبر العقود والمشاريع. تُسجل الدفعات من مسار العمل الأصلي للحفاظ على التاريخ المالي.', 'summary' => ':total سجل دفعة · :incoming وارد · :outgoing صادر · :pending بانتظار المراجعة'],
    'fields' => ['payment_date' => 'تاريخ الدفعة', 'direction' => 'الاتجاه', 'amount' => 'المبلغ', 'purpose' => 'الغرض', 'party' => 'الطرف', 'status' => 'الحالة', 'reference' => 'الرقم المرجعي', 'source' => 'مصدر الدفعة', 'currency' => 'العملة', 'payment_method' => 'طريقة الدفع', 'recorded_by' => 'سجّلها', 'linked_record' => 'السجل المرتبط', 'reversal_of' => 'عكس للدفعة', 'installment' => 'القسط', 'due_date' => 'تاريخ الاستحقاق', 'allocated_amount' => 'المبلغ الموزع', 'actual_cost' => 'التكلفة الفعلية', 'cost_amount' => 'مبلغ التكلفة', 'project_amount' => 'مبلغ عملة المشروع', 'contract' => 'العقد', 'payer' => 'الدافع', 'installment_to_settle' => 'القسط المطلوب تسديده'],
    'directions' => ['incoming' => 'وارد', 'outgoing' => 'صادر'],
    'statuses' => ['pending' => 'قيد الانتظار', 'completed' => 'مكتملة', 'failed' => 'فشلت', 'voided' => 'ملغاة'],
    'purposes' => ['contract_receipt' => 'إيصال عقد', 'project_cost' => 'دفعة تكلفة مشروع', 'reversal' => 'عكس / استرداد دفعة', 'other' => 'دفعة أخرى'],
    'actions' => ['details' => 'التفاصيل', 'record_payment' => 'تسجيل دفعة', 'save_payment' => 'تسجيل الدفعة'],
    'filters' => ['date_from' => 'مدفوعة من', 'date_until' => 'مدفوعة إلى'],
    'placeholders' => ['choose_contract' => 'ابحث برقم العقد أو الوحدة…', 'choose_payer' => 'اختر الطرف الدافع', 'choose_installment' => 'اختر القسط الذي تغطيه هذه الدفعة'],
    'installment_option' => 'القسط رقم :sequence · المتبقي :amount :currency · يستحق :due',
    'descriptions' => ['record_payment' => 'يسجّل إيصالاً مقابل جدول دفعات عقد مُصدَر. لتوزيع دفعة واحدة على عدة أقساط، استخدم شاشة جدول دفعات العقد.'],
    'help' => [
        'details' => 'يعرض مصدر الدفعة والسجل المرتبط وكل قسط أو تكلفة غطتها هذه الدفعة.',
        'record_payment' => 'سجّل دفعة عميل مقابل قسط عقد دون مغادرة هذه الشاشة.',
        'contract_choice' => 'العقود التي لديها جدول دفعات مُصدَر فقط يمكنها استلام دفعات.',
        'installment_choice' => 'يظهر المبلغ المستحق على كل قسط لتتمكن من اختيار القسط الصحيح.',
        'amount' => 'مُعبأ مسبقاً بكامل المبلغ المتبقي. اخفضه لتسجيل دفعة جزئية.',
        'reference' => 'رقم الحوالة البنكية أو الشيك أو الإيصال. يُستخدم لاحقاً في التسوية.',
    ],
    'notifications' => [
        'payment_recorded' => 'تم تسجيل الدفعة',
        'payment_recorded_body' => 'تم استلام :amount :currency عن :contract. تم تحديث رصيد القسط.',
    ],
    'errors' => [
        'no_issued_schedule_title' => 'لا يمكن لهذا العقد استلام دفعة بعد',
        'no_issued_schedule_body' => 'لم يُصدَر جدول الدفعات الخاص به. افتح العقد ثم أرسل الجدول واعتمده قبل تسجيل الدفعات.',
    ],
    'empty' => ['heading' => 'لا توجد دفعات', 'description' => 'تظهر الدفعات هنا بعد تسجيلها من جدول دفعات عقد أو من مسار تكلفة مشروع.'],
];
