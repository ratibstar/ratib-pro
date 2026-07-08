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
        'branches',
        'pos',
    ],

    /** Lang keys for company/plan module checkboxes (PlanLimitService::moduleCatalog). */
    'tenant_module_labels' => [
        'dashboard' => 'dashboard',
        'procurement' => 'procurement',
        'inventory' => 'inventory',
        'suppliers' => 'suppliers',
        'assets' => 'assets',
        'contracts' => 'contracts',
        'tenders' => 'tenders',
        'reports' => 'reports',
        'medical_devices' => 'medical_devices',
        'accounting' => 'accounting_module',
        'documents' => 'documents',
        'workflows' => 'workflows',
        'notifications' => 'notifications',
        'hr' => 'human_resources',
        'branches' => 'branches',
        'pos' => 'pos_nav_section',
    ],

    /**
     * Parent slug grants child slugs at runtime (rateb_can). Children may be hidden from matrix UI.
     * @var array<string, list<string>>
     */
    'permission_implies' => [
        'access.manage' => ['users.manage', 'roles.manage', 'permissions.manage'],
        'branch.financial.consolidated' => ['branch.financial.interbranch'],
        'workflows.manage' => ['oversight.approve'],
        'procurement.manage' => ['procurement.oversight'],
        'inventory.manage' => ['inventory.oversight'],
        'suppliers.manage' => ['suppliers.oversight'],
        'hr.manage' => ['hr.oversight'],
        'accounting.approve' => ['accounting.oversight'],
        'contracts.manage' => ['contracts.oversight'],
        'assets.manage' => ['assets.oversight'],
    ],

    /** Slugs omitted from matrix UI (still in DB; granted via permission_implies or legacy roles). */
    'matrix_hidden_slugs' => [
        'users.manage',
        'roles.manage',
        'permissions.manage',
        'branch.financial.interbranch',
    ],

    /** Branch / HQ permission slugs (company matrix module: branches + accounting reports). */
    'branch_permission_slugs' => [
        'branches.view',
        'branches.manage',
        'branches.access_all',
        'branch.dashboard.view',
        'branch.dashboard.compare',
        'branch.reports.view',
        'branch.transfers.view',
        'branch.transfers.manage',
    ],

    /** Default role slug for demo / full company ERP access. */
    'company_full_access_role' => 'company-full-access',

    /** Global platform roles (company_id NULL — one row each, not tenant-scoped). */
    'platform_role_slugs' => [
        'super-admin',
        'access-manager',
        'accountant',
        'accounting-approver',
    ],

    /** Tenant roles cloned per company (company_id = rateb_companies.id). */
    'tenant_role_slugs' => [
        'company-full-access',
        'hq_admin',
        'hq_manager',
        'branch_manager',
        'branch_user',
        'procurement-manager',
        'inventory-manager',
        'hr-manager',
        'pos_cashier',
        'pos_supervisor',
        'pos_manager',
    ],

    /** Extra permission slugs granted to company-full-access on dedicated / agency ERP hosts only. */
    'dedicated_company_admin_slugs' => [
        'access.manage',
        'settings.manage',
        'dashboard.view',
    ],

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
        'accounting.dashboard',
        'accounting.events',
        'accounting.replay',
        'accounting.audit',
        'accounting.projections',
        'accounting.consolidation',
        'accounting.drift',
        'accounting.reconciliation',
        'accounting.integrity',
        'accounting.system_health',
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

    /** Customs clearance permission slugs (procurement module). */
    'customs_clearance_permission_slugs' => [
        'customs_clearance.view',
        'customs_clearance.manage',
    ],

    /** Platform oversight approval slugs (super-admin matrix). */
    'oversight_permission_slugs' => [
        'oversight.approve',
        'procurement.oversight',
        'inventory.oversight',
        'suppliers.oversight',
        'hr.oversight',
        'accounting.oversight',
        'contracts.oversight',
        'assets.oversight',
        'cms.oversight',
        'executive.oversight',
        'access.oversight',
        'notifications.oversight',
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
        'oversight.approve',
        'procurement.oversight',
        'inventory.oversight',
        'suppliers.oversight',
        'hr.oversight',
        'accounting.oversight',
        'contracts.oversight',
        'assets.oversight',
        'cms.oversight',
        'executive.oversight',
        'access.oversight',
        'notifications.oversight',
    ],

    /** Platform /admin routes → permission slug (SuperAdminMiddleware + RequirePermission). */
    'platform_routes' => [
        'admin' => 'dashboard.view',
        'admin/executive-dashboard' => 'executive.dashboard.view',
        'admin/agency-updates' => 'companies.manage',
        'admin/agency-updates/link' => 'companies.manage',
        'admin/agency-updates/sync-files' => 'companies.manage',
        'admin/agency-updates/reset-data' => 'companies.manage',
        'admin/companies' => 'companies.view',
        'admin/companies/create' => 'companies.manage',
        'admin/access-control' => 'access.manage',
        'admin/access-control/matrix' => 'access.manage',
        'admin/accounting' => 'accounting.view',
        'admin/accounting-control' => 'accounting.dashboard',
        'admin/accounting-control/events' => 'accounting.events',
        'admin/accounting-control/replay' => 'accounting.replay',
        'admin/accounting-control/audit' => 'accounting.audit',
        'admin/accounting-control/projections' => 'accounting.projections',
        'admin/accounting-control/consolidation' => 'accounting.consolidation',
        'admin/accounting-control/drift' => 'accounting.drift',
        'admin/accounting-control/reconciliation' => 'accounting.reconciliation',
        'admin/accounting-control/integrity' => 'accounting.integrity',
        'admin/accounting-control/health' => 'accounting.system_health',
        'admin/accounting-control/settings' => 'accounting.dashboard',
        'admin/accounting-control/timeline' => 'accounting.dashboard',
        'admin/accounting-control/notifications' => 'accounting.dashboard',
        'admin/accounting-control/diagnostics' => 'accounting.system_health',
        'admin/accounting-control/api/{resource}' => 'accounting.dashboard',
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
        'admin/oversight/companies-approvals' => 'companies.view',
        'admin/oversight/procurement' => 'procurement.oversight',
        'admin/oversight/rfq' => 'procurement.oversight',
        'admin/oversight/inventory' => 'inventory.oversight',
        'admin/oversight/approvals' => 'workflows.view',
        'admin/oversight/approvals/count' => 'workflows.view',
        'admin/oversight/approvals/detail' => 'workflows.view',
        'admin/oversight/approvals/decide' => 'oversight.approve',
        'admin/oversight/approvals/action' => 'oversight.approve',
        'admin/oversight/approvals/approve' => 'oversight.approve',
        'admin/oversight/approvals/reject' => 'oversight.approve',
        'admin/oversight/approvals/undo' => 'oversight.approve',
        'admin/oversight/supplier-evaluations' => 'suppliers.oversight',
        'admin/oversight/workflows' => 'workflows.view',
        'admin/oversight/workflows/store' => 'workflows.manage',
        'admin/email-templates' => 'settings.manage',
        'admin/sms-templates' => 'settings.manage',
        'admin/support-tickets' => 'settings.manage',
    ],

    /** Company /app routes → permission slug (rateb_erp_mw enforcement). */
    'company_routes' => [
        'accounting-control' => 'accounting.dashboard',
        'accounting-control/events' => 'accounting.events',
        'accounting-control/replay' => 'accounting.replay',
        'accounting-control/audit' => 'accounting.audit',
        'accounting-control/projections' => 'accounting.projections',
        'accounting-control/consolidation' => 'accounting.consolidation',
        'accounting-control/drift' => 'accounting.drift',
        'accounting-control/reconciliation' => 'accounting.reconciliation',
        'accounting-control/integrity' => 'accounting.integrity',
        'accounting-control/settings' => 'accounting.dashboard',
        'accounting-control/health' => 'accounting.system_health',
        'accounting-control/timeline' => 'accounting.dashboard',
        'accounting-control/notifications' => 'accounting.dashboard',
        'accounting-control/diagnostics' => 'accounting.system_health',
        'accounting-control/api/{resource}' => 'accounting.dashboard',
    ],
];
