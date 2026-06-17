<?php
/**
 * Unified Control Hub — one page linking to major control panel and admin surfaces.
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/client-platform-nav.php';
require_once __DIR__ . '/../../includes/control/public-marketing-urls.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD);

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die(cp_t('common.db_unavailable'));
}

$siteRootUrl = rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/');
if ($siteRootUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $siteRootUrl = $host !== '' ? ($scheme . '://' . $host) : '';
}
$controlCenterUrl = rtrim($siteRootUrl, '/') . '/admin/control-center.php';
$registrationPageUrl = control_panel_registration_page_url($ctrl);
$clientPricingPageUrl = control_panel_pricing_page_url($ctrl);
$clientPlatformLinks = control_client_platform_links();
$legacyModuleKey = trim((string) ($_GET['legacy_module'] ?? ''));
$legacyModuleMap = [
    'agent' => 'Agents',
    'subagent' => 'SubAgents',
    'workers' => 'Workers',
    'partner_agencies' => 'Partner Agencies',
    'cases' => 'Cases',
    'reports' => 'Reports',
    'contact' => 'Contact',
    'notifications' => 'Notifications',
];
$legacyModuleLabel = $legacyModuleMap[$legacyModuleKey] ?? '';

$canViewCountryUsers = (strtolower(trim((string) ($_SESSION['control_username'] ?? ''))) === 'admin')
    || hasControlPermission(CONTROL_PERM_COUNTRY_USERS)
    || hasControlPermission('view_control_country_users')
    || hasControlPermission(CONTROL_PERM_AGENCIES)
    || hasControlPermission('view_control_agencies')
    || hasControlPermission('open_control_agency');

$countryProgramPerms = 'control_government,view_control_government,gov_admin,control_admins';
if ($ctrl) {
    try {
        $chkCp = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
        if ($chkCp && $chkCp->num_rows > 0) {
            $rcp = $ctrl->query('SELECT slug FROM control_countries WHERE is_active = 1');
            if ($rcp) {
                while ($crow = $rcp->fetch_assoc()) {
                    $countryProgramPerms .= ',country_' . $crow['slug'];
                }
                $rcp->close();
            }
        }
    } catch (Throwable $e) { /* ignore */ }
}

$fullBaseUrl = rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/') . (function_exists('getBaseUrl') ? getBaseUrl() : '');
$panelSettingsHref = rtrim($fullBaseUrl, '/') . '/pages/control/panel-settings.php?control=1';

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Control hub', ['css/system-settings.css'], []);
?>

<p class="control-settings-intro mb-3">
    <strong><i class="fas fa-layer-group me-2"></i><?php echo htmlspecialchars(cp_t('page.control_hub'), ENT_QUOTES, 'UTF-8'); ?></strong>
    — <?php echo htmlspecialchars(cp_t('hub.intro'), ENT_QUOTES, 'UTF-8'); ?>
</p>

<?php if ($legacyModuleLabel !== ''): ?>
<div class="alert alert-info mb-3" role="status">
    <i class="fas fa-compass me-2"></i>
    <?php echo htmlspecialchars(cp_t('hub.legacy_notice', ['module' => $legacyModuleLabel]), ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<div class="control-settings-intro mb-2"><strong><?php echo htmlspecialchars(cp_t('hub.overview'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card" data-permission="control_dashboard">
        <h3><i class="fas fa-tachometer-alt"></i> <?php echo htmlspecialchars(cp_t('nav.dashboard'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.dashboard_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars(cp_t('hub.open_dashboard'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_dashboard">
        <h3><i class="fas fa-book"></i> <?php echo htmlspecialchars(cp_t('nav.help_center'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.help_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/help-center.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-info"><i class="fas fa-circle-question"></i> <?php echo htmlspecialchars(cp_t('hub.open_help'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
</div>

<div class="control-settings-intro mb-2"><strong><?php echo htmlspecialchars(cp_t('hub.client_platform'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card" data-permission="control_dashboard">
        <h3><i class="fas fa-chart-pie"></i> <?php echo htmlspecialchars(cp_t('nav.client_hub'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.client_hub_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($clientPlatformLinks['hub']['href'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars(cp_t('hub.open_client_hub'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_dashboard">
        <h3><i class="fas fa-server"></i> <?php echo htmlspecialchars(cp_t('nav.services'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.services_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($clientPlatformLinks['services']['href'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars(cp_t('hub.open_services'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_dashboard">
        <h3><i class="fas fa-globe"></i> <?php echo htmlspecialchars(cp_t('nav.domains'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.domains_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($clientPlatformLinks['domains']['href'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars(cp_t('hub.open_domains'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_dashboard">
        <h3><i class="fas fa-bag-shopping"></i> <?php echo htmlspecialchars(cp_t('hub.orders_billing'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.orders_billing_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo htmlspecialchars($clientPlatformLinks['orders']['href'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light"><i class="fas fa-receipt"></i> <?php echo htmlspecialchars(cp_t('nav.orders'), ENT_QUOTES, 'UTF-8'); ?></a>
            <a href="<?php echo htmlspecialchars($clientPlatformLinks['billing']['href'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light"><i class="fas fa-file-invoice-dollar"></i> <?php echo htmlspecialchars(cp_t('nav.billing'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </div>
</div>

<div class="control-settings-intro mb-2"><strong><?php echo htmlspecialchars(cp_t('section.core_management'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card" data-permission="control_select_country">
        <h3><i class="fas fa-globe"></i> <?php echo htmlspecialchars(cp_t('nav.select_country'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.select_country_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(pageUrl('select-country.php') . '?control=1', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars(cp_t('nav.select_country'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_countries,view_control_countries">
        <h3><i class="fas fa-list"></i> <?php echo htmlspecialchars(cp_t('countries.title'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.countries_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/countries.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-flag"></i> <?php echo htmlspecialchars(cp_t('hub.manage_countries'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_agencies,view_control_agencies">
        <h3><i class="fas fa-building"></i> <?php echo htmlspecialchars(cp_t('agencies.title'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.agencies_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/agencies.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-building"></i> <?php echo htmlspecialchars(cp_t('hub.manage_agencies'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <?php if ($canViewCountryUsers): ?>
    <div class="control-settings-card" data-permission="control_country_users,view_control_country_users,control_agencies,view_control_agencies,open_control_agency">
        <h3><i class="fas fa-globe-americas"></i> <?php echo htmlspecialchars(cp_t('nav.country_users'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.country_users_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/country-users.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-users"></i> <?php echo htmlspecialchars(cp_t('nav.country_users'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <?php endif; ?>
</div>

<div class="control-settings-intro mb-2"><strong><?php echo htmlspecialchars(cp_t('hub.registration_public'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card" data-permission="control_registration_requests,view_control_registration,view_all_control_registration">
        <h3><i class="fas fa-user-plus"></i> <?php echo htmlspecialchars(cp_t('nav.registration_requests'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.registration_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/registration-requests.php') . '&all_dates=1', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-clipboard-check"></i> <?php echo htmlspecialchars(cp_t('hub.open_queue'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_support_chats,view_control_support">
        <h3><i class="fas fa-comments"></i> <?php echo htmlspecialchars(cp_t('nav.support_chats'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.support_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/support-chats.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-headset"></i> <?php echo htmlspecialchars(cp_t('hub.support_chats'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card">
        <h3><i class="fas fa-tags"></i> <?php echo htmlspecialchars(cp_t('hub.client_pricing'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.client_pricing_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($clientPricingPageUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt"></i> <?php echo htmlspecialchars(cp_t('hub.view_pricing'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a href="<?php echo htmlspecialchars($registrationPageUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary ms-2" target="_blank" rel="noopener noreferrer"><i class="fas fa-file-signature"></i> <?php echo htmlspecialchars(cp_t('hub.open_checkout'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings,edit_control_system_settings">
        <h3><i class="fas fa-globe"></i> <?php echo htmlspecialchars(cp_t('nav.public_site_content'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.public_site_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/site-content.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-pen-to-square"></i> <?php echo htmlspecialchars(cp_t('hub.open_site_editor'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
</div>

<div class="control-settings-intro mb-2"><strong><?php echo htmlspecialchars(cp_t('section.business_modules'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card" data-permission="<?php echo htmlspecialchars($countryProgramPerms, ENT_QUOTES, 'UTF-8'); ?>">
        <h3><i class="fas fa-flag"></i> <?php echo htmlspecialchars(cp_t('nav.country_program'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.country_program_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/country-program.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-diagram-project"></i> <?php echo htmlspecialchars(cp_t('nav.country_program'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_accounting,view_control_accounting">
        <h3><i class="fas fa-calculator"></i> <?php echo htmlspecialchars(cp_t('nav.accounting'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.accounting_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(pageUrl('control/accounting.php') . '?control=1', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-coins"></i> <?php echo htmlspecialchars(cp_t('nav.accounting'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_hr,view_control_hr">
        <h3><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars(cp_t('hub.hr_center'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.hr_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/hr.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-people-group"></i> <?php echo htmlspecialchars(cp_t('hub.hr_center'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_government,view_control_government,gov_admin">
        <h3><i class="fas fa-shield-halved"></i> <?php echo htmlspecialchars(cp_t('nav.government_control'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.government_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/government.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-shield"></i> <?php echo htmlspecialchars(cp_t('gov.title'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_government,view_control_government,gov_admin">
        <h3><i class="fas fa-map-location-dot"></i> <?php echo htmlspecialchars(cp_t('hub.tracking_map'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.tracking_map_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-map.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light"><i class="fas fa-map"></i> <?php echo htmlspecialchars(cp_t('hub.tracking_map'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_government,manage_control_government,gov_admin">
        <h3><i class="fas fa-qrcode"></i> <?php echo htmlspecialchars(cp_t('hub.onboarding'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.onboarding_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-onboarding.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light"><i class="fas fa-mobile-screen"></i> <?php echo htmlspecialchars(cp_t('hub.onboarding'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_government,view_control_government,gov_admin">
        <h3><i class="fas fa-heart-pulse"></i> <?php echo htmlspecialchars(cp_t('hub.tracking_health'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.tracking_health_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-health.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light"><i class="fas fa-wave-square"></i> <?php echo htmlspecialchars(cp_t('hub.tracking_health'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings,edit_control_system_settings,manage_control_roles">
        <h3><i class="fas fa-sliders"></i> <?php echo htmlspecialchars(cp_t('nav.country_profiles'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.country_profiles_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/country-profiles.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-sliders-h"></i> <?php echo htmlspecialchars(cp_t('nav.country_profiles'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
</div>

<div class="control-settings-intro mb-2"><strong><?php echo htmlspecialchars(cp_t('hub.admin_infra'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings,control_dashboard">
        <h3><i class="fas fa-diagram-project"></i> <?php echo htmlspecialchars(cp_t('nav.rollout_control'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.rollout_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($controlCenterUrl . '#system-flags', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-warning" target="_blank" rel="noopener noreferrer"><i class="fas fa-flag"></i> <?php echo htmlspecialchars(cp_t('hub.rollout_flags'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings">
        <h3><i class="fas fa-tools"></i> <?php echo htmlspecialchars(cp_t('nav.admin_control_center'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.control_center_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($controlCenterUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-warning" target="_blank" rel="noopener noreferrer"><i class="fas fa-screwdriver-wrench"></i> <?php echo htmlspecialchars(cp_t('hub.open_control_center'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings">
        <h3><i class="fas fa-sliders-h"></i> <?php echo htmlspecialchars(cp_t('nav.panel_settings'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.panel_settings_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($panelSettingsHref, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-cog"></i> <?php echo htmlspecialchars(cp_t('hub.panel_settings'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings">
        <h3><i class="fas fa-network-wired"></i> <?php echo htmlspecialchars(cp_t('nav.infrastructure'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars(cp_t('hub.infrastructure_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/infrastructure.php') . '&view=control', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-sliders-h"></i> <?php echo htmlspecialchars(cp_t('hub.infra_control'), ENT_QUOTES, 'UTF-8'); ?></a>
            <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/infrastructure.php') . '&view=dashboard', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light"><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars(cp_t('hub.infra_dashboard'), ENT_QUOTES, 'UTF-8'); ?></a>
            <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/infrastructure.php') . '&view=providers', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light"><i class="fas fa-plug"></i> <?php echo htmlspecialchars(cp_t('hub.infra_providers'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </div>
</div>

<p class="control-settings-footer-note mb-0">
    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars(cp_t('hub.footer'), ENT_QUOTES, 'UTF-8'); ?>
</p>

<?php endControlLayout(); ?>
