<?php

declare(strict_types=1);

/**
 * Defaults for POS V2 register request context resolution.
 */
return [
    'currency' => 'SAR',
    'timezone' => 'Asia/Riyadh',
    'locale' => 'ar',

    /**
     * POS permission slugs evaluated via rateb_can() for the current cashier.
     *
     * @var list<string>
     */
    'permission_slugs' => [
        'pos.view',
        'pos.register',
        'pos.manage',
        'pos.shift.open',
        'pos.shift.close',
        'pos.terminal.manage',
        'pos.orders.view',
        'pos.settings.manage',
        'pos.sync.manage',
        'pos.reports.view',
        'pos.reports.z',
        'pos.cash_drawer.manage',
        'pos.returns.manage',
        'pos.discount.manage',
    ],
];
