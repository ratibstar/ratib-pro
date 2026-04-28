<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/system-settings.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/system-settings.php`.
 */
/**
 * System Settings - Control panel entry.
 * Full system settings (users, visa types, etc.) are in Ratib Pro; this page links there or shows a stub.
 */
if (!defined('SYSTEM_SETTINGS_DIRECT_INCLUDE')) {
    require_once __DIR__ . '/../includes/config.php';
    if (empty($_SESSION['control_logged_in'])) {
        header('Location: ' . pageUrl('login.php'));
        exit;
    }
    $ctrl = $GLOBALS['control_conn'] ?? null;
    $GLOBALS['ctrl'] = $ctrl;
    require_once __DIR__ . '/../includes/control/layout-wrapper.php';
    startControlLayout('System Settings', [], []);
}
$agencyName = $_SESSION['control_agency_name'] ?? 'your agency';
$ratibUrl = defined('RATIB_PRO_URL') ? RATIB_PRO_URL : '#';
?>
<div class="p-4">
    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle me-2"></i>System settings (users, visa types, recruitment)</h5>
        <p class="mb-0">To manage users, visa types, and other program settings for <strong><?php echo htmlspecialchars($agencyName); ?></strong>, open Ratib Pro with an agency selected.</p>
        <?php if ($ratibUrl !== '#'): ?>
        <p class="mt-2 mb-0"><a href="<?php echo htmlspecialchars($ratibUrl); ?>?control=1&agency_id=<?php echo (int)($_SESSION['control_agency_id'] ?? 0); ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Open Ratib Pro <i class="fas fa-external-link-alt ms-1"></i></a></p>
        <?php else: ?>
        <p class="mt-2 mb-0 text-muted small">Set <code>RATIB_PRO_URL</code> in <code>config/env.php</code> (e.g. <code>https://out.ratib.sa</code>) to enable the link.</p>
        <?php endif; ?>
    </div>
    <p class="text-muted small">To configure the control panel itself (admins, countries, agencies), use <a href="<?php echo pageUrl('control/panel-settings.php'); ?>">Control Panel Settings</a>.</p>
</div>
<?php
if (!defined('SYSTEM_SETTINGS_DIRECT_INCLUDE')) {
    endControlLayout();
}
