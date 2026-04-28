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

// EN: Build scoped statistics according to allowed countries for current operator.
// AR: تجهيز الإحصاءات ضمن نطاق الدول المسموح بها للمستخدم الحالي.
// Get statistics - filter by user's allowed countries when they have country-specific access
$allowedCountryIds = getAllowedCountryIds($ctrl);
$stats = [
    'countries' => 0,
    'agencies' => 0,
    'pending_requests' => 0,
    'active_agencies' => 0,
    'total_employees' => 0,
    'total_revenue' => 0,
];

$countryWhere = '';
if ($allowedCountryIds === []) {
    $countryWhere = ' AND 1=0'; // No access - show zeros
} elseif ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
    $countryWhere = ' AND id IN (' . implode(',', array_map('intval', $allowedCountryIds)) . ')';
}
$agencyWhere = '';
if ($allowedCountryIds === []) {
    $agencyWhere = ' AND 1=0';
} elseif ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
    $agencyWhere = ' AND country_id IN (' . implode(',', array_map('intval', $allowedCountryIds)) . ')';
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
    
    // Pending registration requests (filtered by user's allowed countries)
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
        $res4 = $ctrl->query("SELECT COUNT(*) as c FROM control_registration_requests WHERE status = 'pending'" . $reqWhere);
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
        ratibEnsureGovernmentLaborSchema($govPdo);
        $govWidgetData = ratib_government_dashboard_summary_pdo($govPdo);
    } catch (Throwable $e) {
        $govWidgetData = null;
    }
}

// EN: Render dashboard page with cards + quick links and inject front-end config.
// AR: عرض صفحة اللوحة مع بطاقات الإحصاء والروابط السريعة وتمرير إعدادات الواجهة.
$pageTitle = 'Control Panel Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/control/system.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/control/dashboard.css'); ?>?v=<?php echo time(); ?>">
    <?php if ($govWidgetData !== null): ?>
    <link rel="stylesheet" href="<?php echo asset('css/control/government.css'); ?>?v=<?php echo time(); ?>">
    <?php endif; ?>
</head>
<body class="control-system-body">
    <?php $fullBase = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . preg_replace('#/pages/[^?]*.*$#', '', $_SERVER['REQUEST_URI'] ?? ''), '/'); ?>
    <?php $ratibBase = rtrim(defined('RATIB_PRO_URL') ? RATIB_PRO_URL : (defined('SITE_URL') ? SITE_URL : ''), '/'); if ($ratibBase === '' && isset($_SERVER['HTTP_HOST'])) { $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $ratibBase = $scheme . '://' . $_SERVER['HTTP_HOST']; } ?>
    <!-- EN: Server-to-client bootstrap for control dashboard scripts (API endpoints + base URLs). -->
    <!-- AR: تمرير إعدادات الخادم إلى سكربتات لوحة التحكم (مسارات API وروابط الأساس). -->
<div id="control-config" data-api-base="<?php echo htmlspecialchars($apiBase); ?>" data-agencies-url-base="<?php echo htmlspecialchars($agenciesUrlWithControl); ?>" data-country-users-url-base="<?php echo htmlspecialchars($countryUsersUrlWithControl); ?>" data-ratib-base="<?php echo htmlspecialchars($ratibBase); ?>"></div>
    <div id="app-config" data-base-url="<?php echo htmlspecialchars($fullBase, ENT_QUOTES, 'UTF-8'); ?>" data-api-base="<?php echo htmlspecialchars($fullBase . '/api', ENT_QUOTES, 'UTF-8'); ?>" data-control-api-path="<?php echo htmlspecialchars($fullBase . '/api/control', ENT_QUOTES, 'UTF-8'); ?>" data-control="1" class="hidden"></div>
    
    <!-- EN: Top header with support alerts, account identity, and logout action. -->
    <!-- AR: ترويسة علوية تضم تنبيهات الدعم وهوية المستخدم وخيار تسجيل الخروج. -->
    <!-- Header -->
    <header class="control-header">
        <div class="header-left">
            <h1><i class="fas fa-cog"></i> Control Panel</h1>
            <span class="header-subtitle header-subtitle-ratib">RATIB — Recruitment Automation &amp; Tracking Intelligence Base</span>
        </div>
        <div class="header-right">
            <div class="header-alerts" id="headerAlerts" data-permission="control_support_chats,view_control_support">
                <button type="button" class="header-alert-btn" id="supportAlertsBtn" aria-label="Support alerts" title="Support alerts">
                    <i class="fas fa-bell"></i>
                    <span class="badge-count header-alert-badge is-hidden" id="supportAlertsBadge">0</span>
                </button>
                <div class="header-alert-dropdown is-hidden" id="supportAlertsDropdown">
                    <div class="header-alert-title">Support Alerts</div>
                    <div class="header-alert-list" id="supportAlertsList">
                        <div class="header-alert-empty">No unread chats.</div>
                    </div>
                    <a href="<?php echo pageUrl('control/support-chats.php'); ?>?control=1" class="header-alert-footer">Open Support Chats</a>
                </div>
            </div>
            <a href="<?php echo htmlspecialchars(rtrim($ratibBase !== '' ? $ratibBase : $fullBase, '/') . '/coreai/index.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn-coreai" target="_blank" rel="noopener noreferrer" title="Open CoreAI">
                <i class="fas fa-robot"></i>
                <span>CoreAI</span>
            </a>
            <span class="user-info"><?php echo htmlspecialchars($_SESSION['control_username'] ?? ''); ?></span>
            <a href="<?php echo pageUrl('logout.php'); ?>?control=1" class="btn-logout">Logout</a>
        </div>
    </header>

    <div class="control-layout">
        <!-- Left Sidebar Navigation -->
        <?php include __DIR__ . '/../../includes/control/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="control-content">
            <div class="content-header">
                <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard Overview</h2>
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
                        <p>Active Countries</p>
                        <a href="<?php echo pageUrl('control/countries.php'); ?>?control=1" class="stat-link">View All <i class="fas fa-arrow-right"></i></a>
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
                        <p>Total Agencies</p>
                        <span class="stat-subtitle"><?php echo $stats['active_agencies']; ?> Active</span>
                        <a href="<?php echo pageUrl('control/agencies.php'); ?>?control=1" class="stat-link">Manage <i class="fas fa-arrow-right"></i></a>
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
                        <p>Pending Requests</p>
                        <a href="<?php echo pageUrl('control/registration-requests.php'); ?>?control=1" class="stat-link">Review <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (hasControlDashboardCardVisible('hide_dashboard_accounting_card')): ?>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-accounting">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Accounting</h3>
                        <p>Financial Management</p>
                        <a href="<?php echo pageUrl('control/accounting.php'); ?>?control=1" class="stat-link">Open <i class="fas fa-arrow-right"></i></a>
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
                <h3><i class="fas fa-shield-halved me-2"></i>Government labor monitoring</h3>
                <div class="gov-dash-row">
                    <span class="gov-dash-metric"><strong><?php echo (int) ($gt['violations'] ?? 0); ?></strong> violations</span>
                    <span class="gov-dash-metric"><strong><?php echo (int) ($gt['blacklist_active'] ?? 0); ?></strong> active blacklist</span>
                    <span class="gov-dash-metric"><strong><?php echo (int) ($gt['workers_alert'] ?? 0); ?></strong> workers in alert</span>
                    <span class="gov-dash-metric"><strong><?php echo (int) ($gt['inspections_failed_pending'] ?? 0); ?></strong> inspections (failed / pending)</span>
                </div>
                <?php if (!empty($gal)): ?>
                <p class="text-muted small mb-1">Active signals</p>
                <ul class="gov-dash-alerts">
                    <?php foreach (array_slice($gal, 0, 10) as $item): ?>
                    <li><?php echo htmlspecialchars(($item['title'] ?? '') . ': ' . ($item['detail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-muted small mb-0">No active government alerts in this database.</p>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/government.php'), ENT_QUOTES, 'UTF-8'); ?>" class="stat-link d-inline-block mt-2">Open Government Control <i class="fas fa-arrow-right"></i></a>
            </section>
            <?php endif; ?>

            <!-- EN: Dynamic users-per-country widget (loaded asynchronously by dashboard JS). -->
            <!-- AR: ويدجت المستخدمين لكل دولة (يُحمَّل ديناميكياً عبر JavaScript). -->
            <!-- Users per Country -->
            <div class="users-per-country-section">
                <div class="section-header">
                    <h3><i class="fas fa-users me-2"></i>Users per Country</h3>
                </div>
                <div class="users-per-country-grid" id="usersPerCountryGrid">
                    <div class="loading-state">
                        <i class="fas fa-spinner fa-spin"></i> Loading...
                    </div>
                </div>
            </div>

            <!-- EN: Shortcut actions for the most common control-panel operations. -->
            <!-- AR: إجراءات سريعة لأكثر العمليات استخداماً داخل لوحة التحكم. -->
            <!-- Quick Actions -->
            <?php if (hasControlDashboardCardVisible('hide_dashboard_quick_actions')): ?>
            <div class="quick-actions-section">
                <h3><i class="fas fa-bolt me-2"></i>Quick Actions</h3>
                <div class="quick-actions-grid">
                    <a href="<?php echo pageUrl('control/countries.php'); ?>?control=1" class="quick-action-card" data-permission="control_countries,add_control_country,view_control_countries">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Country</span>
                    </a>
                    <a href="<?php echo pageUrl('control/agencies.php'); ?>?control=1" class="quick-action-card" data-permission="control_agencies,add_control_agency,view_control_agencies">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Agency</span>
                    </a>
                    <a href="<?php echo pageUrl('control/registration-requests.php'); ?>?control=1" class="quick-action-card" data-permission="control_registration_requests,view_control_registration">
                        <i class="fas fa-check-circle"></i>
                        <span>Review Requests</span>
                    </a>
                    <a href="<?php echo pageUrl('control/accounting.php'); ?>?control=1" class="quick-action-card" data-permission="control_accounting,view_control_accounting">
                        <i class="fas fa-chart-line"></i>
                        <span>View Reports</span>
                    </a>
                    <a href="<?php echo pageUrl('control/soc-dashboard.php'); ?>?control=1" class="quick-action-card">
                        <i class="fas fa-shield-halved"></i>
                        <span>Open SOC Dashboard</span>
                    </a>
                    <a href="<?php echo htmlspecialchars(rtrim($ratibBase !== '' ? $ratibBase : $fullBase, '/') . '/mobile-app/index.php', ENT_QUOTES, 'UTF-8'); ?>" class="quick-action-card" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-location-crosshairs"></i>
                        <span>Open Tracker View</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- EN: Live preview list of newest registration requests for fast triage. -->
            <!-- AR: قائمة مباشرة لأحدث طلبات التسجيل لتسريع المتابعة. -->
            <!-- Recent Registration Requests -->
            <?php if (hasControlDashboardCardVisible('hide_dashboard_recent_requests')): ?>
            <div class="recent-section">
                <div class="section-header">
                    <h3><i class="fas fa-clock me-2"></i>Recent Registration Requests</h3>
                    <a href="<?php echo pageUrl('control/registration-requests.php'); ?>?control=1" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="recent-list" id="recent-requests">
                    <div class="loading-state">
                        <i class="fas fa-spinner fa-spin"></i> Loading recent requests...
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="<?php echo asset('js/permissions.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/app-config-init.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/system.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/header-support-alerts.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/dashboard.js'); ?>?v=<?php echo time(); ?>"></script>
</body>
</html>
