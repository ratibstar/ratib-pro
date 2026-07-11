<?php

declare(strict_types=1);

/**
 * Phase 12 — Offline nav catalog (data only).
 * Filtered at bootstrap with rateb_nav_can / entity-permissions.
 * Modules listed in offline_disabled_modules are never emitted.
 *
 * @return array{
 *   offline_disabled_modules: list<string>,
 *   sections: list<array{title_key: string, icon: string, items: list<array{path: string, label_key: string, icon: string, module?: string, permission?: string}>}>
 * }
 */
return [
    /** Accounting write nav stays disabled; Tier-1 drafts use ops allowlist + SDK. Payroll / payments stay offline-disabled. */
    'offline_disabled_modules' => [
        'accounting',
        'payroll',
        'payments',
        'pos',
    ],

    'sections' => [
        [
            'title_key' => 'dashboard',
            'icon' => 'fa-gauge-high',
            'items' => [
                ['path' => 'dashboard', 'label_key' => 'dashboard', 'icon' => 'fa-gauge-high', 'module' => '', 'permission' => 'dashboard.view'],
            ],
        ],
        [
            'title_key' => 'procurement',
            'icon' => 'fa-cart-shopping',
            'items' => [
                ['path' => 'purchase-requests', 'label_key' => 'purchase_requests', 'icon' => 'fa-file-circle-plus', 'module' => 'procurement'],
                ['path' => 'purchase-orders', 'label_key' => 'purchase_orders', 'icon' => 'fa-file-invoice', 'module' => 'procurement'],
                ['path' => 'rfq', 'label_key' => 'rfq', 'icon' => 'fa-comments-dollar', 'module' => 'procurement'],
                ['path' => 'quotations', 'label_key' => 'quotations', 'icon' => 'fa-file-signature', 'module' => 'procurement'],
            ],
        ],
        [
            'title_key' => 'inventory',
            'icon' => 'fa-boxes-stacked',
            'items' => [
                ['path' => 'inventory', 'label_key' => 'inventory', 'icon' => 'fa-boxes-stacked', 'module' => 'inventory'],
                ['path' => 'warehouses', 'label_key' => 'warehouses', 'icon' => 'fa-warehouse', 'module' => 'inventory'],
                ['path' => 'warehouse-transfers', 'label_key' => 'warehouse_transfers', 'icon' => 'fa-truck-ramp-box', 'module' => 'inventory'],
                ['path' => 'stock-movements', 'label_key' => 'stock_movements', 'icon' => 'fa-arrows-rotate', 'module' => 'inventory'],
            ],
        ],
        [
            'title_key' => 'hr',
            'icon' => 'fa-users',
            'items' => [
                ['path' => 'hr', 'label_key' => 'hr_overview', 'icon' => 'fa-gauge-high', 'module' => 'hr', 'permission' => 'hr.view'],
                ['path' => 'hr/employees', 'label_key' => 'hr_employee_list', 'icon' => 'fa-list', 'module' => 'hr', 'permission' => 'hr.view'],
                ['path' => 'hr/attendance', 'label_key' => 'hr_attendance_daily', 'icon' => 'fa-calendar-check', 'module' => 'hr', 'permission' => 'hr.view'],
                ['path' => 'hr/leaves', 'label_key' => 'hr_leave_requests', 'icon' => 'fa-list', 'module' => 'hr', 'permission' => 'hr.view'],
            ],
        ],
        [
            'title_key' => 'suppliers',
            'icon' => 'fa-truck-field',
            'items' => [
                ['path' => 'suppliers', 'label_key' => 'suppliers', 'icon' => 'fa-truck-field', 'module' => 'suppliers'],
            ],
        ],
        [
            'title_key' => 'account',
            'icon' => 'fa-user-gear',
            'items' => [
                ['path' => 'notifications', 'label_key' => 'notifications', 'icon' => 'fa-bell', 'module' => '', 'permission' => ''],
                ['path' => 'profile', 'label_key' => 'profile', 'icon' => 'fa-user-gear', 'module' => '', 'permission' => ''],
            ],
        ],
    ],
];
