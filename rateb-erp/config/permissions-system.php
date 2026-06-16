<?php
declare(strict_types=1);

/**
 * RATEB ERP — permission system registry (matrix slugs ↔ routes ↔ entities).
 * DB source of truth: rateb_permissions + rateb_role_permissions (matrix UI).
 * Runtime enforcement: entity-permissions.php + route middleware + controller guards.
 */
return [
    /** Modules reserved for platform super-admin (never on company-full-access). */
    'platform_modules' => [
        'companies',
        'subscriptions',
        'plans',
        'permissions',
        'roles',
        'users',
        'settings',
        'access',
    ],

    /** Plan modules enabled for tenant company operations. */
    'company_modules' => [
        'dashboard',
        'procurement',
        'inventory',
        'suppliers',
        'assets',
        'contracts',
        'tenders',
        'reports',
        'medical_devices',
        'accounting',
        'documents',
        'workflows',
        'notifications',
        'hr',
    ],

    /** Default role slug for demo / full company ERP access. */
    'company_full_access_role' => 'company-full-access',

    /**
     * Accounting permission tiers (company matrix):
     * - accounting.view — read reports, lists, and entry details
     * - accounting.manage — create/edit draft journals, vouchers, chart of accounts
     * - accounting.approve — approve (post) manual journals and cash vouchers; void them
     * - accounting.post — system sync, fiscal period close/reopen, automatic posting
     */
    'accounting_permission_slugs' => [
        'accounting.view',
        'accounting.manage',
        'accounting.approve',
        'accounting.post',
    ],

    /** Key accounting operations and required permission slugs. */
    'accounting_permission_ops' => [
        'approve_journals_vouchers' => 'accounting.approve',
        'supplier_payment' => 'accounting.post',
        'supplier_payment_void' => 'accounting.post',
        'bank_statement_import' => 'accounting.manage',
        'bank_statement_delete' => 'accounting.manage',
        'fiscal_period_close' => 'accounting.post',
        'fiscal_period_manage' => 'accounting.manage',
        'accounting_sync' => 'accounting.post',
    ],

    /** Inventory-related permission slugs (company matrix). */
    'inventory_permission_slugs' => [
        'inventory.manage',
        'stock_movements.view',
        'stock_movements.manage',
        'inventory_batches.view',
        'inventory_batches.manage',
        'inventory_audit.view',
        'inventory_audit.manage',
        'categories.view',
        'categories.manage',
        'warehouse_transfers.view',
        'warehouse_transfers.manage',
        'inventory_forecast.view',
        'reports.inventory_valuation.view',
    ],

    /**
     * Permission slugs that must never be assigned to company-full-access
     * (even when module is shared, e.g. reports.executive).
     */
    'company_role_excluded_slugs' => [
        'executive.dashboard.view',
        'billing.manage',
        'companies.view',
        'companies.manage',
        'company_plans.manage',
        'subscriptions.manage',
        'plans.manage',
        'settings.manage',
        'access.manage',
        'users.manage',
        'roles.manage',
        'permissions.manage',
    ],

    /** Platform /admin routes → permission slug (SuperAdminMiddleware + RequirePermission). */
    'platform_routes' => [
        'admin' => 'dashboard.view',
        'admin/executive-dashboard' => 'executive.dashboard.view',
        'admin/companies' => 'companies.view',
        'admin/companies/create' => 'companies.manage',
        'admin/access-control' => 'access.manage',
        'admin/access-control/matrix' => 'access.manage',
        'admin/accounting' => 'accounting.view',
        'admin/accounting/sync' => 'accounting.post',
        'admin/chart-of-accounts' => 'accounting.view',
        'admin/coa-tree' => 'accounting.view',
        'admin/journal-entries' => 'accounting.view',
        'admin/subscriptions' => 'subscriptions.manage',
        'admin/plans' => 'plans.manage',
        'admin/users' => 'access.manage',
        'admin/roles' => 'access.manage',
        'admin/permissions' => 'access.manage',
        'admin/companies/{id}/edit' => 'company_plans.manage',
        'admin/companies/{id}' => 'company_plans.manage',
        'admin/chart-of-accounts/create' => 'accounting.manage',
        'admin/chart-of-accounts/{id}/edit' => 'accounting.manage',
        'admin/payments/create' => 'billing.manage',
        'admin/payments/{id}/edit' => 'billing.manage',
        'admin/invoices/create' => 'billing.manage',
        'admin/invoices/{id}/edit' => 'billing.manage',
        'admin/payments' => 'accounting.view',
        'admin/invoices' => 'accounting.view',
        'admin/audit-logs' => 'settings.manage',
        'admin/login-activity' => 'settings.manage',
        'admin/queue-monitor' => 'settings.manage',
        'admin/automation-health' => 'settings.manage',
        'admin/settings' => 'settings.manage',
        'admin/reports' => 'reports.view',
        'admin/oversight/procurement' => 'procurement.manage',
        'admin/oversight/rfq' => 'procurement.manage',
        'admin/oversight/inventory' => 'inventory.manage',
        'admin/oversight/workflows' => 'workflows.view',
        'admin/oversight/workflows/store' => 'workflows.manage',
        'admin/email-templates' => 'settings.manage',
        'admin/sms-templates' => 'settings.manage',
        'admin/support-tickets' => 'settings.manage',
    ],
];
