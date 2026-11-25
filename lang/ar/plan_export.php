<?php
return [
    'action' => 'تصدير إلى Excel',
    'file_name' => 'plans-:date-:id',
    'columns' => [
        'name' => 'اسم الخطة',
        'price_monthly' => 'السعر الشهري',
        'price_annual' => 'السعر السنوي',
        'trial_days' => 'أيام الاختبار',
        'status' => 'الحالة',
    ],
    'features' => [
        'maintenance_tracking' => 'تتبع الصيانة',
        'rental_requests' => 'طلبات الإيجار',
        'expense_tracking' => 'تتبع النفقات',
        'max_projects' => 'الحد الأقصى للمشاريع',
        'max_properties' => 'الحد الأقصى للعقارات',
        'max_units' => 'الحد الأقصى للوحدات',
        'max_employees' => 'الحد الأقصى للموظفين',
        'max_users' => 'الحد الأقصى للمستخدمين',
        'construction_tracking' => 'تتبع البناء',
        'due_diligence' => 'العناية الواجبة والاستحواذ',
        'advanced_reports' => 'التقارير المتقدمة',
        'export_data' => 'تصدير البيانات إلى Excel',
    ],
    'limits' => [
        'unlimited' => 'غير محدود',
    ],
    'statuses' => [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
    ],
    'boolean' => [
        'yes' => 'نعم',
        'no' => 'لا',
    ],
];
