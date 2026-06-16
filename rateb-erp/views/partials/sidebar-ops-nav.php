<?php
declare(strict_types=1);

/**
 * Company operations nav — full CRUD routes under unified /admin shell.
 * Shown for company users and super admins (permissions + plan modules apply).
 */
require RATEB_ROOT . '/views/partials/sidebar-nav.php';

$opsSection(__('procurement'), [
    ['purchase-requests', 'purchase_requests', 'fa-file-circle-plus', 'procurement'],
    ['purchase-orders', 'purchase_orders', 'fa-file-invoice', 'procurement'],
    ['rfq', 'rfq', 'fa-comments-dollar', 'procurement'],
    ['quotations', 'quotations', 'fa-file-signature', 'procurement'],
    ['workflows', 'workflows', 'fa-diagram-project', 'workflows'],
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
$opsSection(__('suppliers'), [
    ['suppliers', 'suppliers', 'fa-truck-field', 'suppliers'],
    ['supplier-comms', 'supplier_comms', 'fa-comments', 'suppliers'],
    ['supplier-evaluations', 'supplier_evaluations', 'fa-star-half-stroke', 'suppliers'],
    ['supplier-classifications', 'supplier_classifications', 'fa-tags', 'suppliers'],
    ['supplier-kpi', 'supplier_kpi', 'fa-chart-line', 'suppliers'],
], 'fa-truck-field');
$opsSection(__('human_resources'), [
    ['hr', 'hr_overview', 'fa-gauge-high', 'hr'],
    ['hr/employees', 'hr_employees', 'fa-id-badge', 'hr'],
    ['hr/departments', 'hr_departments', 'fa-sitemap', 'hr'],
    ['hr/attendance', 'hr_attendance', 'fa-clock', 'hr'],
    ['hr/leaves', 'hr_leaves', 'fa-calendar-minus', 'hr'],
    ['hr/leave-types', 'leave_types', 'fa-list', 'hr'],
    ['hr/payroll', 'hr_payroll', 'fa-money-check-dollar', 'hr'],
    ['hr/reports', 'hr_reports', 'fa-chart-column', 'hr'],
], 'fa-users-gear');
$opsSection(__('accounting_module'), [
    ['accounting', 'accounting_overview', 'fa-gauge-high', 'accounting'],
    ['accounting/reports', 'accounting_reports', 'fa-chart-pie', 'accounting', 'accounting.view'],
    ['chart-of-accounts', 'chart_of_accounts', 'fa-list', 'accounting'],
    ['accounting/coa-tree', 'coa_full_tree', 'fa-sitemap', 'accounting', 'accounting.view'],
    ['journal-entries', 'journal_entries', 'fa-book', 'accounting'],
    ['accounting/entry-approval', 'entry_approval', 'fa-check-double', 'accounting', 'accounting.view'],
    ['accounting/supplier-payments', 'supplier_payments', 'fa-hand-holding-dollar', 'accounting', 'accounting.view'],
    ['cash-vouchers', 'cash_vouchers', 'fa-money-bill-wave', 'accounting'],
    ['accounting/voucher-approval', 'voucher_approval', 'fa-stamp', 'accounting', 'accounting.view'],
    ['fiscal-periods', 'fiscal_periods', 'fa-calendar-days', 'accounting'],
    ['cost-centers', 'cost_centers', 'fa-diagram-project', 'accounting'],
    ['bank-accounts', 'bank_accounts', 'fa-building-columns', 'accounting'],
    ['accounting/bank-reconciliation', 'bank_reconciliation', 'fa-scale-balanced', 'accounting', 'accounting.view'],
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
$opsLink('notifications', 'notifications', 'fa-bell');
$opsLink('profile', 'profile', 'fa-user-gear');
