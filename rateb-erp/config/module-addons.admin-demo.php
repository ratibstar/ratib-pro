<?php
declare(strict_types=1);

/**
 * Tracked CRM-only preview catalog for exact host admin.rateb.sa.
 * Loaded only after ModuleAddonService preview guards pass.
 * Production config/module-addons.php stays fail-closed.
 *
 * @return array<string, array{name?:string, monthly?:float, yearly?:float, enabled?:bool}>
 */
return [
    'crm' => [
        'name' => 'CRM',
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
    ],
];
