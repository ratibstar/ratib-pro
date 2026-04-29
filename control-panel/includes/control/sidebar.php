<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control/sidebar.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control/sidebar.php`.
 */
$logoUrl = (file_exists(__DIR__ . '/../../assets/logo.png')) ? asset('assets/logo.png') : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='44' height='44'%3E%3Crect width='44' height='44' rx='10' fill='%236b21a8'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.35em' fill='white' font-size='18' font-weight='bold'%3ER%3C/text%3E%3C/svg%3E";
$base = getBaseUrl();
$fullBaseUrl = rtrim(defined('SITE_URL') ? SITE_URL : '', '/') . $base;
$controlCenterUrl = rtrim(defined('SITE_URL') ? SITE_URL : '', '/') . '/admin/control-center.php';
?>
<aside class="control-sidebar" id="control-sidebar">
    <div class="sidebar-header">
        <a href="<?php echo pageUrl('control/dashboard.php'); ?>" class="sidebar-brand">
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Ratib" class="sidebar-logo">
        </a>
        <div class="sidebar-brand-title"><?php echo htmlspecialchars((string) ($_SESSION['control_username'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if (!empty($_SESSION['control_agency_name'])): ?>
        <div class="sidebar-context"><?php echo htmlspecialchars($_SESSION['control_agency_name'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php elseif (!empty($_SESSION['control_country_name'])): ?>
        <div class="sidebar-context"><?php echo htmlspecialchars($_SESSION['control_country_name'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
    </div>
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <li><a href="<?php echo pageUrl('control/dashboard.php'); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active' : ''; ?>" data-permission="control_dashboard"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li class="sidebar-section"><span class="section-label">Core Management</span></li>
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
            <li><a href="<?php echo pageUrl('select-country.php'); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'select-country.php') ? 'active' : ''; ?>" data-permission="<?php echo htmlspecialchars($selectCountryPerms); ?>"><i class="fas fa-globe"></i><span>Select Country</span></a></li>
            <li><a href="<?php echo pageUrl('control/countries.php'); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'countries.php') ? 'active' : ''; ?>" data-permission="control_countries,view_control_countries"><i class="fas fa-list"></i><span>Manage Countries</span></a></li>
            <li><a href="<?php echo pageUrl('control/agencies.php'); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'agencies.php') ? 'active' : ''; ?>" data-permission="control_agencies,view_control_agencies"><i class="fas fa-building"></i><span>Manage Agencies</span></a></li>
            <?php
            $canViewCountryUsers = (strtolower(trim($_SESSION['control_username'] ?? '')) === 'admin')
                || hasControlPermission(CONTROL_PERM_COUNTRY_USERS)
                || hasControlPermission('view_control_country_users')
                || hasControlPermission('control_agencies')
                || hasControlPermission('view_control_agencies')
                || hasControlPermission('open_control_agency');
            if ($canViewCountryUsers): ?>
            <li><a href="<?php echo pageUrl('control/country-users.php'); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'country-users.php') ? 'active' : ''; ?>" data-permission="control_country_users,view_control_country_users,control_agencies,view_control_agencies,open_control_agency"><i class="fas fa-globe-americas"></i><span>Country Users</span></a></li>
            <?php endif; ?>
            <li class="sidebar-section"><span class="section-label">Registration & Support</span></li>
            <li>
                <a href="<?php echo htmlspecialchars(function_exists('control_panel_page_with_control') ? (control_panel_page_with_control('control/registration-requests.php') . '&all_dates=1') : (pageUrl('control/registration-requests.php') . '?control=1&all_dates=1')); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'registration-requests.php') ? 'active' : ''; ?>" data-permission="control_registration_requests,view_control_registration,view_all_control_registration">
                    <i class="fas fa-user-plus"></i><span>Registration Requests</span>
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
                                // Keep sidebar pending badge aligned with queue safety:
                                // count only paid registrations (plus optional Pro inquiries), not unpaid paid-plans.
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
                        $allowedIds = function_exists('getAllowedCountryIds') ? getAllowedCountryIds($ctrl) : null;
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
                <a href="<?php echo pageUrl('control/support-chats.php'); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'support-chats.php') ? 'active' : ''; ?>" data-permission="control_support_chats,view_control_support">
                    <i class="fas fa-comments"></i>
                    <span>Support Chats</span>
                    <span class="badge-count <?php echo $supportUnreadCount > 0 ? '' : 'd-none'; ?>" id="sidebarSupportChatsBadge"><?php echo $supportUnreadCount > 99 ? '99+' : (int) $supportUnreadCount; ?></span>
                </a>
            </li>
            <?php $registrationPageUrl = (defined('RATIB_PRO_URL') ? rtrim(RATIB_PRO_URL, '/') : rtrim(defined('SITE_URL') ? SITE_URL : '', '/')) . '/pages/home.php?open=register&plan=gold&years=1'; ?>
            <li><a href="<?php echo htmlspecialchars($registrationPageUrl); ?>" target="_blank" rel="noopener noreferrer" class="sidebar-item"><i class="fas fa-file-signature"></i><span>Registration Page</span></a></li>
            <?php $designedAppUrl = defined('DESIGNED_APP_URL') ? trim((string) DESIGNED_APP_URL) : ''; ?>
            <?php if ($designedAppUrl !== ''): ?>
            <li><a href="<?php echo htmlspecialchars($designedAppUrl); ?>" target="_blank" rel="noopener noreferrer" class="sidebar-item" data-permission="control_designed_site,view_control_designed_site"><i class="fas fa-palette"></i><span>Designed site</span></a></li>
            <?php endif; ?>
            <li class="sidebar-section"><span class="section-label">Business Modules</span></li>
            <?php
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
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/country-program.php')); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'country-program.php') ? 'active' : ''; ?>" data-permission="<?php echo htmlspecialchars($countryProgramPerms); ?>"><i class="fas fa-flag"></i><span>Country program</span></a></li>
            <li><a href="<?php echo pageUrl('control/accounting.php'); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'accounting.php') ? 'active' : ''; ?>" data-permission="control_accounting,view_control_accounting"><i class="fas fa-calculator"></i><span>Accounting</span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/hr.php')); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'hr.php') ? 'active' : ''; ?>" data-permission="control_hr,view_control_hr"><i class="fas fa-user-tie"></i><span>HR Center</span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/government.php')); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'government.php') ? 'active' : ''; ?>" data-permission="control_government,view_control_government,gov_admin"><i class="fas fa-shield-halved"></i><span>Government Control</span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-map.php')); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'tracking-map.php') ? 'active' : ''; ?>" data-permission="control_government,view_control_government,gov_admin"><i class="fas fa-map-location-dot"></i><span>Tracking Map</span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-onboarding.php')); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'tracking-onboarding.php') ? 'active' : ''; ?>" data-permission="control_government,manage_control_government,gov_admin"><i class="fas fa-qrcode"></i><span>Tracking Onboarding</span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-health.php')); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'tracking-health.php') ? 'active' : ''; ?>" data-permission="control_government,view_control_government,gov_admin"><i class="fas fa-heart-pulse"></i><span>Tracking Health</span></a></li>
            <li><a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/country-profiles.php')); ?>" class="sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) === 'country-profiles.php') ? 'active' : ''; ?>" data-permission="control_system_settings,view_control_system_settings,edit_control_system_settings,manage_control_roles"><i class="fas fa-sliders"></i><span>Country Profiles</span></a></li>
            <li class="sidebar-section"><span class="section-label">Administration</span></li>
            <li><a href="<?php echo htmlspecialchars($fullBaseUrl . '/pages/control/panel-settings.php?control=1'); ?>" id="nav-control-panel-settings" class="sidebar-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['panel-settings.php', 'admins.php', 'control-panel-settings.php', 'panel-users.php', 'control-panel-users.php']) ? 'active' : ''; ?>" data-permission="control_system_settings,view_control_system_settings"><i class="fas fa-sliders-h"></i><span>Control Panel Settings</span></a></li>
            <li><a href="<?php echo htmlspecialchars($controlCenterUrl, ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-item" target="_blank" rel="noopener noreferrer" data-permission="control_system_settings,view_control_system_settings"><i class="fas fa-tools"></i><span>Admin Control Center</span></a></li>
            <li class="sidebar-section"><span class="section-label">Account</span></li>
            <li><a href="<?php echo pageUrl('logout.php'); ?>" class="sidebar-item sidebar-item-logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </nav>
</aside>
