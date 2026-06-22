<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control/sidebar.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control/sidebar.php`.
 */
require_once __DIR__ . '/client-platform-nav.php';
require_once __DIR__ . '/public-marketing-urls.php';
require_once __DIR__ . '/rateb-erp-nav.php';
require_once __DIR__ . '/contact-center-nav.php';
require_once __DIR__ . '/sidebar-groups.php';
$logoUrl = (file_exists(__DIR__ . '/../../assets/logo.png')) ? asset('assets/logo.png') : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='44' height='44'%3E%3Crect width='44' height='44' rx='10' fill='%236b21a8'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.35em' fill='white' font-size='18' font-weight='bold'%3ER%3C/text%3E%3C/svg%3E";
$base = getBaseUrl();
$fullBaseUrl = rtrim(defined('SITE_URL') ? SITE_URL : '', '/') . $base;
$controlCenterUrl = rtrim(defined('SITE_URL') ? SITE_URL : '', '/') . '/admin/control-center.php';
$clientPlatformLinks = control_client_platform_links();
$clientPlatformActiveKey = control_client_platform_active_key();
$cpT = static function (string $key): string {
    return function_exists('cp_t') ? cp_t($key) : $key;
};
?>
<aside class="control-sidebar" id="control-sidebar">
    <div class="sidebar-header">
        <a href="<?php echo pageUrl('control/dashboard.php'); ?>" class="sidebar-brand">
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="RATEB" class="sidebar-logo">
        </a>
        <div class="sidebar-brand-title"><?php echo htmlspecialchars((string) ($_SESSION['control_username'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if (!empty($_SESSION['control_agency_name'])): ?>
        <div class="sidebar-context"><?php echo htmlspecialchars($_SESSION['control_agency_name'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php elseif (!empty($_SESSION['control_country_name'])): ?>
        <div class="sidebar-context"><?php echo htmlspecialchars($_SESSION['control_country_name'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <div class="sidebar-lang-switch"><?php include __DIR__ . '/lang-switcher.php'; ?></div>
    </div>
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <li><a href="<?php echo pageUrl('control/dashboard.php'); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-home"></i><span><?php echo htmlspecialchars($cpT('nav.dashboard'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/control-hub.php'), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'control-hub.php') ? 'active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-layer-group"></i><span><?php echo htmlspecialchars($cpT('nav.control_hub'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/help-center.php'), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'help-center.php') ? 'active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-book"></i><span><?php echo htmlspecialchars($cpT('nav.help_center'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php control_sidebar_group_open('rateb-erp', $cpT('section.rateb_erp'), 'fa-hospital'); ?>
            <li><a href="<?php echo htmlspecialchars(control_rateb_erp_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF'] ?? '') === 'rateb-erp.php') ? 'active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-hospital"></i><span><?php echo htmlspecialchars($cpT('nav.rateb_erp'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php
            $erpRouteCp = trim((string) ($_GET['route'] ?? ''), '/');
            $companyPortalActive = (basename($_SERVER['PHP_SELF'] ?? '') === 'rateb-erp-app.php' && strpos($erpRouteCp, 'company') === 0);
            ?>
            <li><a href="<?php echo htmlspecialchars(control_rateb_erp_public_url('company/login'), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem<?php echo $companyPortalActive ? ' active' : ''; ?>" data-permission="control_dashboard" target="_blank" rel="noopener"><i class="fas fa-building"></i><span><?php echo htmlspecialchars($cpT('nav.company_portal'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_rateb_erp_public_url('portals.php'), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem" data-permission="control_dashboard" target="_blank" rel="noopener"><i class="fas fa-link"></i><span><?php echo htmlspecialchars($cpT('nav.erp_links'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_rateb_erp_migrate_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF'] ?? '') === 'rateb-erp-migrate.php') ? 'active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-database"></i><span><?php echo htmlspecialchars($cpT('nav.erp_db_setup'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php
            $ratebErpNavLinks = function_exists('control_rateb_erp_nav_links') ? control_rateb_erp_nav_links() : [];
            foreach ($ratebErpNavLinks as $erpLink) {
                $active = (function_exists('control_rateb_erp_active_key') && control_rateb_erp_active_key() === ($erpLink['key'] ?? '')) ? ' active' : '';
                $erpKey = (string) ($erpLink['key'] ?? '');
                $erpLabel = $cpT('erp.' . $erpKey);
                if ($erpLabel === 'erp.' . $erpKey) {
                    $erpLabel = (string) ($erpLink['label'] ?? $erpKey);
                }
                echo '<li><a href="' . htmlspecialchars($erpLink['href'], ENT_QUOTES, 'UTF-8') . '" class="sidebar-subitem' . $active . '" data-permission="control_dashboard"><i class="fas ' . htmlspecialchars($erpLink['icon'], ENT_QUOTES, 'UTF-8') . '"></i><span>' . htmlspecialchars($erpLabel, ENT_QUOTES, 'UTF-8') . '</span></a></li>';
            }
            control_sidebar_group_close();
            control_sidebar_group_open('contact-center', $cpT('section.contact_center'), 'fa-headset');
            ?>
            <li><a href="<?php echo htmlspecialchars(control_contact_center_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF'] ?? '') === 'contact-center.php') ? 'active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-phone-volume"></i><span><?php echo htmlspecialchars($cpT('nav.contact_center'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_contact_center_app_url('agent-desktop'), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF'] ?? '') === 'contact-center-app.php') ? 'active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-headset"></i><span><?php echo htmlspecialchars($cpT('nav.contact_center_desktop'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_contact_center_ops_page_url('health'), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF'] ?? '') === 'contact-center-ops.php') ? 'active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-screwdriver-wrench"></i><span><?php echo htmlspecialchars($cpT('nav.contact_center_ops'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_contact_center_supervisor_page_url('dashboard'), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF'] ?? '') === 'contact-center-supervisor.php') ? 'active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-chart-line"></i><span><?php echo htmlspecialchars($cpT('nav.contact_center_supervisor'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_contact_center_migrate_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF'] ?? '') === 'contact-center-migrate.php') ? 'active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-database"></i><span><?php echo htmlspecialchars($cpT('nav.contact_center_db_setup'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php
            control_sidebar_group_close();
            $ratebPublicProfileUrl = '';
            if (function_exists('control_rateb_pro_public_base_url')) {
                $ratebPublicProfileUrl = rtrim((string) control_rateb_pro_public_base_url(), '/') . '/profile/';
            }
            control_sidebar_group_open('public-site', $cpT('section.public_site'), 'fa-globe');
            ?>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/site-content.php'), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'site-content.php') ? 'active' : ''; ?>" data-permission="control_system_settings,view_control_system_settings,edit_control_system_settings" title="Marketing home, company profile, architecture, security, procurement"><i class="fas fa-globe"></i><span><?php echo htmlspecialchars($cpT('nav.public_site_content'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php if ($ratebPublicProfileUrl !== '') { ?>
            <li><a href="<?php echo htmlspecialchars($ratebPublicProfileUrl, ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem" target="_blank" rel="noopener noreferrer" data-permission="control_system_settings,view_control_system_settings"><i class="fas fa-building"></i><span><?php echo htmlspecialchars($cpT('nav.company_profile_live'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php }
            control_sidebar_group_close();
            control_sidebar_group_open('client-platform', $cpT('section.client_platform'), 'fa-chart-pie');
            ?>
            <li><a href="<?php echo htmlspecialchars($clientPlatformLinks['hub']['href'], ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem<?php echo ($clientPlatformActiveKey === 'hub') ? ' active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-chart-pie"></i><span><?php echo htmlspecialchars($cpT('nav.client_hub'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars($clientPlatformLinks['services']['href'], ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem<?php echo ($clientPlatformActiveKey === 'services') ? ' active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-server"></i><span><?php echo htmlspecialchars($cpT('nav.services'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars($clientPlatformLinks['domains']['href'], ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem<?php echo ($clientPlatformActiveKey === 'domains') ? ' active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-globe"></i><span><?php echo htmlspecialchars($cpT('nav.domains'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars($clientPlatformLinks['orders']['href'], ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem<?php echo ($clientPlatformActiveKey === 'orders') ? ' active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-bag-shopping"></i><span><?php echo htmlspecialchars($cpT('nav.orders'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars($clientPlatformLinks['billing']['href'], ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem<?php echo ($clientPlatformActiveKey === 'billing') ? ' active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-file-invoice-dollar"></i><span><?php echo htmlspecialchars($cpT('nav.billing'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php
            control_sidebar_group_close();
            control_sidebar_group_open('core-management', $cpT('section.core_management'), 'fa-sitemap');
            ?>
            <?php
            $selectCountryPerms = 'control_select_country';
            if (isset($ctrl) && $ctrl) {
                try {
                    $chk = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
                    if ($chk && $chk->num_rows > 0) {
                        $r = $ctrl->query("SELECT slug FROM control_countries WHERE is_active = 1");
                        if ($r) { while ($row = $r->fetch_assoc()) { $selectCountryPerms .= ',country_' . $row['slug']; } $r->close(); }
                    }
                } catch (Throwable $e) { /* ignore */ }
            }
            ?>
            <li><a href="<?php echo pageUrl('select-country.php'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'select-country.php') ? 'active' : ''; ?>" data-permission="<?php echo htmlspecialchars($selectCountryPerms); ?>"><i class="fas fa-globe"></i><span><?php echo htmlspecialchars($cpT('nav.select_country'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo pageUrl('control/countries.php'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'countries.php') ? 'active' : ''; ?>" data-permission="control_countries,view_control_countries"><i class="fas fa-list"></i><span><?php echo htmlspecialchars($cpT('nav.manage_countries'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo pageUrl('control/agencies.php'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'agencies.php') ? 'active' : ''; ?>" data-permission="control_agencies,view_control_agencies"><i class="fas fa-building"></i><span><?php echo htmlspecialchars($cpT('nav.manage_agencies'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php
            $canViewCountryUsers = (strtolower(trim($_SESSION['control_username'] ?? '')) === 'admin')
                || hasControlPermission(CONTROL_PERM_COUNTRY_USERS)
                || hasControlPermission('view_control_country_users')
                || hasControlPermission('control_agencies')
                || hasControlPermission('view_control_agencies')
                || hasControlPermission('open_control_agency');
            if ($canViewCountryUsers): ?>
            <li><a href="<?php echo pageUrl('control/country-users.php'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'country-users.php') ? 'active' : ''; ?>" data-permission="control_country_users,view_control_country_users,control_agencies,view_control_agencies,open_control_agency"><i class="fas fa-globe-americas"></i><span><?php echo htmlspecialchars($cpT('nav.country_users'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php endif;
            control_sidebar_group_close();
            control_sidebar_group_open('registration-support', $cpT('section.registration_support'), 'fa-user-plus');
            ?>
            <li>
                <a href="<?php echo htmlspecialchars(function_exists('control_panel_page_with_control') ? (control_panel_page_with_control('control/registration-requests.php') . '&all_dates=1') : (pageUrl('control/registration-requests.php') . '?control=1&all_dates=1')); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'registration-requests.php') ? 'active' : ''; ?>" data-permission="control_registration_requests,view_control_registration,view_all_control_registration">
                    <i class="fas fa-user-plus"></i><span><?php echo htmlspecialchars($cpT('nav.registration_requests'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php
                    $pendingCount = 0;
                    if (isset($ctrl) && $ctrl && function_exists('getRegistrationRequestScopeCountryIds')) {
                        try {
                            $chk = $ctrl->query("SHOW TABLES LIKE 'control_registration_requests'");
                            if ($chk && $chk->num_rows > 0) {
                                $scopeIds = getRegistrationRequestScopeCountryIds($ctrl);
                                $canViewAllReg = ($scopeIds === null);
                                $reqWhere = '';
                                if ($scopeIds === []) {
                                    if (!$canViewAllReg) {
                                        $reqWhere = ' AND 1=0';
                                    }
                                } elseif (!$canViewAllReg && $scopeIds !== null && !empty($scopeIds)) {
                                    $idsStr = implode(',', array_map('intval', $scopeIds));
                                    $namesRes = @$ctrl->query("SELECT name FROM control_countries WHERE id IN ($idsStr) AND is_active = 1");
                                    $countryNames = [];
                                    if ($namesRes) {
                                        while ($nr = $namesRes->fetch_assoc()) {
                                            $countryNames[] = "'" . $ctrl->real_escape_string((string)$nr['name']) . "'";
                                        }
                                    }
                                    $nameMatch = !empty($countryNames)
                                        ? " OR (COALESCE(country_id, 0) = 0 AND country_name IN (" . implode(',', $countryNames) . "))"
                                        : '';
                                    $reqWhere = " AND (country_id IN ($idsStr)$nameMatch)";
                                }
                                $pendingSafety = '';
                                $psCol = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'");
                                $hasPaymentStatusCol = ($psCol && $psCol->num_rows > 0);
                                if ($hasPaymentStatusCol) {
                                    $pendingSafety = " AND (LOWER(TRIM(COALESCE(payment_status,''))) = 'paid' OR LOWER(TRIM(COALESCE(plan,''))) = 'pro')";
                                }
                                $res = $ctrl->query("SELECT COUNT(*) as c FROM control_registration_requests WHERE status = 'pending'" . $reqWhere . $pendingSafety);
                                if ($res) $pendingCount = (int)($res->fetch_assoc()['c'] ?? 0);
                            }
                        } catch (Throwable $e) { /* ignore */ }
                    }
                    if ($pendingCount > 0): ?><span class="badge-count"><?php echo $pendingCount; ?></span><?php endif; ?>
                </a>
            </li>
            <?php
            $supportUnreadCount = 0;
            if (isset($ctrl) && $ctrl && function_exists('hasControlPermission')
                && (hasControlPermission(CONTROL_PERM_SUPPORT) || hasControlPermission('view_control_support'))) {
                try {
                    $chkChats = $ctrl->query("SHOW TABLES LIKE 'control_support_chats'");
                    $chkMsgs = $ctrl->query("SHOW TABLES LIKE 'control_support_chat_messages'");
                    if ($chkChats && $chkChats->num_rows > 0 && $chkMsgs && $chkMsgs->num_rows > 0) {
                        $countryScope = '';
                        $hasCountryCol = $ctrl->query("SHOW COLUMNS FROM control_support_chats LIKE 'country_id'")->num_rows > 0;
                        $allowedIds = function_exists('getControlPanelCountryScopeIds') ? getControlPanelCountryScopeIds($ctrl) : null;
                        $controlUsername = strtolower(trim((string)($_SESSION['control_username'] ?? '')));
                        $isAdminUser = ($controlUsername === 'admin');
                        $sessionCountryId = isset($_SESSION['control_country_id']) ? (int)$_SESSION['control_country_id'] : 0;
                        if (!$isAdminUser) {
                            if ($sessionCountryId > 0) {
                                $allowedIds = [$sessionCountryId];
                            } elseif ($allowedIds === null) {
                                $allowedIds = [];
                            }
                        }
                        if ($hasCountryCol && $allowedIds !== null) {
                            if (empty($allowedIds)) {
                                $countryScope = ' AND 1=0';
                            } else {
                                $countryScope = ' AND c.country_id IN (' . implode(',', array_map('intval', $allowedIds)) . ')';
                            }
                        }
                        $uq = "SELECT COUNT(DISTINCT c.id) as c
                               FROM control_support_chats c
                               WHERE c.status = 'open'
                                 {$countryScope}
                                 AND (c.admin_read_at IS NULL OR EXISTS (
                                     SELECT 1 FROM control_support_chat_messages m
                                     WHERE m.chat_id = c.id
                                       AND m.sender = 'user'
                                       AND m.created_at > c.admin_read_at
                                 ))";
                        $uRes = $ctrl->query($uq);
                        if ($uRes) {
                            $supportUnreadCount = (int)($uRes->fetch_assoc()['c'] ?? 0);
                        }
                    }
                } catch (Throwable $e) { /* ignore */ }
            }
            ?>
            <li>
                <a href="<?php echo pageUrl('control/support-chats.php'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'support-chats.php') ? 'active' : ''; ?>" data-permission="control_support_chats,view_control_support">
                    <i class="fas fa-comments"></i>
                    <span><?php echo htmlspecialchars($cpT('nav.support_chats'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="badge-count <?php echo $supportUnreadCount > 0 ? '' : 'd-none'; ?>" id="sidebarSupportChatsBadge"><?php echo $supportUnreadCount > 99 ? '99+' : (int) $supportUnreadCount; ?></span>
                </a>
            </li>
            <?php
            $ctrlDb = (isset($ctrl) && $ctrl instanceof mysqli) ? $ctrl : null;
            $clientPricingPageUrl = control_panel_pricing_page_url($ctrlDb);
            ?>
            <li><a href="<?php echo htmlspecialchars($clientPricingPageUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="sidebar-subitem" title="Gold &amp; Platinum pricing on the live marketing site"><i class="fas fa-tags"></i><span><?php echo htmlspecialchars($cpT('nav.client_registration_page'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php
            control_sidebar_group_close();
            control_sidebar_group_open('business-modules', $cpT('section.business_modules'), 'fa-briefcase');
            ?>
            $countryProgramPerms = 'control_government,view_control_government,gov_admin,control_admins';
            if (isset($ctrl) && $ctrl) {
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
            ?>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/country-program.php')); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'country-program.php') ? 'active' : ''; ?>" data-permission="<?php echo htmlspecialchars($countryProgramPerms); ?>"><i class="fas fa-flag"></i><span><?php echo htmlspecialchars($cpT('nav.country_program'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo pageUrl('control/accounting.php'); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'accounting.php') ? 'active' : ''; ?>" data-permission="control_accounting,view_control_accounting"><i class="fas fa-calculator"></i><span><?php echo htmlspecialchars($cpT('nav.accounting'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/hr.php')); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'hr.php') ? 'active' : ''; ?>" data-permission="control_hr,view_control_hr"><i class="fas fa-user-tie"></i><span><?php echo htmlspecialchars($cpT('nav.hr_center'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/government.php')); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'government.php') ? 'active' : ''; ?>" data-permission="control_government,view_control_government,gov_admin"><i class="fas fa-shield-halved"></i><span><?php echo htmlspecialchars($cpT('nav.government_control'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-map.php')); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'tracking-map.php') ? 'active' : ''; ?>" data-permission="control_government,view_control_government,gov_admin"><i class="fas fa-map-location-dot"></i><span><?php echo htmlspecialchars($cpT('nav.tracking_map'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-onboarding.php')); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'tracking-onboarding.php') ? 'active' : ''; ?>" data-permission="control_government,manage_control_government,gov_admin"><i class="fas fa-qrcode"></i><span><?php echo htmlspecialchars($cpT('nav.tracking_onboarding'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-health.php')); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'tracking-health.php') ? 'active' : ''; ?>" data-permission="control_government,view_control_government,gov_admin"><i class="fas fa-heart-pulse"></i><span><?php echo htmlspecialchars($cpT('nav.telemetry_health'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/country-profiles.php')); ?>" class="sidebar-subitem <?php echo (basename($_SERVER['PHP_SELF']) === 'country-profiles.php') ? 'active' : ''; ?>" data-permission="control_system_settings,view_control_system_settings,edit_control_system_settings,manage_control_roles"><i class="fas fa-sliders"></i><span><?php echo htmlspecialchars($cpT('nav.country_profiles'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php
            control_sidebar_group_close();
            control_sidebar_group_open('administration', $cpT('section.administration'), 'fa-cogs');
            ?>
            <li><a href="<?php echo htmlspecialchars($controlCenterUrl . '#system-flags', ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem" target="_blank" rel="noopener noreferrer" data-permission="control_system_settings,view_control_system_settings,control_dashboard"><i class="fas fa-diagram-project"></i><span><?php echo htmlspecialchars($cpT('nav.rollout_control'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars($fullBaseUrl . '/pages/control/panel-settings.php?control=1'); ?>" id="nav-control-panel-settings" class="sidebar-subitem <?php echo in_array(basename($_SERVER['PHP_SELF']), ['panel-settings.php', 'admins.php', 'control-panel-settings.php', 'panel-users.php', 'control-panel-users.php']) ? 'active' : ''; ?>" data-permission="control_system_settings,view_control_system_settings"><i class="fas fa-sliders-h"></i><span><?php echo htmlspecialchars($cpT('nav.panel_settings'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars($controlCenterUrl, ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem" target="_blank" rel="noopener noreferrer" data-permission="control_system_settings,view_control_system_settings"><i class="fas fa-tools"></i><span><?php echo htmlspecialchars($cpT('nav.admin_control_center'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/infrastructure.php') . '&view=control', ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-subitem <?php echo basename($_SERVER['PHP_SELF']) === 'infrastructure.php' ? 'active' : ''; ?>" data-permission="control_system_settings,view_control_system_settings"><i class="fas fa-network-wired"></i><span><?php echo htmlspecialchars($cpT('nav.infrastructure'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php control_sidebar_group_close(); ?>
            <li><a href="<?php echo pageUrl('logout.php'); ?>?control=1" class="sidebar-item sidebar-item-logout"><i class="fas fa-sign-out-alt"></i><span><?php echo htmlspecialchars($cpT('nav.logout'), ENT_QUOTES, 'UTF-8'); ?></span></a></li>
        </ul>
    </nav>
</aside>
