<?php

return [
    'fields' => [
        'total_land_area_m2' => 'إجمالي مساحة الأرض (م²)',
        'title_deed_number' => 'رقم صك الملكية',
        'seller_party' => 'البائع (الطرف)',
        'latitude' => 'خط العرض',
        'longitude' => 'خط الطول',
        'location_details' => 'تفاصيل الموقع',
        'project_name' => 'اسم المشروع',
        'project_type' => 'نوع المشروع',
        'seed_budget' => 'إنشاء ميزانية أولية',
        'seed_budget_help' => 'إنشاء بند ميزانية "تكلفة الأرض" بقيمة الشراء المتفق عليها',
    ],
    'project_types' => [
        'residential' => 'سكني',
        'commercial' => 'تجاري',
        'mixed_use' => 'متعدد الاستخدامات',
        'land_development' => 'تطوير أراضي',
    ],
    'actions' => [
        'convert_to_project' => 'تحويل إلى مشروع',
    ],
    'modals' => [
        'convert_heading' => 'تحويل الاستحواذ إلى مشروع',
        'convert_description' => 'سيتم إنشاء مشروع جديد من بيانات هذا الاستحواذ المكتمل. سيتم ربط خصائص الأرض بالمشروع، ويمكن اختياريًا إنشاء ميزانية أولية مع بند "تكلفة الأرض".',
        'convert_submit' => 'نعم، تحويل',
    ],
    'budget' => [
        'initial_budget_name' => 'ميزانية أولية - ',
        'land_cost_category' => 'تكلفة الأرض',
        'land_purchase_item' => 'شراء الأرض',
    ],
    'notifications' => [
        'conversion_complete' => 'تم التحويل بنجاح',
        'conversion_body' => 'تم إنشاء المشروع ":name" (ID: :project_id) من هذا الاستحواذ.',
    ],
];