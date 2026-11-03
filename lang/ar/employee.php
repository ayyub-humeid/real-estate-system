<?php

return [
    'resource' => [
        'singular' => 'موظف',
        'plural'   => 'الموظفون',
    ],
    'sections' => [
        'account' => 'معلومات الحساب',
        'employment' => 'تفاصيل التوظيف',
        'emergency' => 'جهة اتصال للطوارئ',
        'additional' => 'معلومات إضافية',
    ],
    'fields' => [
        'full_name' => 'الاسم الكامل',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الهاتف',
        'password' => 'كلمة المرور',
        'system_role' => 'الدور في النظام',
        'company' => 'الشركة',
        'photo' => 'صورة الموظف',
        'employee_number' => 'رقم الموظف',
        'position' => 'المسمى الوظيفي',
        'department' => 'الإدارة',
        'hire_date' => 'تاريخ التعيين',
        'salary' => 'الراتب',
        'contact_name' => 'اسم جهة الاتصال',
        'contact_phone' => 'هاتف جهة الاتصال',
        'relationship' => 'صلة القرابة',
        'status' => 'الحالة',
        'notes' => 'ملاحظات',
    ],
    'departments' => [
        'management' => 'الإدارة',
        'operations' => 'العمليات',
        'finance' => 'المالية',
        'maintenance' => 'الصيانة',
        'sales' => 'المبيعات والتأجير',
        'customer_service' => 'خدمة العملاء',
        'other' => 'أخرى',
    ],
    'statuses' => [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'on_leave' => 'في إجازة',
        'terminated' => 'منتهية خدمته',
    ],
    'help' => [
        'number_generated' => 'يُنشأ تلقائيًا بعد الحفظ.',
        'login_password' => 'سيستخدم الموظف كلمة المرور هذه لتسجيل الدخول.',
        'keep_password' => 'اترك الحقل فارغًا للاحتفاظ بكلمة المرور الحالية.',
    ],
    'notifications' => [
        'created'      => 'تم إنشاء الموظف بنجاح',
        'updated'      => 'تم تحديث الموظف بنجاح',
        'limit_reached'=> 'تم بلوغ الحد',
        'plan_limit'   => 'لا تسمح باقتك بإضافة موظفين آخرين.',
        'create_failed'=> 'تعذر إنشاء الموظف',
    ],
    'validation' => [
        'role_required' => 'يجب تحديد دور في الشركة.',
    ],
];
