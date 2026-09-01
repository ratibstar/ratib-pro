<?php
declare(strict_types=1);

/**
 * LOCAL/STAGING preview overlay for Module Add-on Commerce.
 *
 * Copy to module-addons.local.php in this folder (gitignored).
 * Production config/module-addons.php stays fail-closed (enabled=false, prices=0).
 *
 * Overlay is loaded ONLY when ALL of:
 *   RATIB_MODULE_ADDON_PREVIEW=1
 *   RATEB_ENV or APP_ENV is local | staging | stage | dev | development
 *   process is not production (rateb_is_production() / rateb.sa host)
 *
 * Also set MODULE_ADDON_COMMERCE_ENABLED=1 in the same local/staging process
 * to show locked navigation and billing pages. Do not set these on production.
 *
 * @return array<string, array{name?:string, monthly?:float, yearly?:float, enabled?:bool}>
 */
return [
    'crm' => [
        'name' => 'CRM',
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
        'featured' => true,
        'promo_label' => 'popular',
    ],
];
