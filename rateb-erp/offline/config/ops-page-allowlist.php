<?php

declare(strict_types=1);

/**
 * Phase 14 — Allowlisted enterprise daily-ops pages for offline snapshot browse.
 * Paths are app-route suffixes (matched against location.pathname).
 * Accounting / payroll / payments / approvals intentionally omitted.
 *
 * @return array{
 *   paths: list<string>,
 *   form_hooks: list<array{match: string, module: string, action: string}>
 * }
 */
return [
    'paths' => [
        'stock-movements',
        'warehouse-transfers',
        'inventory-audits',
        'inventory',
        'warehouses',
        'hr/attendance',
        'hr/leaves',
        'purchase-requests',
        'purchase-orders',
        'rfq',
        'recruitment/candidates',
        'recruitment/agencies',
        'recruitment',
    ],

    /** Narrow form-post hooks (pathname substring → adapter action). */
    'form_hooks' => [
        ['match' => 'stock-movements', 'module' => 'inventory', 'action' => 'stock_movement.create'],
        ['match' => 'warehouse-transfers', 'module' => 'inventory', 'action' => 'warehouse_transfer.create'],
        ['match' => 'inventory-audits', 'module' => 'inventory', 'action' => 'stock_count.create'],
        ['match' => 'hr/attendance/bulk', 'module' => 'hr', 'action' => 'attendance.bulk'],
        ['match' => 'hr/attendance', 'module' => 'hr', 'action' => 'attendance.create'],
        ['match' => 'hr/leaves', 'module' => 'hr', 'action' => 'leave_request.draft'],
        ['match' => 'purchase-requests', 'module' => 'procurement', 'action' => 'purchase_request.draft'],
        ['match' => 'purchase-orders', 'module' => 'procurement', 'action' => 'purchase_order.draft'],
        ['match' => 'rfq', 'module' => 'procurement', 'action' => 'rfq.draft'],
        ['match' => 'recruitment/candidates/create', 'module' => 'recruitment', 'action' => 'candidate.create'],
        ['match' => 'recruitment/candidates', 'module' => 'recruitment', 'action' => 'candidate.update'],
    ],
];
