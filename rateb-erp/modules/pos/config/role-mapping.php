<?php
declare(strict_types=1);

/** ERP role slug → POS permission slugs (applied in Phase 2 migration). */
return [
    'company-full-access' => ['pos.*'],
    'branch_manager' => [
        'pos.view', 'pos.register', 'pos.sale.complete', 'pos.shift.open', 'pos.shift.close',
        'pos.orders.view', 'pos.reports.view', 'pos.cash_drawer.manage',
    ],
    'branch_user' => ['pos.view', 'pos.register', 'pos.sale.complete', 'pos.shift.open', 'pos.orders.view'],
    'pos_cashier' => ['pos.view', 'pos.register', 'pos.sale.complete', 'pos.shift.open'],
    'pos_supervisor' => [
        'pos.view', 'pos.register', 'pos.sale.complete', 'pos.shift.open', 'pos.shift.close',
        'pos.discount.manage', 'pos.returns.manage', 'pos.reports.view',
    ],
    'pos_manager' => [
        'pos.view', 'pos.manage', 'pos.register', 'pos.sale.complete', 'pos.terminal.manage',
        'pos.settings.manage', 'pos.devices.manage', 'pos.sync.manage', 'pos.reports.z',
    ],
];
