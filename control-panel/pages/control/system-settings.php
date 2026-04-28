<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/system-settings.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/system-settings.php`.
 */
/**
 * Control Panel - Ratib Program System Settings (when agency selected)
 * Shows the agency's system settings (users, visa types, etc.) within control panel layout.
 * When no agency selected, redirects to Control Panel Settings.
 */
if (!defined('IS_CONTROL_PANEL')) define('IS_CONTROL_PANEL', true);
define('SYSTEM_SETTINGS_DIRECT_INCLUDE', true);
require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// No agency selected → go to Control Panel Settings instead
if (empty($_SESSION['control_agency_id']) && empty($_SESSION['control_use_own_program'])) {
    header('Location: ' . pageUrl('control/panel-settings.php'));
    exit;
}

$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 0;

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_SYSTEM_SETTINGS, 'view_control_system_settings', 'edit_control_system_settings', 'manage_control_users', 'manage_control_roles');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

$baseUrl = getBaseUrl();
$cssVer = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ratib Program - System Settings</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/control/system.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/nav.css'); ?>?v=<?php echo $cssVer; ?>">
    <link rel="stylesheet" href="<?php echo asset('css/system-settings.css'); ?>?v=<?php echo $cssVer; ?>">
    <link rel="stylesheet" href="<?php echo asset('css/control/system-settings-inline.css'); ?>?v=<?php echo $cssVer; ?>">
    <script src="<?php echo asset('js/utils/header-config.js'); ?>"></script>
</head>
<body class="control-system-body">
    <header class="control-header">
        <div class="header-left">
            <h1><i class="fas fa-cog"></i> Ratib Program - System Settings</h1>
            <span class="header-subtitle"><?php echo htmlspecialchars($_SESSION['control_agency_name'] ?? 'My Program'); ?></span>
        </div>
        <div class="header-right">
            <a href="<?php echo pageUrl('control/panel-settings.php'); ?>" class="btn btn-secondary me-2"><i class="fas fa-arrow-left"></i> Control Panel Settings</a>
            <span class="user-info"><?php echo htmlspecialchars($_SESSION['control_username'] ?? ''); ?></span>
            <a href="<?php echo pageUrl('logout.php'); ?>" class="btn-logout">Logout</a>
        </div>
    </header>

    <div class="control-layout">
        <?php include __DIR__ . '/../../includes/control/sidebar.php'; ?>

        <main class="control-content control-content-inline">
            <div class="content-header">
                <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
                <h2><i class="fas fa-cogs me-2"></i>Program Settings (Users, Visa Types, etc.)</h2>
            </div>
            <div class="system-settings-inline-content">
<?php
$_GET['control'] = '1';
require_once __DIR__ . '/../system-settings.php';
?>
            </div>
        </main>
    </div>

    <script src="<?php echo asset('js/control/system.js'); ?>?v=<?php echo time(); ?>"></script>
</body>
</html>
