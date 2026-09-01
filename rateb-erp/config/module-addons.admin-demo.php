<?php
declare(strict_types=1);

/**
 * Tracked CRM-only preview catalog for exact host admin.rateb.sa.
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
];
