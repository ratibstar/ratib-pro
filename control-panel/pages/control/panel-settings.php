<?php
/**
 * Control Panel Settings Hub - admins, countries, agencies, and main-platform links.
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
    die(cp_t('common.db_unavailable'));
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
    <strong><i class="fas fa-cog me-2"></i><?php echo htmlspecialchars(cp_t('settings.intro_platform'), ENT_QUOTES, 'UTF-8'); ?></strong>
    <p class="mb-2"><?php echo htmlspecialchars(cp_t('settings.intro_platform_desc', ['agency' => $agencyName]), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($platformProgramUrl): ?>
    <a href="<?php echo htmlspecialchars($platformProgramUrl); ?>?control=1&agency_id=<?php echo (int)($_SESSION['control_agency_id'] ?? 0); ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
        <i class="fas fa-external-link-alt me-1"></i><?php echo htmlspecialchars(cp_t('settings.open_main_platform'), ENT_QUOTES, 'UTF-8'); ?>
    </a>
    <?php else: ?>
    <p class="text-muted small mb-0"><?php echo htmlspecialchars(cp_t('settings.site_url_hint'), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
</div>

<div class="control-settings-intro">
    <strong><?php echo htmlspecialchars(cp_t('settings.intro_panel'), ENT_QUOTES, 'UTF-8'); ?></strong>
</div>

<div class="control-settings-grid">
    <div class="control-settings-card" data-permission="control_system_settings,manage_control_users,view_control_system_settings">
        <h3><i class="fas fa-users-cog"></i> <?php echo htmlspecialchars(cp_t('settings.users_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('settings.users_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo pageUrl('control/panel-users.php'); ?>?control=1" class="btn btn-primary">
            <i class="fas fa-users"></i> <?php echo htmlspecialchars(cp_t('settings.manage_users'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>

    <div class="control-settings-card" data-permission="control_admins,view_control_admins">
        <h3><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars(cp_t('settings.admins_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('settings.admins_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo pageUrl('control/admins.php'); ?>?control=1" class="btn btn-secondary">
            <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars(cp_t('settings.admins_btn'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>

    <div class="control-settings-card" data-permission="control_countries,view_control_countries">
        <h3><i class="fas fa-globe"></i> <?php echo htmlspecialchars(cp_t('settings.countries_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('settings.countries_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo pageUrl('control/countries.php'); ?>?control=1" class="btn btn-primary">
            <i class="fas fa-list"></i> <?php echo htmlspecialchars(cp_t('nav.manage_countries'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>

    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings,edit_control_system_settings,manage_control_roles">
        <h3><i class="fas fa-sliders"></i> <?php echo htmlspecialchars(cp_t('settings.profiles_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('settings.profiles_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo pageUrl('control/country-profiles.php'); ?>?control=1" class="btn btn-primary">
            <i class="fas fa-pen-to-square"></i> <?php echo htmlspecialchars(cp_t('settings.manage_profiles'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>

    <div class="control-settings-card" data-permission="control_agencies,view_control_agencies">
        <h3><i class="fas fa-building"></i> <?php echo htmlspecialchars(cp_t('settings.agencies_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('settings.agencies_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo pageUrl('control/agencies.php'); ?>?control=1" class="btn btn-primary">
            <i class="fas fa-building"></i> <?php echo htmlspecialchars(cp_t('nav.manage_agencies'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>

    <div class="control-settings-card" data-permission="control_registration_requests,view_control_registration">
        <h3><i class="fas fa-user-plus"></i> <?php echo htmlspecialchars(cp_t('settings.registration_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('settings.registration_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo pageUrl('control/registration-requests.php'); ?>?control=1" class="btn btn-primary">
            <i class="fas fa-clipboard-check"></i> <?php echo htmlspecialchars(cp_t('settings.view_requests'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>

    <?php if (function_exists('control_panel_page_with_control') && control_panel_page_with_control('control/site-content.php')) { ?>
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings">
        <h3><i class="fas fa-globe"></i> <?php echo htmlspecialchars(cp_t('settings.public_site_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('settings.public_site_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo pageUrl('control/site-content.php'); ?>?control=1" class="btn btn-primary">
            <i class="fas fa-pen-to-square"></i> <?php echo htmlspecialchars(cp_t('settings.edit_homepage'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>
    <?php } ?>
</div>

<p class="control-settings-footer-note">
    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars(cp_t('settings.footer_note'), ENT_QUOTES, 'UTF-8'); ?>
</p>

<?php endControlLayout(); ?>
