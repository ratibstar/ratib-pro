<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/panel-users.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/panel-users.php`.
 */
/**
 * Control Panel Users - Landing for user management. Uses simple filename.
 * Add/edit admins via Control Admins page.
 */
if (!defined('IS_CONTROL_PANEL')) define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_SYSTEM_SETTINGS, 'view_control_system_settings', 'manage_control_users');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Control Panel Users', [], []);
?>

<div class="table-card">
    <h2 class="mb-3"><i class="fas fa-users-cog me-2"></i>Control Panel Users</h2>
    <div class="control-settings-intro">
        <strong>Control Panel Users</strong> — Table: <code>control_admins</code>. Users who can log into the control panel only. <strong>Not</strong> Ratib Pro users. For Ratib Pro users per country, use <a href="<?php echo pageUrl('control/country-users.php'); ?>?control=1" class="text-decoration-none">Country Users</a>.
    </div>
    <p class="text-muted mb-3">Manage who can log into the control panel and set permissions per user. Add, edit, or remove control panel accounts and their access rights.</p>
    <a href="<?php echo pageUrl('control/admins.php'); ?>?control=1" class="btn-control btn-primary" data-permission="control_admins,view_control_admins,manage_control_users">
        <i class="fas fa-user-shield"></i> Manage Control Admins
    </a>
    <ul class="feature-list">
        <li><i class="fas fa-check"></i> Add, edit, and delete control panel admin accounts</li>
        <li><i class="fas fa-key"></i> Set permissions per user (Permissions button on each row)</li>
        <li><i class="fas fa-list"></i> Search and paginate admins</li>
    </ul>
</div>

<?php endControlLayout(); ?>
