<?php
declare(strict_types=1);

/**
 * Company operations nav — unified lean product set (online = offline / branch).
 * Enterprise modules (recruitment, CRM, projects, …) appear when enabled in company permissions.
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
    ['purchase-requests', 'purchase_requests', 'fa-file-circle-plus', 'procurement'],
    ['purchase-orders', 'purchase_orders', 'fa-file-invoice', 'procurement'],
    ['rfq', 'rfq', 'fa-comments-dollar', 'procurement'],
    ['quotations', 'quotations', 'fa-file-signature', 'procurement'],
], 'fa-cart-shopping');
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
if (is_file(RATEB_ROOT . '/modules/pos/views/partials/sidebar-pos-nav.php')) {
    require RATEB_ROOT . '/modules/pos/views/partials/sidebar-pos-nav.php';
}
if (is_file(RATEB_ROOT . '/modules/logistics/views/partials/sidebar-logistics-nav.php')) {
    require RATEB_ROOT . '/modules/logistics/views/partials/sidebar-logistics-nav.php';
}
$opsSection(__('suppliers'), [
    ['suppliers', 'suppliers', 'fa-truck-field', 'suppliers'],
    ['supplier-comms', 'supplier_comms', 'fa-comments', 'suppliers'],
    ['supplier-evaluations', 'supplier_evaluations', 'fa-star-half-stroke', 'suppliers'],
    ['supplier-classifications', 'supplier_classifications', 'fa-tags', 'suppliers'],
    ['supplier-kpi', 'supplier_kpi', 'fa-chart-line', 'suppliers'],
], 'fa-truck-field');
require RATEB_ROOT . '/views/partials/sidebar-hr-nav.php';
$opsSection(__('recruitment'), [
    ['recruitment', 'recruitment', 'fa-user-plus', 'recruitment'],
    ['recruitment/candidates', 'recruitment_candidates', 'fa-users', 'recruitment'],
    ['recruitment/agencies', 'recruitment_agencies', 'fa-building', 'recruitment'],
], 'fa-user-plus');
$opsSection(__('crm'), [
    ['crm', 'crm', 'fa-handshake', 'crm'],
    ['crm/leads', 'crm_leads', 'fa-user-tag', 'crm'],
    ['crm/pipeline', 'crm_pipeline', 'fa-filter', 'crm'],
    ['crm/opportunities', 'crm_opportunities', 'fa-bullseye', 'crm'],
    ['crm/quotations', 'crm_quotations', 'fa-file-invoice', 'crm'],
    ['crm/reports', 'crm_reports', 'fa-chart-line', 'crm', 'crm.reports.view'],
    ['crm/admin', 'crm_admin_config', 'fa-sliders', 'crm', 'crm.config.manage'],
    ['crm/contacts', 'crm_contacts', 'fa-address-book', 'crm'],
    ['crm/companies', 'crm_companies', 'fa-building', 'crm'],
    ['crm/activities', 'crm_activities', 'fa-list-check', 'crm', 'crm.activities'],
    ['crm/calls', 'crm_calls', 'fa-phone', 'crm', 'crm.activities'],
    ['crm/meetings', 'crm_meetings', 'fa-calendar', 'crm', 'crm.activities'],
    ['crm/tasks', 'crm_tasks', 'fa-check', 'crm', 'crm.activities'],
], 'fa-handshake');
if (is_file(RATEB_ROOT . '/modules/marketplace/views/partials/sidebar-marketplace-nav.php')) {
    require RATEB_ROOT . '/modules/marketplace/views/partials/sidebar-marketplace-nav.php';
}
$opsSection(__('projects'), [
    ['projects', 'projects', 'fa-diagram-project', 'projects'],
    ['projects/list', 'projects_list', 'fa-list', 'projects'],
    ['projects/tasks', 'project_tasks', 'fa-tasks', 'projects', 'projects.tasks'],
], 'fa-diagram-project');
$opsSection(__('approval_platform'), [
    ['approvals', 'approval_platform', 'fa-check-double', 'approval'],
    ['approvals/pending', 'approval_pending', 'fa-clock', 'approval', 'approval.approve'],
    ['approvals/requests', 'approval_requests', 'fa-file-signature', 'approval'],
    ['approvals/templates', 'approval_templates', 'fa-file-lines', 'approval'],
], 'fa-check-double');
$opsSection(__('manufacturing_platform'), [
    ['mfg', 'manufacturing_platform', 'fa-industry', 'manufacturing'],
    ['mfg/products', 'mfg_products', 'fa-box', 'manufacturing'],
    ['mfg/boms', 'mfg_boms', 'fa-list-ul', 'manufacturing', 'manufacturing.bom'],
    ['mfg/production-orders', 'mfg_production_orders', 'fa-gears', 'manufacturing', 'manufacturing.shopfloor'],
], 'fa-industry');
$opsSection(__('payroll_platform'), [
    ['payroll', 'payroll_platform', 'fa-money-check-dollar', 'payroll'],
    ['payroll/batches', 'payroll_batches', 'fa-layer-group', 'payroll'],
    ['payroll/payslips', 'payroll_payslips', 'fa-file-invoice', 'payroll'],
    ['payroll/cycles', 'payroll_cycles', 'fa-rotate', 'payroll'],
], 'fa-money-check-dollar');
$opsSection(__('quality_platform'), [
    ['qms', 'quality_platform', 'fa-award', 'quality'],
    ['qms/inspections', 'quality_inspections', 'fa-clipboard-check', 'quality'],
    ['qms/nonconformities', 'quality_nonconformities', 'fa-triangle-exclamation', 'quality'],
    ['qms/corrective-actions', 'quality_corrective_actions', 'fa-wrench', 'quality', 'quality.corrective'],
], 'fa-award');
$opsSection(__('bi_platform'), [
    ['bi', 'bi_platform', 'fa-chart-line', 'bi'],
    ['bi/dashboards', 'bi_dashboards', 'fa-chart-pie', 'bi'],
    ['bi/kpis', 'bi_kpis', 'fa-gauge-high', 'bi'],
    ['bi/reports', 'bi_reports', 'fa-file-chart-column', 'bi'],
], 'fa-chart-line');
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
// Platform super-admin already gets Access Control (+ App Management) from main.php —
// skip here to avoid a duplicate "التحكم بالوصول" group.
if (!rateb_is_super_admin()
    && function_exists('rateb_company_access_routes_enabled')
    && rateb_company_access_routes_enabled()
) {
    // Agent Apps UI is SuperAdmin/platform-only today — omit from tenant sidebar.
    $accessNavLinks = [
        ['access-control', 'access_control', 'fa-shield-halved', 'access_control', 'access.manage'],
        ['access-control/matrix', 'permission_matrix', 'fa-table-cells', 'access_control', 'access.manage'],
        ['users', 'users', 'fa-users', 'access_control', 'access.manage'],
        ['roles', 'roles', 'fa-user-shield', 'access_control', 'access.manage'],
    ];
    if (!function_exists('rateb_tenant_permission_catalog_locked') || !rateb_tenant_permission_catalog_locked()) {
        $accessNavLinks[] = ['permissions', 'permissions', 'fa-key', 'access_control', 'access.manage'];
    }
    $accessNavLinks = array_merge($accessNavLinks, [
        ['audit-logs', 'audit_logs', 'fa-clipboard-list', 'access_control', 'settings.manage'],
        ['support-tickets', 'support_tickets', 'fa-life-ring', 'access_control', 'settings.manage'],
        ['email-templates', 'email_templates', 'fa-envelope', 'access_control', 'settings.manage'],
        ['sms-templates', 'sms_templates', 'fa-sms', 'access_control', 'settings.manage'],
    ]);
    $opsSection(__('access_control'), $accessNavLinks, 'fa-key');
}
$opsLink('notifications', 'notifications', 'fa-bell', 'notifications');
$opsLink('profile', 'profile', 'fa-user-gear', 'profile');
if (rateb_can('website.view') || rateb_can('website.manage') || rateb_is_super_admin()) {
    $opsSection(__('website') ?: 'Website', [
        ['website', 'website', 'fa-globe', 'website', 'website.view'],
        ['website/builder', 'website_builder', 'fa-table-columns', 'website', 'website.builder.manage'],
        ['website/pages', 'website_pages', 'fa-file-lines', 'website', 'website.pages.manage'],
        ['website/theme', 'website_theme', 'fa-palette', 'website', 'website.theme.manage'],
        ['website/media', 'website_media', 'fa-images', 'website', 'website.media.manage'],
        ['website/menus', 'website_menus', 'fa-bars', 'website', 'website.builder.manage'],
        ['website/forms', 'website_forms', 'fa-list-check', 'website', 'website.forms.manage'],
    ], 'fa-globe');
}
