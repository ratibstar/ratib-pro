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
    ],

    /** Default role slug for demo / full company ERP access. */
    'company_full_access_role' => 'company-full-access',

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
        'admin/executive-dashboard' => 'executive.dashboard.view',
        'admin/companies' => 'companies.view',
        'admin/companies/create' => 'companies.manage',
        'admin/access-control' => 'access.manage',
        'admin/access-control/matrix' => 'access.manage',
        'admin/accounting' => 'accounting.view',
        'admin/accounting/sync' => 'accounting.post',
        'admin/chart-of-accounts' => 'accounting.view',
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
