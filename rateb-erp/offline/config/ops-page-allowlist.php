<?php

declare(strict_types=1);

/**
 * Phase 14 — Allowlisted enterprise daily-ops pages for offline snapshot browse.
 * Paths are app-route suffixes (matched against location.pathname).
 * Payroll / payments / approvals intentionally omitted.
 * Phase 16B: journal draft browse + form hooks only (no post/reverse/close).
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
        'journal-entries',
        'accounting/platform',
        'accounting/currencies',
        'accounting/tax-codes',
        'accounting/profit-centers',
        'accounting/recurring',
        'accounting/opening-balances',
        'crm',
        'crm/leads',
        'crm/pipeline',
        'crm/tasks',
        'crm/meetings',
        'crm/campaigns',
        'crm/contacts',
        'crm/companies',
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
        ['match' => 'journal-entries/create', 'module' => 'accounting', 'action' => 'journal.create'],
        ['match' => 'journal-entries', 'module' => 'accounting', 'action' => 'journal.update'],
        ['match' => 'accounting/recurring/create', 'module' => 'accounting', 'action' => 'recurring.create'],
        ['match' => 'accounting/opening-balances', 'module' => 'accounting', 'action' => 'opening_balance.create'],
        ['match' => 'crm/leads/create', 'module' => 'crm', 'action' => 'lead.create'],
        ['match' => 'crm/leads', 'module' => 'crm', 'action' => 'lead.update'],
        ['match' => 'crm/tasks', 'module' => 'crm', 'action' => 'task.create'],
        ['match' => 'crm/meetings', 'module' => 'crm', 'action' => 'meeting.create'],
        ['match' => 'crm/campaigns', 'module' => 'crm', 'action' => 'campaign.create'],
        ['match' => 'crm/contacts', 'module' => 'crm', 'action' => 'contact.create'],
        ['match' => 'crm/companies', 'module' => 'crm', 'action' => 'company.create'],
    ],
];
