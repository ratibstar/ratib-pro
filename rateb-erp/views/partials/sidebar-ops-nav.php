<?php
declare(strict_types=1);

/**
 * Company operations nav — full CRUD routes under unified /admin shell.
 * Shown for company users and super admins (permissions + plan modules apply).
 */
if (function_exists('rateb_bootstrap_ops_tenant')) {
    rateb_bootstrap_ops_tenant();
}
if (!rateb_is_super_admin() && rateb_company_branches_nav_enabled()) {
    $opsSection(__('branches'), [
        ['branch-dashboard', 'branch_dashboard', 'fa-code-branch', 'branches', 'branch.dashboard.view'],
        ['branch-financial', 'branch_financial_reports', 'fa-file-invoice-dollar', 'accounting', 'branch.financial.pl'],
        ['branch-dashboard/compare', 'branch_comparison', 'fa-scale-balanced', 'branches', 'branch.dashboard.compare'],
        ['branch-dashboard/reports', 'branch_reports', 'fa-chart-column', 'branches', 'branch.reports.view'],
        ['branch-transfers', 'branch_transfers', 'fa-shuffle', 'branches', 'branch.transfers.view'],
    ], 'fa-code-branch');
}
$opsSection(__('procurement'), [
    ['eproc', 'procurement_platform', 'fa-building-columns', 'procurement', 'procurement.view'],
    ['eproc/suppliers', 'eproc_suppliers', 'fa-truck', 'procurement', 'procurement.supplier'],
    ['eproc/tenders', 'eproc_tenders', 'fa-gavel', 'procurement', 'procurement.tender'],
    ['eproc/contracts', 'eproc_contracts', 'fa-file-contract', 'procurement', 'procurement.contract'],
    ['eproc/calendar', 'eproc_calendar', 'fa-calendar-days', 'procurement', 'procurement.view'],
    ['eproc/spend', 'eproc_spend', 'fa-chart-pie', 'procurement', 'procurement.view'],
    ['eproc/portal', 'eproc_portal', 'fa-globe', 'procurement', 'procurement.portal'],
    ['eproc/reports', 'eproc_reports', 'fa-chart-line', 'procurement', 'procurement.view'],
    ['purchase-requests', 'purchase_requests', 'fa-file-circle-plus', 'procurement'],
    ['purchase-orders', 'purchase_orders', 'fa-file-invoice', 'procurement'],
    ['rfq', 'rfq', 'fa-comments-dollar', 'procurement'],
    ['quotations', 'quotations', 'fa-file-signature', 'procurement'],
], 'fa-cart-shopping');
$opsSection(__('recruitment'), [
    ['recruitment', 'recruitment', 'fa-briefcase', 'recruitment'],
    ['recruitment/candidates', 'recruitment_candidates', 'fa-user-plus', 'recruitment'],
    ['recruitment/agencies', 'recruitment_agencies', 'fa-building', 'recruitment'],
], 'fa-user-tie');
$opsSection(__('crm'), [
    ['crm', 'crm', 'fa-handshake', 'crm', 'crm.view'],
    ['crm/leads', 'crm_leads', 'fa-user-tag', 'crm', 'crm.view'],
    ['crm/leads/board', 'crm_lead_board', 'fa-columns', 'crm', 'crm.view'],
    ['crm/pipeline', 'crm_pipeline', 'fa-filter', 'crm', 'crm.pipeline'],
    ['crm/opportunities', 'crm_opportunities', 'fa-bullseye', 'crm', 'crm.view'],
    ['crm/meetings', 'crm_meetings', 'fa-calendar', 'crm', 'crm.activities'],
    ['crm/tasks', 'crm_tasks', 'fa-list-check', 'crm', 'crm.activities'],
    ['crm/campaigns', 'crm_campaigns', 'fa-bullhorn', 'crm', 'crm.campaign'],
    ['crm/contacts', 'crm_contacts', 'fa-address-book', 'crm', 'crm.view'],
    ['crm/companies', 'crm_companies', 'fa-building-user', 'crm', 'crm.view'],
], 'fa-handshake');
$opsSection(__('projects'), [
    ['projects', 'projects', 'fa-diagram-project', 'projects', 'projects.view'],
    ['projects/list', 'projects_list', 'fa-folder-open', 'projects', 'projects.view'],
    ['projects/tasks', 'project_tasks', 'fa-list-check', 'projects', 'projects.tasks'],
    ['projects/tasks/kanban', 'project_kanban', 'fa-columns', 'projects', 'projects.tasks'],
    ['projects/tasks/gantt', 'project_gantt', 'fa-chart-gantt', 'projects', 'projects.tasks'],
    ['projects/tasks/calendar', 'project_calendar', 'fa-calendar-days', 'projects', 'projects.tasks'],
    ['projects/milestones', 'project_milestones', 'fa-flag', 'projects', 'projects.view'],
    ['projects/issues', 'project_issues', 'fa-bug', 'projects', 'projects.view'],
    ['projects/risks', 'project_risks', 'fa-triangle-exclamation', 'projects', 'projects.view'],
    ['projects/timesheets', 'project_timesheets', 'fa-clock', 'projects', 'projects.timesheets'],
    ['projects/resources', 'project_resources', 'fa-users-gear', 'projects', 'projects.view'],
    ['projects/budget', 'project_budget', 'fa-coins', 'projects', 'projects.budget'],
    ['projects/timeline', 'project_timeline', 'fa-timeline', 'projects', 'projects.view'],
    ['projects/reports', 'project_reports', 'fa-chart-pie', 'projects', 'projects.reports'],
], 'fa-diagram-project');
$opsSection(__('inventory'), [
    ['inventory', 'inventory', 'fa-boxes-stacked', 'inventory'],
    ['inventory-batches', 'inventory_batches', 'fa-layer-group', 'inventory'],
    ['inventory-audits', 'inventory_audits', 'fa-clipboard-check', 'inventory'],
    ['warehouses', 'warehouses', 'fa-warehouse', 'inventory'],
    ['warehouse-transfers', 'warehouse_transfers', 'fa-truck-ramp-box', 'inventory'],
    ['inventory-forecast', 'inventory_forecast', 'fa-chart-line', 'inventory'],
    ['stock-movements', 'stock_movements', 'fa-arrows-rotate', 'inventory'],
    ['product-categories', 'product_categories', 'fa-tags', 'inventory'],
], 'fa-boxes-stacked');
$opsSection(__('manufacturing_platform'), [
    ['mfg', 'manufacturing_platform', 'fa-industry', 'manufacturing', 'manufacturing.view'],
    ['mfg/products', 'mfg_products', 'fa-cube', 'manufacturing', 'manufacturing.view'],
    ['mfg/boms', 'mfg_boms', 'fa-sitemap', 'manufacturing', 'manufacturing.bom'],
    ['mfg/production-orders', 'mfg_production_orders', 'fa-clipboard-list', 'manufacturing', 'manufacturing.shopfloor'],
    ['mfg/work-orders', 'mfg_work_orders', 'fa-hammer', 'manufacturing', 'manufacturing.shopfloor'],
    ['mfg/work-centers', 'mfg_work_centers', 'fa-warehouse', 'manufacturing', 'manufacturing.planning'],
    ['mfg/routings', 'mfg_routings', 'fa-route', 'manufacturing', 'manufacturing.bom'],
    ['mfg/capacity', 'mfg_capacity', 'fa-gauge-high', 'manufacturing', 'manufacturing.planning'],
    ['mfg/calendar', 'mfg_calendar', 'fa-calendar-days', 'manufacturing', 'manufacturing.planning'],
    ['mfg/schedules', 'mfg_schedules', 'fa-timeline', 'manufacturing', 'manufacturing.planning'],
    ['mfg/quality', 'mfg_quality', 'fa-clipboard-check', 'manufacturing', 'manufacturing.quality'],
    ['mfg/reports', 'mfg_reports', 'fa-chart-line', 'manufacturing', 'manufacturing.view'],
], 'fa-industry');
$opsSection(__('hr_platform'), [
    ['hrm', 'hr_platform', 'fa-id-card-clip', 'hr', 'hr.view'],
    ['hrm/employees', 'hrm_employees', 'fa-user-tie', 'hr', 'hr.view'],
    ['hrm/departments', 'hrm_departments', 'fa-building', 'hr', 'hr.view'],
    ['hrm/positions', 'hrm_positions', 'fa-briefcase', 'hr', 'hr.view'],
    ['hrm/organization', 'hrm_organization', 'fa-sitemap', 'hr', 'hr.view'],
    ['hrm/training', 'hrm_training', 'fa-chalkboard-user', 'hr', 'hr.training'],
    ['hrm/performance', 'hrm_performance', 'fa-chart-simple', 'hr', 'hr.performance'],
    ['hrm/goals', 'hrm_goals', 'fa-bullseye', 'hr', 'hr.performance'],
    ['hrm/competencies', 'hrm_competencies', 'fa-layer-group', 'hr', 'hr.performance'],
    ['hrm/promotions', 'hrm_promotions', 'fa-arrow-up', 'hr', 'hr.promotions'],
    ['hrm/transfers', 'hrm_transfers', 'fa-right-left', 'hr', 'hr.transfers'],
    ['hrm/timeline', 'hrm_timeline', 'fa-timeline', 'hr', 'hr.view'],
    ['hrm/reports', 'hrm_reports', 'fa-chart-pie', 'hr', 'hr.view'],
], 'fa-id-card-clip');
$opsSection(__('payroll_platform'), [
    ['payroll/dashboard', 'payroll_platform', 'fa-gauge-high', 'payroll', 'payroll.view'],
    ['payroll/batches', 'payroll_batches', 'fa-layer-group', 'payroll', 'payroll.view'],
    ['payroll/cycles', 'payroll_cycles', 'fa-calendar-days', 'payroll', 'payroll.view'],
    ['payroll/payslips', 'payroll_payslips', 'fa-file-invoice', 'payroll', 'payroll.view'],
    ['payroll/salary-structures', 'payroll_salary_structures', 'fa-table-list', 'payroll', 'payroll.view'],
    ['payroll/loans', 'payroll_loans', 'fa-hand-holding-dollar', 'payroll', 'payroll.view'],
    ['payroll/advances', 'payroll_advances', 'fa-money-bill-transfer', 'payroll', 'payroll.view'],
    ['payroll/overtime', 'payroll_overtime', 'fa-clock', 'payroll', 'payroll.view'],
    ['payroll/timeline', 'payroll_timeline', 'fa-timeline', 'payroll', 'payroll.view'],
    ['payroll/reports', 'payroll_reports', 'fa-chart-pie', 'payroll', 'payroll.view'],
], 'fa-money-check-dollar');
$opsSection(__('quality_platform'), [
    ['qms/dashboard', 'quality_platform', 'fa-clipboard-check', 'quality', 'quality.view'],
    ['qms/plans', 'quality_plans', 'fa-map', 'quality', 'quality.view'],
    ['qms/standards', 'quality_standards', 'fa-certificate', 'quality', 'quality.view'],
    ['qms/checklists', 'quality_checklists', 'fa-list-check', 'quality', 'quality.view'],
    ['qms/inspections', 'quality_inspections', 'fa-magnifying-glass', 'quality', 'quality.view'],
    ['qms/defects', 'quality_defects', 'fa-bug', 'quality', 'quality.view'],
    ['qms/nonconformities', 'quality_nonconformities', 'fa-triangle-exclamation', 'quality', 'quality.view'],
    ['qms/corrective-actions', 'quality_corrective_actions', 'fa-wrench', 'quality', 'quality.view'],
    ['qms/preventive-actions', 'quality_preventive_actions', 'fa-shield-halved', 'quality', 'quality.view'],
    ['qms/audits', 'quality_audits', 'fa-file-circle-check', 'quality', 'quality.view'],
    ['qms/complaints', 'quality_complaints', 'fa-comment-dots', 'quality', 'quality.view'],
    ['qms/supplier-quality', 'quality_supplier_quality', 'fa-truck', 'quality', 'quality.view'],
    ['qms/timeline', 'quality_timeline', 'fa-timeline', 'quality', 'quality.view'],
    ['qms/reports', 'quality_reports', 'fa-chart-pie', 'quality', 'quality.view'],
], 'fa-shield-halved');
if (is_file(RATEB_ROOT . '/modules/pos/views/partials/sidebar-pos-nav.php')) {
    require RATEB_ROOT . '/modules/pos/views/partials/sidebar-pos-nav.php';
}
$opsSection(__('suppliers'), [
    ['suppliers', 'suppliers', 'fa-truck-field', 'suppliers'],
    ['supplier-comms', 'supplier_comms', 'fa-comments', 'suppliers'],
    ['supplier-evaluations', 'supplier_evaluations', 'fa-star-half-stroke', 'suppliers'],
    ['supplier-classifications', 'supplier_classifications', 'fa-tags', 'suppliers'],
    ['supplier-kpi', 'supplier_kpi', 'fa-chart-line', 'suppliers'],
], 'fa-truck-field');
require RATEB_ROOT . '/views/partials/sidebar-hr-nav.php';
$opsSection(__('accounting_module'), [
    ['accounting', 'accounting_dashboard', 'fa-gauge-high', 'accounting'],
    ['accounting/platform', 'accounting_platform', 'fa-building-columns', 'accounting', 'accounting.view'],
    ['accounting/cfo-dashboard', 'cfo_dashboard', 'fa-user-tie', 'accounting', 'accounting.view'],
    ['accounting/accounts-receivable', 'accounts_receivable', 'fa-hand-holding-dollar', 'accounting', 'accounting.view'],
    ['accounting/accounts-payable', 'accounts_payable', 'fa-file-invoice-dollar', 'accounting', 'accounting.view'],
    ['customers', 'customers', 'fa-users', 'accounting', 'accounting.view'],
    ['accounting/reports', 'accounting_reports', 'fa-chart-pie', 'accounting', 'accounting.view'],
    ['chart-of-accounts', 'chart_of_accounts', 'fa-list', 'accounting'],
    ['accounting/coa-tree', 'coa_full_tree', 'fa-sitemap', 'accounting', 'accounting.view'],
    ['journal-entries', 'journal_entries', 'fa-book', 'accounting'],
    ['cash-vouchers', 'cash_vouchers', 'fa-money-bill-wave', 'accounting'],
    ['accounting/supplier-payments', 'supplier_payments', 'fa-hand-holding-dollar', 'accounting', 'accounting.view'],
    ['fiscal-periods', 'fiscal_periods', 'fa-calendar-days', 'accounting'],
    ['cost-centers', 'cost_centers', 'fa-diagram-project', 'accounting'],
    ['bank-accounts', 'bank_accounts', 'fa-building-columns', 'accounting'],
    ['accounting/bank-reconciliation', 'bank_reconciliation', 'fa-scale-balanced', 'accounting', 'accounting.view'],
    ['accounting-control', 'accounting_control_center', 'fa-shield-halved', 'accounting', 'accounting.dashboard'],
    ['accounting/zatca-settings', 'zatca_settings', 'fa-file-invoice', 'accounting', 'accounting.view'],
    ['reports/cost-analysis', 'cost_analysis', 'fa-coins', 'reports'],
    ['reports/inventory-valuation', 'inventory_valuation_report', 'fa-boxes-stacked', 'inventory'],
    ['asset-depreciation', 'asset_depreciation', 'fa-chart-line', 'assets'],
], 'fa-calculator');
$opsSection(__('eam_platform'), [
    ['eam', 'eam_platform', 'fa-cubes', 'assets', 'assets.view'],
    ['eam/assets', 'eam_assets', 'fa-toolbox', 'assets', 'assets.view'],
    ['eam/maintenance', 'eam_maintenance', 'fa-wrench', 'assets', 'assets.maintenance'],
    ['eam/work-orders', 'eam_work_orders', 'fa-clipboard-list', 'assets', 'assets.maintenance'],
    ['eam/requests', 'eam_requests', 'fa-inbox', 'assets', 'assets.maintenance'],
    ['eam/calendar', 'eam_calendar', 'fa-calendar-days', 'assets', 'assets.maintenance'],
    ['eam/assignments', 'eam_assignments', 'fa-user-check', 'assets', 'assets.assign'],
    ['eam/inspections', 'eam_inspections', 'fa-clipboard-check', 'assets', 'assets.inspection'],
    ['eam/timeline', 'eam_timeline', 'fa-timeline', 'assets', 'assets.view'],
    ['eam/reports', 'eam_reports', 'fa-chart-pie', 'assets', 'assets.view'],
], 'fa-cubes');
$opsSection(__('approval_platform'), [
    ['approvals', 'approval_platform', 'fa-clipboard-check', 'approval', 'approval.view'],
    ['approvals/requests', 'approval_requests', 'fa-inbox', 'approval', 'approval.view'],
    ['approvals/pending', 'approval_pending', 'fa-hourglass-half', 'approval', 'approval.approve'],
    ['approvals/templates', 'approval_templates', 'fa-file-lines', 'approval', 'approval.view'],
    ['approvals/chains', 'approval_chains', 'fa-link', 'approval', 'approval.view'],
    ['approvals/rules', 'approval_rules', 'fa-scale-balanced', 'approval', 'approval.view'],
    ['approvals/history', 'approval_history', 'fa-clock-rotate-left', 'approval', 'approval.view'],
    ['approvals/reports', 'approval_reports', 'fa-chart-pie', 'approval', 'approval.view'],
], 'fa-clipboard-check');
$opsSection(__('contracts') . ' / ' . __('assets'), [
    ['contracts', 'contracts', 'fa-file-contract', 'contracts'],
    ['contract-renewals', 'contract_renewals', 'fa-rotate', 'contracts'],
    ['tenders', 'tenders', 'fa-gavel', 'tenders'],
    ['assets', 'assets', 'fa-toolbox', 'assets'],
    ['asset-maintenance', 'asset_maintenance', 'fa-wrench', 'assets'],
    ['asset-assignments', 'asset_assignments', 'fa-user-check', 'assets'],
    ['medical-devices', 'medical_devices', 'fa-stethoscope', 'medical_devices'],
    ['device-maintenance', 'device_maintenance', 'fa-screwdriver-wrench', 'medical_devices'],
    ['device-spare-parts', 'device_spare_parts', 'fa-gears', 'medical_devices'],
    ['device-warranty', 'device_warranty', 'fa-shield-halved', 'medical_devices'],
    ['reports', 'reports', 'fa-chart-pie', 'reports'],
    ['reports/procurement', 'procurement_analytics', 'fa-cart-shopping', 'procurement'],
    ['reports/kpi', 'company_kpi', 'fa-gauge-high', 'reports'],
    ['reports/supplier-performance', 'supplier_performance_report', 'fa-truck-field', 'reports'],
    ['documents', 'documents', 'fa-folder-open', 'documents'],
], 'fa-briefcase');
if (function_exists('rateb_company_access_routes_enabled') && rateb_company_access_routes_enabled()) {
    $accessNavLinks = [
        ['access-control', 'access_control', 'fa-shield-halved', '', 'access.manage'],
        ['access-control/matrix', 'permission_matrix', 'fa-table-cells', '', 'access.manage'],
        ['users', 'users', 'fa-users', '', 'access.manage'],
        ['roles', 'roles', 'fa-user-shield', '', 'access.manage'],
    ];
    if (!function_exists('rateb_tenant_permission_catalog_locked') || !rateb_tenant_permission_catalog_locked()) {
        $accessNavLinks[] = ['permissions', 'permissions', 'fa-key', '', 'access.manage'];
    }
    $accessNavLinks = array_merge($accessNavLinks, [
        ['audit-logs', 'audit_logs', 'fa-clipboard-list', '', 'settings.manage'],
        ['support-tickets', 'support_tickets', 'fa-life-ring', '', 'settings.manage'],
        ['email-templates', 'email_templates', 'fa-envelope', '', 'settings.manage'],
        ['sms-templates', 'sms_templates', 'fa-sms', '', 'settings.manage'],
    ]);
    $opsSection(__('access_control'), $accessNavLinks, 'fa-key');
}
$opsLink('notifications', 'notifications', 'fa-bell');
$opsLink('profile', 'profile', 'fa-user-gear');
