<?php

declare(strict_types=1);

/**
 * Phase 13 — Master-data delta entity allowlist + field maps.
 *
 * @return array{
 *   entities: array<string, array{
 *     table: string,
 *     aliases: list<string>,
 *     cache_prefix: string,
 *     branch_scoped: bool,
 *     requires_updated_at: bool
 *   }>
 * }
 */
return [
    'entities' => [
        'customer_directory' => [
            'table' => 'rateb_customers',
            'aliases' => ['customers', 'customer'],
            'cache_prefix' => 'cus',
            'branch_scoped' => false,
            'requires_updated_at' => true,
        ],
        'branch_directory' => [
            'table' => 'rateb_branches',
            'aliases' => ['branches', 'branch'],
            'cache_prefix' => 'br',
            'branch_scoped' => false,
            'requires_updated_at' => true,
        ],
        'warehouse_directory' => [
            'table' => 'rateb_warehouses',
            'aliases' => ['warehouses', 'warehouse'],
            'cache_prefix' => 'wh',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
        /** Normalized under master_data (also available via Tier-1 flags). */
        'employee_directory' => [
            'table' => 'rateb_employees',
            'aliases' => ['employees', 'hr_employees'],
            'cache_prefix' => 'emp',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
        'supplier_directory' => [
            'table' => 'rateb_suppliers',
            'aliases' => ['suppliers', 'procurement_suppliers'],
            'cache_prefix' => 'sup',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
    ],
];
