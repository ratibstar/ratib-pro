<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/dashboard.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/dashboard.php`.
 */
/**
 * Control Panel - Main Dashboard
 * Unified dashboard for managing Countries, Agencies, HR, Accounting, and Registration Requests
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';

// EN: Guard access to dashboard (authentication + permission gate).
// AR: حماية الوصول للوحة التحكم (التحقق من تسجيل الدخول + فحص الصلاحية).
$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD);
require_once __DIR__ . '/../../includes/control/client-platform-nav.php';
require_once __DIR__ . '/../../../includes/tenant-rollout-flags.php';

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>خطأ في الاتصال</title>
        <link rel="stylesheet" href="<?php echo asset('css/control/dashboard.css'); ?>?v=<?php echo time(); ?>">
    </head>
    <body class="dashboard-error-body">
        <div class="dashboard-error-box">
            <h1 class="dashboard-error-title">خطأ في الاتصال بقاعدة البيانات</h1>
            <p class="dashboard-error-text">تعذر الاتصال بقاعدة بيانات لوحة التحكم. يرجى المحاولة لاحقاً أو التواصل مع المسؤول.</p>
            <a href="javascript:location.reload()" class="dashboard-error-reload">إعادة التحميل</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;
$apiBase = $baseUrl . '/api/control';
/** Same origin as dashboard (avoids pageUrl/BASE_URL mismatch when panel lives under a subpath). */
$controlPagesBase = rtrim($baseUrl, '/') . '/pages/control';
$agenciesUrlWithControl = $controlPagesBase . '/agencies.php?control=1';
$countryUsersUrlWithControl = $controlPagesBase . '/country-users.php?control=1';

// EN: Build scoped statistics to match Users-per-country API (`getControlPanelCountryScopeIds`).
// AR: إحصاءات بنفس نطاق دولة العمل كواجهة "المستخدمون حسب الدولة" حتى لا تتعارض الأرقام مع العرض.
// Get statistics - same workspace scope as get-users-per-country.php (session country pin + country_* limits)
$scopeCountryIds = function_exists('getControlPanelCountryScopeIds')
    ? getControlPanelCountryScopeIds($ctrl)
    : getAllowedCountryIds($ctrl);
$stats = [
    'countries' => 0,
    'agencies' => 0,
    'pending_requests' => 0,
    'active_agencies' => 0,
    'total_employees' => 0,
    'total_revenue' => 0,
];

$countryWhere = '';
if ($scopeCountryIds === []) {
    $countryWhere = ' AND 1=0'; // No access - show zeros
} elseif ($scopeCountryIds !== null && !empty($scopeCountryIds)) {
    $countryWhere = ' AND id IN (' . implode(',', array_map('intval', $scopeCountryIds)) . ')';
}
$agencyWhere = '';
if ($scopeCountryIds === []) {
    $agencyWhere = ' AND 1=0';
} elseif ($scopeCountryIds !== null && !empty($scopeCountryIds)) {
    $agencyWhere = ' AND country_id IN (' . implode(',', array_map('intval', $scopeCountryIds)) . ')';
}

try {
    // Countries count (filtered by user's allowed countries)
    $chk = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
    if ($chk && $chk->num_rows > 0) {
        $res = $ctrl->query("SELECT COUNT(*) as c FROM control_countries WHERE is_active = 1" . $countryWhere);
        if ($res) $stats['countries'] = (int)($res->fetch_assoc()['c'] ?? 0);
    }
    
    // Agencies count (filtered by user's allowed countries)
    $chk2 = $ctrl->query("SHOW TABLES LIKE 'control_agencies'");
    if ($chk2 && $chk2->num_rows > 0) {
        $cols = $ctrl->query("SHOW COLUMNS FROM control_agencies LIKE 'country_id'");
        $hasCountryId = $cols && $cols->num_rows > 0;
        $agWhere = $hasCountryId ? $agencyWhere : '';
        $res2 = $ctrl->query("SELECT COUNT(*) as c FROM control_agencies WHERE is_active = 1" . $agWhere);
        if ($res2) $stats['active_agencies'] = (int)($res2->fetch_assoc()['c'] ?? 0);
        
        $res3 = $ctrl->query("SELECT COUNT(*) as c FROM control_agencies WHERE 1=1" . $agWhere);
        if ($res3) $stats['agencies'] = (int)($res3->fetch_assoc()['c'] ?? 0);
    }
    
    // Pending registration requests (aligned with Registration Requests "Review" queue defaults)
    $chk3 = $ctrl->query("SHOW TABLES LIKE 'control_registration_requests'");
    if ($chk3 && $chk3->num_rows > 0) {
        $reqWhere = '';
        $scopeReg = function_exists('getRegistrationRequestScopeCountryIds') ? getRegistrationRequestScopeCountryIds($ctrl) : null;
        $dashRegViewAll = ($scopeReg === null);
        if ($scopeReg === []) {
            if (!$dashRegViewAll) {
                $reqWhere = ' AND 1=0';
            }
        } elseif (!$dashRegViewAll && $scopeReg !== null && !empty($scopeReg)) {
            $colCountry = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'country_id'");
            if ($colCountry && $colCountry->num_rows > 0) {
                $idsStr = implode(',', array_map('intval', $scopeReg));
                $namesRes = @$ctrl->query("SELECT name FROM control_countries WHERE id IN ($idsStr) AND is_active = 1");
                $countryNames = [];
                if ($namesRes) {
                    while ($r = $namesRes->fetch_assoc()) {
                        $countryNames[] = "'" . $ctrl->real_escape_string($r['name']) . "'";
                    }
                }
                $nameMatch = !empty($countryNames) ? " OR (COALESCE(country_id, 0) = 0 AND country_name IN (" . implode(',', $countryNames) . "))" : '';
                $reqWhere = ' AND (country_id IN (' . $idsStr . ')' . $nameMatch . ')';
            }
        }
        $pendingWhere = "LOWER(TRIM(COALESCE(status,''))) = 'pending'" . $reqWhere;
        // Keep dashboard card consistent with default Review list visibility (paid or Pro inquiry rows).
        $colPaymentStatus = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'");
        $colPlan = @$ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'plan'");
        $hasPaymentStatus = $colPaymentStatus && $colPaymentStatus->num_rows > 0;
        $hasPlan = $colPlan && $colPlan->num_rows > 0;
        if ($hasPaymentStatus) {
            if ($hasPlan) {
                $pendingWhere .= " AND (LOWER(TRIM(COALESCE(payment_status,''))) = 'paid' OR LOWER(TRIM(COALESCE(plan,''))) = 'pro')";
            } else {
                $pendingWhere .= " AND LOWER(TRIM(COALESCE(payment_status,''))) = 'paid'";
            }
        }
        $res4 = $ctrl->query("SELECT COUNT(*) as c FROM control_registration_requests WHERE " . $pendingWhere);
        if ($res4) $stats['pending_requests'] = (int)($res4->fetch_assoc()['c'] ?? 0);
    }
} catch (Throwable $e) {
    // Ignore errors
}

$govWidgetData = null;
if (function_exists('hasControlPermission') && (
    hasControlPermission(CONTROL_PERM_GOVERNMENT)
    || hasControlPermission('view_control_government')
    || hasControlPermission('gov_admin')
)) {
    try {
        require_once __DIR__ . '/../../../api/core/Database.php';
        require_once __DIR__ . '/../../../includes/government-labor.php';
        $govPdo = Database::getInstance()->getConnection();
        ratebEnsureGovernmentLaborSchema($govPdo);
        $govWidgetData = rateb_government_dashboard_summary_pdo($govPdo);
    } catch (Throwable $e) {
        $govWidgetData = null;
    }
}

// Phase 2 integration example: runtime resolver by tenant/country context.
$phase2ResolvedFlag = null;
$phase2NoticeEnabled = false;
$phase2AllAgenciesAuditResolved = null;
$phase2AllAgenciesAuditEnabled = true;
if ($ctrl instanceof mysqli && function_exists('trf_resolve_effective_flag')) {
    try {
        $sessionTenantId = isset($_SESSION['control_agency_id']) ? (int) $_SESSION['control_agency_id'] : 0;
        $sessionCountryId = isset($_SESSION['control_country_id']) ? (int) $_SESSION['control_country_id'] : 0;
        $phase2ResolvedFlag = trf_resolve_effective_flag($ctrl, 'control.dashboard.phase2_notice', $sessionTenantId, $sessionCountryId);
        $phase2NoticeEnabled = !empty($phase2ResolvedFlag['enabled']);
        $phase2AllAgenciesAuditResolved = trf_resolve_effective_flag($ctrl, 'control.dashboard.enable_all_agencies_audit', $sessionTenantId, $sessionCountryId);
        $phase2AllAgenciesAuditEnabled = !isset($phase2AllAgenciesAuditResolved['enabled']) || !empty($phase2AllAgenciesAuditResolved['enabled']);
    } catch (Throwable $e) {
        $phase2ResolvedFlag = null;
        $phase2NoticeEnabled = false;
        $phase2AllAgenciesAuditResolved = null;
        $phase2AllAgenciesAuditEnabled = true;
    }
}

// EN: Render dashboard page with cards + quick links and inject front-end config.
// AR: عرض صفحة اللوحة مع بطاقات الإحصاء والروابط السريعة وتمرير إعدادات الواجهة.
$pageTitle = 'Dashboard';
$controlPopupError = '';
if (!empty($_SESSION['control_popup_error'])) {
    $controlPopupError = (string) $_SESSION['control_popup_error'];
    unset($_SESSION['control_popup_error']);
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(cp_html_lang(), ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars(cp_html_dir(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(cp_t('dashboard.title'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/control/system.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/control/dashboard.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/control/rtl.css'); ?>?v=<?php echo time(); ?>">
    <?php if (cp_html_dir() === 'rtl'): ?>
    <style id="cp-rtl-layout-fix">
    .control-layout{display:flex!important;flex-direction:row!important;direction:ltr!important}
    .control-layout.cp-layout-rtl>.control-content{order:1!important;flex:1 1 auto!important;min-width:0!important;direction:rtl;padding:2rem 4px 2rem 2rem!important}
    .control-layout.cp-layout-rtl>.control-sidebar{order:2!important;flex:0 0 280px!important;width:280px!important;min-width:280px!important;direction:rtl;text-align:right}
    .control-content .stat-card{direction:ltr!important;flex-direction:row!important;align-items:flex-start!important;gap:1.25rem!important;padding:1.5rem!important}
    .control-content .stat-card .stat-icon{order:2!important}
    .control-content .stat-card .stat-content{order:1!important;direction:rtl!important;text-align:right!important;flex:1!important;min-width:0!important}
    .control-content .stat-content h3{font-size:1.75rem!important;font-weight:800!important;font-family:inherit!important;line-height:1.2!important}
    .control-content .stat-content h3.stat-title-label{font-size:1.25rem!important}
    .control-content .stat-link{flex-direction:row-reverse!important}
    .control-sidebar .sidebar-item{border-left:none!important;border-right:3px solid transparent!important}
    .control-sidebar .sidebar-item.active{border-left:none!important;border-right:3px solid var(--control-accent)!important;box-shadow:inset -4px 0 0 var(--control-accent)!important;background:linear-gradient(270deg,rgba(102,126,234,.2),rgba(118,75,162,.2))!important}
    .control-sidebar .sidebar-item:hover{border-left-color:transparent!important;border-right-color:var(--control-accent)!important}
    .control-sidebar .sidebar-item.active::after{right:auto!important;left:10px!important}
    .content-header{flex-direction:row-reverse!important}
    .control-header{padding:1.5rem 2rem!important;flex-direction:row-reverse!important}
    .control-header .header-left h1{font-size:1.75rem!important;flex-direction:row-reverse!important}
    </style>
    <?php endif; ?>
    <?php if ($govWidgetData !== null): ?>
    <link rel="stylesheet" href="<?php echo asset('css/control/government.css'); ?>?v=<?php echo time(); ?>">
    <?php endif; ?>
</head>
<body class="control-system-body<?php echo cp_html_dir() === 'rtl' ? ' cp-rtl' : ''; ?>">
    <?php if ($controlPopupError !== ''): ?>
    <style>
        @keyframes controlPopupShake {
            0%, 100% { transform: translateX(-50%); }
            15% { transform: translateX(calc(-50% - 6px)); }
            30% { transform: translateX(calc(-50% + 6px)); }
            45% { transform: translateX(calc(-50% - 5px)); }
            60% { transform: translateX(calc(-50% + 5px)); }
            75% { transform: translateX(calc(-50% - 3px)); }
        }
    </style>
    <div id="controlPopupErrorToast" style="position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:99999;min-width:380px;max-width:92vw;padding:16px 22px;border-radius:12px;background:#b91c1c;color:#fff;box-shadow:0 12px 28px rgba(127,29,29,.45);font-size:18px;font-weight:700;line-height:1.35;text-align:center;border:2px solid #fecaca;letter-spacing:.2px;animation:controlPopupShake .45s ease-in-out 0s 2;">
        <i class="fas fa-triangle-exclamation" style="margin-right:8px;"></i>
        <?php echo htmlspecialchars($controlPopupError, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>
    <?php $fullBase = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . preg_replace('#/pages/[^?]*.*$#', '', $_SERVER['REQUEST_URI'] ?? ''), '/'); ?>
    <?php $ratebBase = rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/'); if ($ratebBase === '' && isset($_SERVER['HTTP_HOST'])) { $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $ratebBase = $scheme . '://' . $_SERVER['HTTP_HOST']; } ?>
    <?php $clientPlatformLinks = control_client_platform_links(); ?>
    <!-- EN: Server-to-client bootstrap for control dashboard scripts (API endpoints + base URLs). -->
    <!-- AR: تمرير إعدادات الخادم إلى سكربتات لوحة التحكم (مسارات API وروابط الأساس). -->
<div id="control-config" data-api-base="<?php echo htmlspecialchars($apiBase); ?>" data-agencies-url-base="<?php echo htmlspecialchars($agenciesUrlWithControl); ?>" data-country-users-url-base="<?php echo htmlspecialchars($countryUsersUrlWithControl); ?>" data-rateb-base="<?php echo htmlspecialchars($ratebBase); ?>" data-tenant-self-test-url="<?php echo htmlspecialchars(rtrim($fullBase, '/') . '/api/diagnostics/tenant-isolation-self-test.php'); ?>" data-tenant-all-self-test-interval-ms="300000"></div>
    <div id="app-config" data-base-url="<?php echo htmlspecialchars($fullBase, ENT_QUOTES, 'UTF-8'); ?>" data-api-base="<?php echo htmlspecialchars($fullBase . '/api', ENT_QUOTES, 'UTF-8'); ?>" data-control-api-path="<?php echo htmlspecialchars($fullBase . '/api/control', ENT_QUOTES, 'UTF-8'); ?>" data-control="1" class="hidden"></div>
    
    <!-- EN: Top header with support alerts, account identity, and logout action. -->
    <!-- AR: ترويسة علوية تضم تنبيهات الدعم وهوية المستخدم وخيار تسجيل الخروج. -->
    <!-- Header -->
    <header class="control-header">
        <div class="header-left">
            <h1><i class="fas fa-cog"></i> <?php echo htmlspecialchars(cp_t('dashboard.title'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <span class="header-subtitle header-subtitle-rateb"><?php echo htmlspecialchars(cp_t('meta.brand_suffix'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="header-right">
            <?php require __DIR__ . '/../../includes/control/lang-switcher.php'; ?>
            <div class="header-alerts" id="headerAlerts" data-permission="control_support_chats,view_control_support">
                <button type="button" class="header-alert-btn" id="supportAlertsBtn" aria-label="<?php echo htmlspecialchars(cp_t('layout.support_alerts'), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars(cp_t('layout.support_alerts'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-bell"></i>
                    <span class="badge-count header-alert-badge is-hidden" id="supportAlertsBadge">0</span>
                </button>
                <div class="header-alert-dropdown is-hidden" id="supportAlertsDropdown">
                    <div class="header-alert-title"><?php echo htmlspecialchars(cp_t('layout.support_alerts_title'), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="header-alert-list" id="supportAlertsList">
                        <div class="header-alert-empty"><?php echo htmlspecialchars(cp_t('layout.no_unread_chats'), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <a href="<?php echo pageUrl('control/support-chats.php'); ?>?control=1" class="header-alert-footer"><?php echo htmlspecialchars(cp_t('layout.open_support_chats'), ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
            </div>
            <a href="<?php echo htmlspecialchars(rtrim($ratebBase !== '' ? $ratebBase : $fullBase, '/') . '/coreai/index.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn-coreai" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars(cp_t('layout.open_coreai'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-robot"></i>
                <span><?php echo htmlspecialchars(cp_t('layout.coreai'), ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <span class="user-info"><?php echo htmlspecialchars($_SESSION['control_username'] ?? ''); ?></span>
            <a href="<?php echo pageUrl('logout.php'); ?>?control=1" class="btn-logout"><?php echo htmlspecialchars(cp_t('nav.logout'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </header>

    <div class="control-layout<?php echo cp_is_rtl() ? ' cp-layout-rtl' : ''; ?>">
        <?php if (control_sidebar_before_main()) { control_render_sidebar(); } ?>

        <!-- Main Content Area -->
        <main class="control-content">
            <div class="content-header">
                <button class="sidebar-toggle" id="sidebar-toggle" aria-label="<?php echo htmlspecialchars(cp_t('layout.toggle_sidebar'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-bars"></i>
                </button>
                <h2><i class="fas fa-tachometer-alt me-2"></i><?php echo htmlspecialchars(cp_t('dashboard.overview'), ENT_QUOTES, 'UTF-8'); ?></h2>
            </div>
            <?php if ($phase2NoticeEnabled && is_array($phase2ResolvedFlag)): ?>
            <div class="alert alert-info py-2 px-3 mb-3" role="status">
                <i class="fas fa-flag-checkered me-2"></i>
                Phase 2 flag resolver is active for this context.
                <span class="small ms-2">Source: <?php echo htmlspecialchars((string) ($phase2ResolvedFlag['source'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>
            <div id="tenantIsolationGlobalAlert" class="tenant-isolation-global-alert is-hidden" role="alert">
                <i class="fas fa-triangle-exclamation me-2"></i>
                <span id="tenantIsolationGlobalAlertText"><?php echo htmlspecialchars(cp_t('dashboard.tenant_isolation_issue'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <!-- EN: KPI cards controlled by role-based visibility switches. -->
            <!-- AR: بطاقات المؤشرات الرئيسية وتخضع لإعدادات إظهار حسب الصلاحيات. -->
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <?php if (hasControlDashboardCardVisible('hide_dashboard_countries_card')): ?>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-countries">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['countries']; ?></h3>
                        <p><?php echo htmlspecialchars(cp_t('dashboard.active_countries'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <a href="<?php echo pageUrl('control/countries.php'); ?>?control=1" class="stat-link"><?php echo htmlspecialchars(cp_t('common.view_all'), ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (hasControlDashboardCardVisible('hide_dashboard_agencies_card')): ?>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-agencies">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['agencies']; ?></h3>
                        <p><?php echo htmlspecialchars(cp_t('dashboard.total_agencies'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <span class="stat-subtitle"><?php echo $stats['active_agencies']; ?> <?php echo htmlspecialchars(cp_t('common.active'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <a href="<?php echo pageUrl('control/agencies.php'); ?>?control=1" class="stat-link"><?php echo htmlspecialchars(cp_t('common.manage'), ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (hasControlDashboardCardVisible('hide_dashboard_pending_requests_card')): ?>
                <div class="stat-card warning">
                    <div class="stat-icon stat-icon-pending">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['pending_requests']; ?></h3>
                        <p><?php echo htmlspecialchars(cp_t('dashboard.pending_requests'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <a href="<?php echo pageUrl('control/registration-requests.php'); ?>?control=1" class="stat-link"><?php echo htmlspecialchars(cp_t('common.review'), ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (hasControlDashboardCardVisible('hide_dashboard_accounting_card')): ?>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-accounting">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-title-label"><?php echo htmlspecialchars(cp_t('nav.accounting'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars(cp_t('dashboard.financial_management'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <a href="<?php echo pageUrl('control/accounting.php'); ?>?control=1" class="stat-link"><?php echo htmlspecialchars(cp_t('common.open'), ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($govWidgetData !== null && is_array($govWidgetData)): ?>
            <?php
            $gt = $govWidgetData['totals'] ?? [];
            $gal = $govWidgetData['alerts'] ?? [];
            ?>
            <section class="gov-dashboard-widget" id="govLaborDashboard" aria-label="Government labor alerts">
                <h3><i class="fas fa-shield-halved me-2"></i><?php echo htmlspecialchars(cp_t('dashboard.gov_monitoring'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="gov-dash-row">
                    <span class="gov-dash-metric"><strong><?php echo (int) ($gt['violations'] ?? 0); ?></strong> <?php echo htmlspecialchars(cp_t('dashboard.violations'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="gov-dash-metric"><strong><?php echo (int) ($gt['blacklist_active'] ?? 0); ?></strong> <?php echo htmlspecialchars(cp_t('dashboard.active_blacklist'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="gov-dash-metric"><strong><?php echo (int) ($gt['workers_alert'] ?? 0); ?></strong> <?php echo htmlspecialchars(cp_t('dashboard.workers_alert'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="gov-dash-metric"><strong><?php echo (int) ($gt['inspections_failed_pending'] ?? 0); ?></strong> <?php echo htmlspecialchars(cp_t('dashboard.inspections_failed'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php if (!empty($gal)): ?>
                <p class="text-muted small mb-1"><?php echo htmlspecialchars(cp_t('dashboard.active_signals'), ENT_QUOTES, 'UTF-8'); ?></p>
                <ul class="gov-dash-alerts">
                    <?php foreach (array_slice($gal, 0, 10) as $item): ?>
                    <li><?php echo htmlspecialchars(($item['title'] ?? '') . ': ' . ($item['detail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-muted small mb-0"><?php echo htmlspecialchars(cp_t('dashboard.no_gov_alerts'), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/government.php'), ENT_QUOTES, 'UTF-8'); ?>" class="stat-link d-inline-block mt-2"><?php echo htmlspecialchars(cp_t('dashboard.open_government'), ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right"></i></a>
            </section>
            <?php endif; ?>

            <!-- EN: Dynamic users-per-country widget (loaded asynchronously by dashboard JS). -->
            <!-- AR: ويدجت المستخدمين لكل دولة (يُحمَّل ديناميكياً عبر JavaScript). -->
            <!-- Users per Country -->
            <div class="users-per-country-section">
                <div class="section-header">
                    <h3><i class="fas fa-users me-2"></i><?php echo htmlspecialchars(cp_t('dashboard.users_per_country'), ENT_QUOTES, 'UTF-8'); ?></h3>
                </div>
                <div class="users-per-country-grid" id="usersPerCountryGrid">
                    <div class="loading-state">
                        <i class="fas fa-spinner fa-spin"></i> <?php echo htmlspecialchars(cp_t('common.loading'), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </div>

            <!-- EN: Shortcut actions for the most common control-panel operations. -->
            <!-- AR: إجراءات سريعة لأكثر العمليات استخداماً داخل لوحة التحكم. -->
            <!-- Quick Actions -->
            <?php if (hasControlDashboardCardVisible('hide_dashboard_quick_actions')): ?>
            <div class="quick-actions-section">
                <h3><i class="fas fa-bolt me-2"></i><?php echo htmlspecialchars(cp_t('dashboard.quick_actions'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="quick-actions-grid">
                    <a href="<?php echo pageUrl('control/countries.php'); ?>?control=1" class="quick-action-card" data-permission="control_countries,add_control_country,view_control_countries">
                        <i class="fas fa-plus-circle"></i>
                        <span><?php echo htmlspecialchars(cp_t('dashboard.add_country'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <a href="<?php echo pageUrl('control/agencies.php'); ?>?control=1" class="quick-action-card" data-permission="control_agencies,add_control_agency,view_control_agencies">
                        <i class="fas fa-plus-circle"></i>
                        <span><?php echo htmlspecialchars(cp_t('dashboard.add_agency'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <a href="<?php echo pageUrl('control/registration-requests.php'); ?>?control=1" class="quick-action-card" data-permission="control_registration_requests,view_control_registration">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars(cp_t('dashboard.review_requests'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <a href="<?php echo pageUrl('control/accounting.php'); ?>?control=1" class="quick-action-card" data-permission="control_accounting,view_control_accounting">
                        <i class="fas fa-chart-line"></i>
                        <span><?php echo htmlspecialchars(cp_t('dashboard.view_reports'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <a href="<?php echo htmlspecialchars(rtrim($ratebBase !== '' ? $ratebBase : $fullBase, '/') . '/admin/control-center.php#system-flags', ENT_QUOTES, 'UTF-8'); ?>" class="quick-action-card" target="_blank" rel="noopener noreferrer" data-permission="control_system_settings,view_control_system_settings,control_dashboard">
                        <i class="fas fa-diagram-project"></i>
                        <span><?php echo htmlspecialchars(cp_t('dashboard.rollout_control'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <a href="<?php echo pageUrl('control/soc-dashboard.php'); ?>?control=1" class="quick-action-card">
                        <i class="fas fa-shield-halved"></i>
                        <span><?php echo htmlspecialchars(cp_t('dashboard.open_soc'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <a href="<?php echo htmlspecialchars(rtrim($ratebBase !== '' ? $ratebBase : $fullBase, '/') . '/mobile-app/index.php', ENT_QUOTES, 'UTF-8'); ?>" class="quick-action-card" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-location-crosshairs"></i>
                        <span><?php echo htmlspecialchars(cp_t('dashboard.open_tracker'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <a href="<?php echo htmlspecialchars($clientPlatformLinks['hub']['href'], ENT_QUOTES, 'UTF-8'); ?>" class="quick-action-card">
                        <i class="fas fa-chart-pie"></i>
                        <span><?php echo htmlspecialchars(cp_t('dashboard.open_client_hub'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <a href="<?php echo htmlspecialchars($clientPlatformLinks['services']['href'], ENT_QUOTES, 'UTF-8'); ?>" class="quick-action-card">
                        <i class="fas fa-server"></i>
                        <span><?php echo htmlspecialchars(cp_t('dashboard.open_services'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <div class="tenant-self-test-section">
                <div class="tenant-self-test-header">
                    <h3><i class="fas fa-shield-check me-2"></i><?php echo htmlspecialchars(cp_t('dashboard.tenant_self_test'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div class="tenant-self-test-actions">
                        <button type="button" id="runTenantSelfTestBtn" class="btn btn-sm btn-outline-info"><?php echo htmlspecialchars(cp_t('dashboard.run_current'), ENT_QUOTES, 'UTF-8'); ?></button>
                        <?php if ($phase2AllAgenciesAuditEnabled): ?>
                        <button type="button" id="runTenantAllSelfTestBtn" class="btn btn-sm btn-outline-warning"><?php echo htmlspecialchars(cp_t('dashboard.run_all_agencies'), ENT_QUOTES, 'UTF-8'); ?></button>
                        <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Disabled by feature flag control.dashboard.enable_all_agencies_audit">
                            <?php echo htmlspecialchars(cp_t('dashboard.run_all_disabled'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div id="tenantSelfTestResult" class="tenant-self-test-result tenant-self-test-idle">
                    <span class="tenant-self-test-badge"><?php echo htmlspecialchars(cp_t('js.idle'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="tenant-self-test-text"><?php echo htmlspecialchars(cp_t('dashboard.press_run_test'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div id="tenantAllSelfTestResult" class="tenant-all-self-test-result tenant-self-test-idle">
                    <span class="tenant-self-test-badge"><?php echo htmlspecialchars(cp_t('js.idle'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="tenant-self-test-text"><?php echo htmlspecialchars(cp_t('dashboard.run_all_hint'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>

            <!-- EN: Live preview list of newest registration requests for fast triage. -->
            <!-- AR: قائمة مباشرة لأحدث طلبات التسجيل لتسريع المتابعة. -->
            <!-- Recent Registration Requests -->
            <?php if (hasControlDashboardCardVisible('hide_dashboard_recent_requests')): ?>
            <div class="recent-section">
                <div class="section-header">
                    <h3><i class="fas fa-clock me-2"></i><?php echo htmlspecialchars(cp_t('dashboard.recent_requests'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <a href="<?php echo pageUrl('control/registration-requests.php'); ?>?control=1" class="view-all-link"><?php echo htmlspecialchars(cp_t('common.view_all'), ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="recent-list" id="recent-requests">
                    <div class="loading-state">
                        <i class="fas fa-spinner fa-spin"></i> <?php echo htmlspecialchars(cp_t('js.loading_recent'), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
        <?php if (!control_sidebar_before_main()) { control_render_sidebar(); } ?>
    </div>

    <script src="<?php echo asset('js/permissions.js'); ?>?v=<?php echo time(); ?>"></script>
    <?php echo cp_i18n_inline_script(); ?>
    <script src="<?php echo asset('js/control/i18n.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/app-config-init.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/system.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/header-support-alerts.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/dashboard.js'); ?>?v=<?php echo time(); ?>"></script>
    <?php if ($controlPopupError !== ''): ?>
    <script>
        (function () {
            var toast = document.getElementById('controlPopupErrorToast');
            if (!toast) return;
            setTimeout(function () {
                toast.style.transition = 'opacity 200ms ease';
                toast.style.opacity = '0';
                setTimeout(function () {
                    if (toast && toast.parentNode) toast.parentNode.removeChild(toast);
                }, 220);
            }, 1600);
        })();
    </script>
    <?php endif; ?>
</body>
</html>
