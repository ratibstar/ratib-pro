<?php

declare(strict_types=1);

/**
 * Entity → API route mapping for offline replay (Phase 4).
 * Inventory + HR replay delegate to existing domain; Procurement still stubbed.
 */
return [
    'offline_ack' => [
        'module' => 'offline_meta',
        'method' => 'POST',
        'path' => null,
        'replay' => 'noop',
    ],
    'pos_checkout' => [
        'module' => 'pos',
        'method' => 'POST',
        'path' => '/api/v1/pos/register/checkout',
        'replay' => 'delegate_pos',
    ],
    'inventory_stock_movement' => [
        'module' => 'inventory',
        'method' => 'POST',
        'path' => null,
        'replay' => 'delegate_inventory',
        'action' => 'stock_movement.create',
    ],
    'inventory_stock_count' => [
        'module' => 'inventory',
        'method' => 'POST',
        'path' => null,
        'replay' => 'delegate_inventory',
        'action' => 'stock_count.create',
    ],
    'inventory_warehouse_transfer' => [
        'module' => 'inventory',
        'method' => 'POST',
        'path' => null,
        'replay' => 'delegate_inventory',
        'action' => 'warehouse_transfer.create',
    ],
    'inventory_catalog' => [
        'module' => 'inventory',
        'method' => 'GET',
        'path' => '/api/v1/offline/delta/inventory_catalog',
        'replay' => 'delta_pull',
    ],
    'hr_attendance' => [
        'module' => 'hr',
        'method' => 'POST',
        'path' => null,
        'replay' => 'delegate_hr',
        'action' => 'attendance.create',
    ],
    'hr_attendance_bulk' => [
        'module' => 'hr',
        'method' => 'POST',
        'path' => null,
        'replay' => 'delegate_hr',
        'action' => 'attendance.bulk',
    ],
    'hr_leave_draft' => [
        'module' => 'hr',
        'method' => 'POST',
        'path' => null,
        'replay' => 'delegate_hr',
        'action' => 'leave_request.draft',
    ],
    'employee_directory' => [
        'module' => 'hr',
        'method' => 'GET',
        'path' => '/api/v1/offline/delta/employee_directory',
        'replay' => 'delta_pull',
    ],
];
