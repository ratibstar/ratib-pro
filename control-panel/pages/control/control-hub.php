<?php
/**
 * Unified Control Hub — one page linking to major control panel and admin surfaces.
 * URL: …/pages/control/control-hub.php?control=1 (via control-panel routing).
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
requireControlPermission(CONTROL_PERM_DASHBOARD);

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

$siteRootUrl = rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/');
if ($siteRootUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $siteRootUrl = $host !== '' ? ($scheme . '://' . $host) : '';
}
$controlCenterUrl = rtrim($siteRootUrl, '/') . '/admin/control-center.php';
$registrationPageUrl = rtrim($siteRootUrl, '/') . '/pages/home.php?open=register&plan=gold&years=1';

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
    } catch (Throwable $e) { /* ignore */
    }
}

$fullBaseUrl = rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/') . (function_exists('getBaseUrl') ? getBaseUrl() : '');
$panelSettingsHref = rtrim($fullBaseUrl, '/') . '/pages/control/panel-settings.php?control=1';

$designedAppUrl = defined('DESIGNED_APP_URL') ? trim((string) DESIGNED_APP_URL) : '';

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Control hub', ['css/system-settings.css'], []);
?>

<p class="control-settings-intro mb-3">
    <strong><i class="fas fa-layer-group me-2"></i>Control hub</strong>
    — Quick links to operations, public site copy, infrastructure, and deep admin tools. Sidebar entries use the same permission gates; items you are not allowed to use stay hidden.
</p>

<div class="control-settings-intro mb-2"><strong>Overview</strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card" data-permission="control_dashboard">
        <h3><i class="fas fa-tachometer-alt"></i> Dashboard</h3>
        <p>KPIs, quick actions, and recent activity.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Open dashboard</a>
    </div>
</div>

<div class="control-settings-intro mb-2"><strong>Core management</strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card" data-permission="control_select_country">
        <h3><i class="fas fa-globe"></i> Select country</h3>
        <p>Switch the active country workspace.</p>
        <a href="<?php echo htmlspecialchars(pageUrl('select-country.php') . '?control=1', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-location-dot"></i> Select country</a>
    </div>
    <div class="control-settings-card" data-permission="control_countries,view_control_countries">
        <h3><i class="fas fa-list"></i> Countries</h3>
        <p>Manage country records and availability.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/countries.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-flag"></i> Manage countries</a>
    </div>
    <div class="control-settings-card" data-permission="control_agencies,view_control_agencies">
        <h3><i class="fas fa-building"></i> Agencies</h3>
        <p>Agencies, databases, and program instances.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/agencies.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-building"></i> Manage agencies</a>
    </div>
    <?php if ($canViewCountryUsers): ?>
    <div class="control-settings-card" data-permission="control_country_users,view_control_country_users,control_agencies,view_control_agencies,open_control_agency">
        <h3><i class="fas fa-globe-americas"></i> Country users</h3>
        <p>Users scoped to country / agency access.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/country-users.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-users"></i> Country users</a>
    </div>
    <?php endif; ?>
</div>

<div class="control-settings-intro mb-2"><strong>Registration &amp; public site</strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card" data-permission="control_registration_requests,view_control_registration,view_all_control_registration">
        <h3><i class="fas fa-user-plus"></i> Registration requests</h3>
        <p>Review and approve agency registrations.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/registration-requests.php') . '&all_dates=1', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-clipboard-check"></i> Open queue</a>
    </div>
    <div class="control-settings-card" data-permission="control_support_chats,view_control_support">
        <h3><i class="fas fa-comments"></i> Support chats</h3>
        <p>Operator support conversations.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/support-chats.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-headset"></i> Support chats</a>
    </div>
    <div class="control-settings-card">
        <h3><i class="fas fa-file-signature"></i> Public registration page</h3>
        <p>Open the marketing homepage registration flow (new tab).</p>
        <a href="<?php echo htmlspecialchars($registrationPageUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt"></i> Open registration</a>
    </div>
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings,edit_control_system_settings">
        <h3><i class="fas fa-file-lines"></i> Public site content</h3>
        <p>Homepage copy, nav labels, and CMS fields for <code>home.php</code>.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/site-content.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-pen-to-square"></i> Edit site content</a>
    </div>
    <?php if ($designedAppUrl !== ''): ?>
    <div class="control-settings-card" data-permission="control_designed_site,view_control_designed_site">
        <h3><i class="fas fa-palette"></i> Designed site</h3>
        <p>Separate Designed experience (if configured).</p>
        <a href="<?php echo htmlspecialchars($designedAppUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt"></i> Open Designed</a>
    </div>
    <?php endif; ?>
</div>

<div class="control-settings-intro mb-2"><strong>Business modules</strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card" data-permission="<?php echo htmlspecialchars($countryProgramPerms, ENT_QUOTES, 'UTF-8'); ?>">
        <h3><i class="fas fa-flag"></i> Country program</h3>
        <p>Government and program entry points.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/country-program.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-diagram-project"></i> Country program</a>
    </div>
    <div class="control-settings-card" data-permission="control_accounting,view_control_accounting">
        <h3><i class="fas fa-calculator"></i> Accounting</h3>
        <p>Control-panel accounting workspace.</p>
        <a href="<?php echo htmlspecialchars(pageUrl('control/accounting.php') . '?control=1', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-coins"></i> Accounting</a>
    </div>
    <div class="control-settings-card" data-permission="control_hr,view_control_hr">
        <h3><i class="fas fa-user-tie"></i> HR center</h3>
        <p>HR tools in the control panel.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/hr.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-people-group"></i> HR center</a>
    </div>
    <div class="control-settings-card" data-permission="control_government,view_control_government,gov_admin">
        <h3><i class="fas fa-shield-halved"></i> Government control</h3>
        <p>Government / compliance surfaces.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/government.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-shield"></i> Government</a>
    </div>
    <div class="control-settings-card" data-permission="control_government,view_control_government,gov_admin">
        <h3><i class="fas fa-map-location-dot"></i> Tracking map</h3>
        <p>Map and tracking overview.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-map.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light"><i class="fas fa-map"></i> Tracking map</a>
    </div>
    <div class="control-settings-card" data-permission="control_government,manage_control_government,gov_admin">
        <h3><i class="fas fa-qrcode"></i> Tracking onboarding</h3>
        <p>Onboarding QR and device flows.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-onboarding.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light"><i class="fas fa-mobile-screen"></i> Onboarding</a>
    </div>
    <div class="control-settings-card" data-permission="control_government,view_control_government,gov_admin">
        <h3><i class="fas fa-heart-pulse"></i> Tracking health</h3>
        <p>Health checks for tracking services.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-health.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light"><i class="fas fa-wave-square"></i> Tracking health</a>
    </div>
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings,edit_control_system_settings,manage_control_roles">
        <h3><i class="fas fa-sliders"></i> Country profiles</h3>
        <p>Per-country labels and field requirements.</p>
        <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/country-profiles.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-sliders-h"></i> Country profiles</a>
    </div>
</div>

<div class="control-settings-intro mb-2"><strong>Administration &amp; infrastructure</strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings,control_dashboard">
        <h3><i class="fas fa-diagram-project"></i> Rollout control</h3>
        <p>System flags and rollout (Admin Control Center, new tab).</p>
        <a href="<?php echo htmlspecialchars($controlCenterUrl . '#system-flags', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-warning" target="_blank" rel="noopener noreferrer"><i class="fas fa-flag"></i> Rollout / flags</a>
    </div>
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings">
        <h3><i class="fas fa-tools"></i> Admin Control Center</h3>
        <p>Deep tools: tenant, database, policies, safety, logs (new tab).</p>
        <a href="<?php echo htmlspecialchars($controlCenterUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-warning" target="_blank" rel="noopener noreferrer"><i class="fas fa-screwdriver-wrench"></i> Open Control Center</a>
    </div>
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings">
        <h3><i class="fas fa-sliders-h"></i> Control panel settings</h3>
        <p>Panel users, admins, countries/agencies shortcuts.</p>
        <a href="<?php echo htmlspecialchars($panelSettingsHref, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-cog"></i> Panel settings</a>
    </div>
    <div class="control-settings-card" data-permission="control_system_settings,view_control_system_settings">
        <h3><i class="fas fa-network-wired"></i> Infrastructure</h3>
        <p>Runtime controls, operations dashboard, and provider integrations — same page, switch with the tabs.</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/infrastructure.php') . '&view=control', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-sliders-h"></i> Control</a>
            <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/infrastructure.php') . '&view=dashboard', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/infrastructure.php') . '&view=providers', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light"><i class="fas fa-plug"></i> Providers</a>
        </div>
    </div>
</div>

<p class="control-settings-footer-note mb-0">
    <i class="fas fa-info-circle"></i> Permissions match the left sidebar. Use the sidebar for day-to-day navigation; this hub is a single bookmark for everything you are allowed to open.
</p>

<?php endControlLayout(); ?>
