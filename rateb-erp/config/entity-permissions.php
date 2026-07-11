<?php
declare(strict_types=1);

/**
 * Maps company operation resources to RBAC slugs (from permission matrix).
 * view = list/read, manage = create/edit/delete, export = optional export action.
 */
return [
    'dashboard' => [
        'module' => 'dashboard',
        'view' => 'dashboard.view',
        'manage' => 'dashboard.view',
    ],
    'purchase-requests' => [
        'module' => 'procurement',
        'view' => 'procurement.manage',
        'manage' => 'procurement.manage',
        'export' => 'reports.export',
    ],
    'eproc' => [
        'module' => 'procurement',
        'view' => 'procurement.view',
        'manage' => 'procurement.manage',
    ],
    'eproc-suppliers' => [
        'module' => 'procurement',
        'view' => 'procurement.supplier',
        'manage' => 'procurement.manage',
    ],
    'eproc-tenders' => [
        'module' => 'procurement',
        'view' => 'procurement.tender',
        'manage' => 'procurement.manage',
    ],
    'eproc-contracts' => [
        'module' => 'procurement',
        'view' => 'procurement.contract',
        'manage' => 'procurement.manage',
    ],
    'mfg' => [
        'module' => 'manufacturing',
        'view' => 'manufacturing.view',
        'manage' => 'manufacturing.manage',
    ],
    'mfg-products' => [
        'module' => 'manufacturing',
        'view' => 'manufacturing.view',
        'manage' => 'manufacturing.manage',
    ],
    'mfg-boms' => [
        'module' => 'manufacturing',
        'view' => 'manufacturing.bom',
        'manage' => 'manufacturing.manage',
    ],
    'mfg-production-orders' => [
        'module' => 'manufacturing',
        'view' => 'manufacturing.shopfloor',
        'manage' => 'manufacturing.manage',
    ],
    'purchase-orders' => [
        'module' => 'procurement',
        'view' => 'procurement.manage',
        'manage' => 'procurement.manage',
        'export' => 'reports.export',
    ],
    'customs-clearance-costs' => [
        'module' => 'procurement',
        'view' => 'customs_clearance.view',
        'manage' => 'customs_clearance.manage',
        'export' => 'reports.export',
    ],
    'rfq' => [
        'module' => 'procurement',
        'view' => 'procurement.manage',
        'manage' => 'procurement.manage',
    ],
    'quotations' => [
        'module' => 'procurement',
        'view' => 'procurement.manage',
        'manage' => 'procurement.manage',
    ],
    'inventory' => [
        'module' => 'inventory',
        'view' => 'inventory.manage',
        'manage' => 'inventory.manage',
    ],
    'inventory-codes' => [
        'module' => 'inventory',
        'view' => 'inventory.manage',
        'manage' => 'inventory.manage',
    ],
    'warehouses' => [
        'module' => 'inventory',
        'view' => 'inventory.manage',
        'manage' => 'inventory.manage',
    ],
    'branches' => [
        'module' => 'branches',
        'view' => 'branches.view',
        'manage' => 'branches.manage',
        'export' => 'reports.export',
    ],
    'branch-dashboard' => [
        'module' => 'branches',
        'view' => 'branch.dashboard.view',
        'manage' => 'branch.dashboard.view',
    ],
    'branch-transfers' => [
        'module' => 'branches',
        'view' => 'branch.transfers.view',
        'manage' => 'branch.transfers.manage',
        'approve' => 'branch.transfers.manage',
    ],
    'branch-financial' => [
        'module' => 'accounting',
        'view' => 'branch.financial.pl',
        'manage' => 'branch.financial.pl',
    ],
    'stock-movements' => [
        'module' => 'inventory',
        'view' => 'stock_movements.view',
        'manage' => 'stock_movements.manage',
        'export' => 'reports.export',
    ],
    'product-categories' => [
        'module' => 'inventory',
        'view' => 'categories.view',
        'manage' => 'categories.manage',
    ],
    'inventory-batches' => [
        'module' => 'inventory',
        'view' => 'inventory_batches.view',
        'manage' => 'inventory_batches.manage',
        'export' => 'reports.export',
    ],
    'inventory-audits' => [
        'module' => 'inventory',
        'view' => 'inventory_audit.view',
        'manage' => 'inventory_audit.manage',
    ],
    'warehouse-transfers' => [
        'module' => 'inventory',
        'view' => 'warehouse_transfers.view',
        'manage' => 'warehouse_transfers.manage',
        'approve' => 'warehouse_transfers.manage',
    ],
    'inventory-forecast' => [
        'module' => 'inventory',
        'view' => 'inventory_forecast.view',
        'manage' => 'inventory_forecast.view',
    ],
    'suppliers' => [
        'module' => 'suppliers',
        'view' => 'suppliers.manage',
        'manage' => 'suppliers.manage',
    ],
    'supplier-evaluations' => [
        'module' => 'suppliers',
        'view' => 'evaluations.view',
        'manage' => 'evaluations.manage',
    ],
    'supplier-classifications' => [
        'module' => 'suppliers',
        'view' => 'supplier_classifications.view',
        'manage' => 'supplier_classifications.manage',
    ],
    'supplier-kpi' => [
        'module' => 'suppliers',
        'view' => 'supplier_kpi.view',
        'manage' => 'supplier_kpi.view',
        'export' => 'reports.export',
    ],
    'supplier-comms' => [
        'module' => 'suppliers',
        'view' => 'supplier_comms.view',
        'manage' => 'supplier_comms.manage',
    ],
    'contracts' => [
        'module' => 'contracts',
        'view' => 'contracts.manage',
        'manage' => 'contracts.manage',
    ],
    'contract-renewals' => [
        'module' => 'contracts',
        'view' => 'contract_renewals.view',
        'manage' => 'contract_renewals.manage',
    ],
    'tenders' => [
        'module' => 'tenders',
        'view' => 'tenders.manage',
        'manage' => 'tenders.manage',
    ],
    'assets' => [
        'module' => 'assets',
        'view' => 'assets.view',
        'manage' => 'assets.manage',
    ],
    'eam' => [
        'module' => 'assets',
        'view' => 'assets.view',
        'manage' => 'assets.manage',
    ],
    'approval' => [
        'module' => 'approval',
        'view' => 'approval.view',
        'manage' => 'approval.manage',
    ],
    'approvals' => [
        'module' => 'approval',
        'view' => 'approval.view',
        'manage' => 'approval.manage',
    ],
    'eam-assets' => [
        'module' => 'assets',
        'view' => 'assets.view',
        'manage' => 'assets.manage',
    ],
    'asset-maintenance' => [
        'module' => 'assets',
        'view' => 'asset_maintenance.view',
        'manage' => 'asset_maintenance.manage',
    ],
    'asset-assignments' => [
        'module' => 'assets',
        'view' => 'asset_assignments.view',
        'manage' => 'asset_assignments.manage',
    ],
    'asset-depreciation' => [
        'module' => 'assets',
        'view' => 'asset_depreciation.view',
        'manage' => 'asset_depreciation.manage',
    ],
    'medical-devices' => [
        'module' => 'medical_devices',
        'view' => 'device_service.view',
        'manage' => 'device_service.manage',
    ],
    'device-maintenance' => [
        'module' => 'medical_devices',
        'view' => 'device_service.view',
        'manage' => 'device_service.manage',
    ],
    'device-spare-parts' => [
        'module' => 'medical_devices',
        'view' => 'device_service.view',
        'manage' => 'device_spare_parts.manage',
    ],
    'device-warranty' => [
        'module' => 'medical_devices',
        'view' => 'device_warranty.view',
        'manage' => 'device_service.manage',
    ],
    'accounting' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
        'post' => 'accounting.post',
    ],
    'accounting-reports' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'trial-balance' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'journal-register' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'account-statement' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'partners-subsidiary-ledger' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'chart-of-accounts' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
    ],
    'coa-tree' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
    ],
    'journal-entries' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
        'approve' => 'accounting.approve',
        'post' => 'accounting.post',
    ],
    'entry-approval' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
        'approve' => 'accounting.approve',
        'post' => 'accounting.post',
    ],
    'accounts-payable' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'accounts-receivable' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'profit-loss' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'cost-of-sales' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'balance-sheet' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'vat-report' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'cash-vouchers' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
        'approve' => 'accounting.approve',
        'post' => 'accounting.post',
    ],
    'voucher-approval' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
        'approve' => 'accounting.approve',
        'post' => 'accounting.post',
    ],
    'fiscal-periods' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
        'post' => 'accounting.post',
    ],
    'accounting-control' => [
        'module' => 'accounting',
        'view' => 'accounting.dashboard',
        'events' => 'accounting.events',
        'replay' => 'accounting.replay',
        'audit' => 'accounting.audit',
        'projections' => 'accounting.projections',
        'consolidation' => 'accounting.consolidation',
        'drift' => 'accounting.drift',
        'reconciliation' => 'accounting.reconciliation',
        'integrity' => 'accounting.integrity',
        'health' => 'accounting.system_health',
    ],
    'cost-centers' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
    ],
    'customers' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
    ],
    'cost-center-report' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'zatca-settings' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
    ],
    'bank-accounts' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
    ],
    'bank-reconciliation' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
    ],
    'supplier-payments' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
        'post' => 'accounting.post',
        'export' => 'reports.export',
    ],
    'budget-report' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.manage',
    ],
    'cfo-dashboard' => [
        'module' => 'accounting',
        'view' => 'accounting.view',
        'manage' => 'accounting.view',
    ],
    'reports' => [
        'module' => 'reports',
        'view' => 'reports.view',
        'manage' => 'reports.view',
        'export' => 'reports.export',
    ],
    'reports/procurement' => [
        'module' => 'procurement',
        'view' => 'procurement.analytics',
        'manage' => 'procurement.analytics',
        'export' => 'reports.export',
    ],
    'reports/kpi' => [
        'module' => 'reports',
        'view' => 'reports.kpi.view',
        'manage' => 'reports.kpi.view',
        'export' => 'reports.export',
    ],
    'reports/cost-analysis' => [
        'module' => 'reports',
        'view' => 'reports.cost_analysis.view',
        'manage' => 'reports.cost_analysis.view',
        'export' => 'reports.export',
    ],
    'reports/supplier-performance' => [
        'module' => 'reports',
        'view' => 'reports.view',
        'manage' => 'reports.view',
        'export' => 'reports.export',
    ],
    'reports/inventory-valuation' => [
        'module' => 'inventory',
        'view' => 'reports.inventory_valuation.view',
        'manage' => 'reports.inventory_valuation.view',
        'export' => 'reports.export',
    ],
    'documents' => [
        'module' => 'documents',
        'view' => 'documents.view',
        'manage' => 'documents.manage',
    ],
    'workflows' => [
        'module' => 'workflows',
        'view' => 'workflows.view',
        'manage' => 'workflows.manage',
        'approve' => 'workflows.approve',
    ],
    'notifications' => [
        'module' => 'notifications',
        'view' => 'notifications.manage',
        'manage' => 'notifications.manage',
    ],
    'profile' => [
        'module' => '',
        'view' => '',
        'manage' => '',
    ],
    'access-control' => [
        'module' => '',
        'view' => 'access.manage',
        'manage' => 'access.manage',
    ],
    'users' => [
        'module' => '',
        'view' => 'access.manage',
        'manage' => 'access.manage',
    ],
    'roles' => [
        'module' => '',
        'view' => 'access.manage',
        'manage' => 'access.manage',
    ],
    'permissions' => [
        'module' => '',
        'view' => 'access.manage',
        'manage' => 'access.manage',
    ],
    'audit-logs' => [
        'module' => '',
        'view' => 'settings.manage',
        'manage' => 'settings.manage',
    ],
    'support-tickets' => [
        'module' => '',
        'view' => 'settings.manage',
        'manage' => 'settings.manage',
    ],
    'email-templates' => [
        'module' => '',
        'view' => 'settings.manage',
        'manage' => 'settings.manage',
    ],
    'sms-templates' => [
        'module' => '',
        'view' => 'settings.manage',
        'manage' => 'settings.manage',
    ],
    'hr' => [
        'module' => 'hr',
        'view' => 'hr.view',
        'manage' => 'hr.manage',
    ],
    'hr-employees' => [
        'module' => 'hr',
        'view' => 'hr.view',
        'manage' => 'hr.manage',
    ],
    'hr-attendance' => [
        'module' => 'hr',
        'view' => 'hr.view',
        'manage' => 'hr.manage',
    ],
    'hr-leaves' => [
        'module' => 'hr',
        'view' => 'hr.view',
        'manage' => 'hr.manage',
    ],
    'hr-payroll' => [
        'module' => 'hr',
        'view' => 'hr.view',
        'manage' => 'hr.manage',
    ],
    'recruitment' => [
        'module' => 'recruitment',
        'view' => 'recruitment.view',
        'manage' => 'recruitment.manage',
    ],
    'recruitment-candidates' => [
        'module' => 'recruitment',
        'view' => 'recruitment.view',
        'manage' => 'recruitment.manage',
    ],
    'recruitment-agencies' => [
        'module' => 'recruitment',
        'view' => 'recruitment.view',
        'manage' => 'recruitment.manage',
    ],
    'crm' => [
        'module' => 'crm',
        'view' => 'crm.view',
        'manage' => 'crm.manage',
    ],
    'crm-leads' => [
        'module' => 'crm',
        'view' => 'crm.view',
        'manage' => 'crm.manage',
    ],
    'projects' => [
        'module' => 'projects',
        'view' => 'projects.view',
        'manage' => 'projects.manage',
    ],
    'project-tasks' => [
        'module' => 'projects',
        'view' => 'projects.tasks',
        'manage' => 'projects.manage',
    ],
];
