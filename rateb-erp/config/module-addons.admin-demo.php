<?php
declare(strict_types=1);

/**
 * Tracked preview catalog for exact host admin.rateb.sa.
 * Merged onto config/module-addons.php after preview guards pass.
 * Production catalog stays fail-closed (enabled false, prices 0).
 *
 * @return array<string, array<string, mixed>>
 */
return [
    'crm' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
        'featured' => true,
        'promo_label' => 'popular',
        'sort_order' => 10,
    ],
    'pos' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
    'hr' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
    'recruitment' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
    'logistics' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
    'marketplace' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
    'manufacturing' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
    'payroll' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
    'accounting' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
    'projects' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
    'quality' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
    'bi' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
    'website' => [
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
];
