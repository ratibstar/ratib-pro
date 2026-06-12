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
]);
$opsSection(__('inventory'), [
    ['inventory', 'inventory', 'fa-boxes-stacked', 'inventory'],
    ['inventory-batches', 'inventory_batches', 'fa-layer-group', 'inventory'],
    ['inventory-audits', 'inventory_audits', 'fa-clipboard-check', 'inventory'],
    ['warehouses', 'warehouses', 'fa-warehouse', 'inventory'],
    ['stock-movements', 'stock_movements', 'fa-arrows-rotate', 'inventory'],
    ['product-categories', 'product_categories', 'fa-tags', 'inventory'],
]);
$opsSection(__('suppliers'), [
    ['suppliers', 'suppliers', 'fa-truck-field', 'suppliers'],
    ['supplier-evaluations', 'supplier_evaluations', 'fa-star-half-stroke', 'suppliers'],
    ['supplier-classifications', 'supplier_classifications', 'fa-tags', 'suppliers'],
    ['supplier-kpi', 'supplier_kpi', 'fa-chart-line', 'suppliers'],
]);
$opsSection(__('contracts') . ' / ' . __('assets'), [
    ['contracts', 'contracts', 'fa-file-contract', 'contracts'],
    ['contract-renewals', 'contract_renewals', 'fa-rotate', 'contracts'],
    ['tenders', 'tenders', 'fa-gavel', 'tenders'],
    ['assets', 'assets', 'fa-toolbox', 'assets'],
    ['asset-maintenance', 'asset_maintenance', 'fa-wrench', 'assets'],
    ['asset-assignments', 'asset_assignments', 'fa-user-check', 'assets'],
    ['asset-depreciation', 'asset_depreciation', 'fa-chart-line', 'assets'],
    ['medical-devices', 'medical_devices', 'fa-stethoscope', 'medical_devices'],
    ['device-maintenance', 'device_maintenance', 'fa-screwdriver-wrench', 'medical_devices'],
    ['device-spare-parts', 'device_spare_parts', 'fa-gears', 'medical_devices'],
    ['device-warranty', 'device_warranty', 'fa-shield-halved', 'medical_devices'],
    ['accounting', 'accounting_module', 'fa-calculator', 'accounting'],
    ['reports', 'reports', 'fa-chart-pie', 'reports'],
    ['reports/procurement', 'procurement_analytics', 'fa-cart-shopping', 'procurement'],
    ['reports/kpi', 'company_kpi', 'fa-gauge-high', 'reports'],
    ['reports/cost-analysis', 'cost_analysis', 'fa-coins', 'reports'],
    ['reports/supplier-performance', 'supplier_performance_report', 'fa-truck-field', 'reports'],
    ['reports/inventory-valuation', 'inventory_valuation_report', 'fa-boxes-stacked', 'inventory'],
    ['documents', 'documents', 'fa-folder-open', 'documents'],
]);
$opsLink('notifications', 'notifications', 'fa-bell');
$opsLink('profile', 'profile', 'fa-user-gear');
