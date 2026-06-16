<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/panel-settings.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/panel-settings.php`.
 */
/**
 * Control Panel Settings Hub - admins, countries, agencies, and main-platform links.
 * Distinct filename from admins.php to avoid server routing confusion.
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_SYSTEM_SETTINGS, 'view_control_system_settings', 'edit_control_system_settings', 'manage_control_users', 'manage_control_roles');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Control Panel Settings', ['css/system-settings.css'], []);
?>
<?php
$agencyName = $_SESSION['control_agency_name'] ?? 'your agency';
$platformRootUrl = rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/');
if ($platformRootUrl === '' && isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $platformRootUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
}
$platformProgramUrl = $platformRootUrl !== '' ? ($platformRootUrl . '/pages/dashboard.php') : null;
?>
<div class="control-settings-intro control-settings-rateb-program">
    <strong><i class="fas fa-cog me-2"></i>Main Platform Program Settings</strong>
    <p class="mb-2">Manage users, visa types, and operational settings for <strong><?php echo htmlspecialchars($agencyName); ?></strong> in the main platform workspace.</p>
    <?php if ($platformProgramUrl): ?>
    <a href="<?php echo htmlspecialchars($platformProgramUrl); ?>?control=1&agency_id=<?php echo (int)($_SESSION['control_agency_id'] ?? 0); ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
        <i class="fas fa-external-link-alt me-1"></i>Open Main Platform
    </a>
    <?php else: ?>
    <p class="text-muted small mb-0">Set <code>SITE_URL</code> in <code>config/env.php</code> to enable the link.</p>
    <?php endif; ?>
</div>

<div class="control-settings-intro">
    <strong>Control Panel Settings</strong> — Configure the control panel itself: admins, countries, agencies.
</div>

<div class="control-settings-grid">
    <div class="control-settings-card" data-permission="control_system_settings,manage_control_users,view_control_system_settings">
        <h3><i class="fas fa-users-cog"></i> Users</h3>
        <p>Manage users who can log into the control panel (admin, ali, sakline, etc.). Add, edit, or reset passwords.</p>
        <a href="<?php echo pageUrl('control/panel-users.php'); ?>?control=1" class="btn btn-primary">
            <i class="fas fa-users"></i> Manage Users
        </a>
    </div>

    <div class="control-settings-card" data-permission="control_admins,view_control_admins">
        <h3><i class="fas fa-user-shield"></i> Control Admins</h3>
        <p>Manage control_admin_permissions (legacy). For user management, use Control Panel Users above.</p>
        <a href="<?php echo pageUrl('control/admins.php'); ?>?control=1" class="btn btn-secondary">
            <i class="fas fa-shield-alt"></i> Admins
        </a>
    </div>

    <div class="control-settings-card" data-permission="control_countries,view_control_countries">
        <h3><i class="fas fa-globe"></i> Countries</h3>
        <p>Manage countries available in the control panel. Countries group agencies by region.</p>
        <a href="<?php echo pageUrl('control/countries.php'); ?>?control=1" class="btn btn-primary">
            <i class="fas fa-list"></i> Manage Countries
        </a>
    </div>

    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings,edit_control_system_settings,manage_control_roles">
        <h3><i class="fas fa-sliders"></i> Country Profiles</h3>
        <p>Edit country-specific worker labels and required fields without touching code.</p>
        <a href="<?php echo pageUrl('control/country-profiles.php'); ?>?control=1" class="btn btn-primary">
            <i class="fas fa-pen-to-square"></i> Manage Profiles
        </a>
    </div>

    <div class="control-settings-card" data-permission="control_agencies,view_control_agencies">
        <h3><i class="fas fa-building"></i> Agencies</h3>
        <p>Manage agencies (program instances). Each agency has its own database and site URL.</p>
        <a href="<?php echo pageUrl('control/agencies.php'); ?>?control=1" class="btn btn-primary">
            <i class="fas fa-building"></i> Manage Agencies
        </a>
    </div>

    <div class="control-settings-card" data-permission="control_registration_requests,view_control_registration">
        <h3><i class="fas fa-user-plus"></i> Registration Requests</h3>
        <p>Review and approve new agency registration requests.</p>
        <a href="<?php echo pageUrl('control/registration-requests.php'); ?>?control=1" class="btn btn-primary">
            <i class="fas fa-clipboard-check"></i> View Requests
        </a>
    </div>

    <?php if (function_exists('control_panel_page_with_control') && control_panel_page_with_control('control/site-content.php')) { ?>
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings">
        <h3><i class="fas fa-globe"></i> Public site content</h3>
        <p>Edit hero copy and optional image paths on the public homepage.</p>
        <a href="<?php echo pageUrl('control/site-content.php'); ?>?control=1" class="btn btn-primary">
            <i class="fas fa-pen-to-square"></i> Edit homepage copy
        </a>
    </div>
    <?php } ?>
</div>

<p class="control-settings-footer-note">
    <i class="fas fa-info-circle"></i> To manage agency runtime settings (Visa Types, Office Manager, etc.), use the <strong>Open Main Platform</strong> button above or open an agency from the dashboard, then use <strong>System Settings</strong> in program navigation.
</p>

<?php endControlLayout(); ?>
