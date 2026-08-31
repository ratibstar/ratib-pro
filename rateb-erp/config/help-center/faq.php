<?php
declare(strict_types=1);

/**
 * Help Center FAQ entries (global + per-module optional).
 *
 * @return list<array{id:string,module:?string,q_ar:string,q_en:string,a_ar:string,a_en:string,keywords:list<string>}>
 */
return [
    [
        'id' => 'faq-search-help',
        'module' => null,
        'q_ar' => 'كيف أبحث داخل مركز المساعدة؟',
        'q_en' => 'How do I search the Help Center?',
        'a_ar' => 'استخدم مربع البحث في أعلى صفحة مركز المساعدة واكتب بالعربي أو الإنجليزي. تظهر النتائج أثناء الكتابة.',
        'a_en' => 'Use the search box at the top of the Help Center and type in Arabic or English. Results appear as you type.',
        'keywords' => ['بحث', 'search', 'مساعدة'],
    ],
    [
        'id' => 'faq-permissions',
        'module' => null,
        'q_ar' => 'لماذا لا أرى بعض الشروحات؟',
        'q_en' => 'Why am I missing some articles?',
        'a_ar' => 'بعض الشروحات تظهر حسب صلاحياتك (مستخدم / مدير / مسؤول). تواصل مع المسؤول إن احتجت وصولاً إضافياً.',
        'a_en' => 'Some articles are gated by your role (user / manager / admin). Ask your administrator if you need more access.',
        'keywords' => ['صلاحيات', 'permissions', 'دور'],
    ],
    [
        'id' => 'faq-purchase-request',
        'module' => 'purchases',
        'q_ar' => 'ما الفرق بين طلب الشراء وأمر الشراء؟',
        'q_en' => 'What is the difference between a PR and a PO?',
        'a_ar' => 'طلب الشراء هو حاجة داخلية تحتاج موافقة. أمر الشراء هو التزام رسمي مع المورد بعد الاختيار.',
        'a_en' => 'A purchase request is an internal need awaiting approval. A purchase order is a formal commitment to the supplier.',
        'keywords' => ['طلب شراء', 'أمر شراء', 'PR', 'PO'],
    ],
    [
        'id' => 'faq-inventory-audit',
        'module' => 'inventory',
        'q_ar' => 'متى أستخدم الجرد ومتى التسوية؟',
        'q_en' => 'When do I use audit vs adjustment?',
        'a_ar' => 'الجرد يعد الكميات الفعلية. التسوية تصحح الفروقات بعد الجرد أو عند اكتشاف خطأ.',
        'a_en' => 'An audit counts physical stock. An adjustment corrects differences after audit or when an error is found.',
        'keywords' => ['جرد', 'تسوية', 'audit', 'adjustment'],
    ],
    [
        'id' => 'faq-oversight-platform',
        'module' => 'admin-oversight',
        'q_ar' => 'لماذا لا تظهر مراقبة الإدارة في مركز المساعدة على نطاق الشركة؟',
        'q_en' => 'Why is Admin oversight missing from Help on the company host?',
        'a_ar' => 'شروحات مراقبة الإدارة تظهر فقط على منصة rateb.sa لأنها أداة سوبر أدمن للمنصة وليست جزءاً من لوحة الشركة المستأجرة.',
        'a_en' => 'Admin oversight guides appear only on rateb.sa. They are a platform Super Admin tool, not part of the tenant company console.',
        'keywords' => ['مراقبة الإدارة', 'المنصة', 'rateb.sa', 'oversight'],
    ],
];
