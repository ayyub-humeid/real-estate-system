<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'email' => 'يجب أن يكون :attribute بريدًا إلكترونيًا صحيحًا.',
    'unique' => 'قيمة :attribute مستخدمة بالفعل.',
    'distinct' => 'يحتوي حقل :attribute على قيمة مكررة.',
    'in' => 'قيمة :attribute المختارة غير صحيحة.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'alpha_dash' => 'يمكن أن يحتوي :attribute على أحرف وأرقام وشرطات فقط.',
    'min' => ['numeric' => 'يجب ألا تقل قيمة :attribute عن :min.', 'string' => 'يجب ألا يقل :attribute عن :min أحرف.'],
    'max' => ['numeric' => 'يجب ألا تزيد قيمة :attribute عن :max.', 'string' => 'يجب ألا يزيد :attribute عن :max أحرف.'],
    'attributes' => [
        'name' => 'الاسم', 'phone' => 'الهاتف', 'email' => 'البريد الإلكتروني',
        'status' => 'الحالة', 'source' => 'المصدر', 'assigned_to' => 'المسؤول',
        'pipeline_id' => 'المسار', 'pipeline_stage_id' => 'المرحلة',
        'listing_id' => 'الإعلان', 'lead_id' => 'العميل المحتمل',
        'budget_min' => 'الحد الأدنى للميزانية', 'budget_max' => 'الحد الأعلى للميزانية',
        'company_id' => 'الشركة', 'code' => 'الرمز', 'label' => 'التسمية',
        'position' => 'الترتيب', 'requirement_type' => 'نوع الشرط',
    ],
];
