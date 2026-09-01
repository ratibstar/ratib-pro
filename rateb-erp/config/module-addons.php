<?php
declare(strict_types=1);

/**
 * Server-authoritative Module Add-on Commerce catalog (platform-controlled).
 *
 * Prices are NOT derived from HTTP input. A zero/empty price is not purchasable.
 * Commercial amounts stay unset until configured (fail closed).
 *
 * `enabled` must be true for a slug to be sold. Runtime access is still
 * company.modules → PlanLimitService::companyHasModule() → CompanyModuleMiddleware.
 *
 * Tenants cannot change these values. Overlay on admin.rateb.sa may enable
 * preview prices without changing this production file.
 *
 * @return array<string, array<string, mixed>>
 */
$crmFeatures = [
    ['en' => 'Customer management', 'ar' => 'إدارة العملاء'],
    ['en' => 'Leads & opportunities', 'ar' => 'العملاء المحتملون والفرص'],
    ['en' => 'Sales pipeline', 'ar' => 'مسار المبيعات'],
    ['en' => 'Follow-ups & activity', 'ar' => 'المتابعات والأنشطة'],
    ['en' => 'Reports & analytics', 'ar' => 'التقارير والتحليلات'],
];

return [
    'crm' => [
        'name' => 'CRM',
        'name_ar' => 'إدارة علاقات العملاء',
        'description' => 'Manage customers, opportunities and sales from one powerful workspace.',
        'description_ar' => 'أدر العملاء والفرص والمبيعات من مساحة عمل واحدة.',
        'icon' => 'crm',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'featured' => false,
        'sort_order' => 10,
        'promo_label' => '',
        'features' => $crmFeatures,
    ],
    'pos' => [
        'name' => 'POS',
        'name_ar' => 'نقاط البيع',
        'description' => 'In-store selling, receipts, and register operations.',
        'description_ar' => 'البيع في المتجر والإيصالات وتشغيل الصندوق.',
        'icon' => 'pos',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'featured' => false,
        'sort_order' => 20,
        'features' => [],
    ],
    'hr' => [
        'name' => 'HR',
        'name_ar' => 'الموارد البشرية',
        'icon' => 'hr',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'sort_order' => 30,
    ],
    'recruitment' => [
        'name' => 'Recruitment',
        'name_ar' => 'التوظيف',
        'icon' => 'recruitment',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'sort_order' => 40,
    ],
    'logistics' => [
        'name' => 'Logistics',
        'name_ar' => 'اللوجستيات',
        'icon' => 'logistics',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'sort_order' => 50,
    ],
    'marketplace' => [
        'name' => 'Marketplace',
        'name_ar' => 'السوق',
        'icon' => 'marketplace',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'sort_order' => 60,
    ],
    'manufacturing' => [
        'name' => 'Manufacturing',
        'name_ar' => 'التصنيع',
        'icon' => 'manufacturing',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'sort_order' => 70,
    ],
    'payroll' => [
        'name' => 'Payroll',
        'name_ar' => 'الرواتب',
        'icon' => 'payroll',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'sort_order' => 80,
    ],
    'accounting' => [
        'name' => 'Accounting',
        'name_ar' => 'المحاسبة',
        'icon' => 'accounting',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'sort_order' => 90,
    ],
    'projects' => [
        'name' => 'Projects',
        'name_ar' => 'المشاريع',
        'icon' => 'projects',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'sort_order' => 100,
    ],
    'quality' => [
        'name' => 'Quality',
        'name_ar' => 'الجودة',
        'icon' => 'quality',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'sort_order' => 110,
    ],
    'bi' => [
        'name' => 'BI',
        'name_ar' => 'ذكاء الأعمال',
        'icon' => 'bi',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'sort_order' => 120,
    ],
    'website' => [
        'name' => 'Website',
        'name_ar' => 'الموقع',
        'icon' => 'website',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'sort_order' => 130,
    ],
];
