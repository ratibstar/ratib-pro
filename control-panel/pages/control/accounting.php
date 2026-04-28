<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/accounting.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/accounting.php`.
 */
/**
 * Control Panel - Accounting (control-panel data only, no Ratib Pro)
 * Uses: control_accounting_transactions, control_support_payments, control_registration_requests
 */
require_once __DIR__ . '/../../includes/config.php';

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_ACCOUNTING, 'view_control_accounting', 'manage_control_accounting');

/* Legacy ?tab=chart|cost|bank|journal → dashboard + modal hash (avoids duplicating the same tables in-page and in modals). */
$tabEarly = isset($_GET['tab']) ? (string) $_GET['tab'] : 'dashboard';
$cpAccLegacyTabToModal = [
    'chart' => 'chartModal',
    'cost' => 'costModal',
    'bank' => 'bankModal',
    'journal' => 'ledgerModal',
    'expenses' => 'expensesModal',
    'receipts' => 'receiptsModal',
    'vouchers' => 'vouchersModal',
    'invoices' => 'invoicesModal',
    'approval' => 'approvalModal',
    'reconcile' => 'reconcileModal',
    'reports' => 'reportsModal',
    'support' => 'supportModal',
    'registrations' => 'registrationRevenueModal',
];
if (isset($cpAccLegacyTabToModal[$tabEarly])) {
    $q = $_GET;
    $q['tab'] = 'dashboard';
    if (!isset($q['control'])) {
        $q['control'] = '1';
    }
    $dest = pageUrl('control/accounting.php') . '?' . http_build_query($q) . '#' . $cpAccLegacyTabToModal[$tabEarly];
    header('Location: ' . $dest, true, 302);
    exit;
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
$additionalCSS = [
    'css/accounting-control.css',
    'css/accounting-modals.css',
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css',
];
/* Flatpickr loads in-body before content; footer runs Chart + page helpers + accounting modals (needs flatpickr + full modal markup). */
$additionalJS = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
    'js/accounting-page.js',
    'js/accounting-modals.js',
];
startControlLayout('Accounting', $additionalCSS, []);

?>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
<?php

require_once __DIR__ . '/../../includes/control/accounting-content.php';

endControlLayout($additionalJS);
