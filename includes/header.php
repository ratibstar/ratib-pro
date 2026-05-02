<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/header.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/header.php`.
 */
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/config.php';
}
$globalAiButtonPath = __DIR__ . '/../app/UI/GlobalAIButton.php';
if (PHP_VERSION_ID >= 70000 && is_file($globalAiButtonPath)) {
    require_once $globalAiButtonPath;
}
$companyProfileServicePath = __DIR__ . '/../app/Services/CompanyProfileService.php';
if (PHP_VERSION_ID >= 70100 && is_file($companyProfileServicePath)) {
    require_once $companyProfileServicePath;
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__FILE__)));
}

$fallbackCompanyName = defined('APP_NAME') ? (string) APP_NAME : '';
$companyName = $fallbackCompanyName;
if (class_exists('\App\Services\CompanyProfileService') && method_exists('\App\Services\CompanyProfileService', 'resolveCompanyName')) {
    $companyName = \App\Services\CompanyProfileService::resolveCompanyName(
        $GLOBALS['conn'] ?? null,
        $conn ?? null,
        $fallbackCompanyName
    );
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="password-manager" content="disabled">
    <meta name="google-password-manager" content="disabled">
    <title><?php echo (isset($pageTitle) ? $pageTitle : 'Default Title'); ?> | RATIB — Recruitment Automation &amp; Tracking Intelligence Base</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32'%3E%3Crect width='32' height='32' fill='%235a4a6a'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' fill='%23d4c4e4' font-size='18' font-weight='bold'%3ER%3C/text%3E%3C/svg%3E" type="image/svg+xml">
    <?php
    $navCssDisk = __DIR__ . '/../css/nav.css';
    $navCssV = file_exists($navCssDisk) ? filemtime($navCssDisk) : time();
    $navCssHref = asset('css/nav.css') . '?v=' . (int) $navCssV;
    ?>
    <!-- Preload must match stylesheet URL exactly or the browser warns the preload was unused -->
    <link rel="preload" href="<?php echo htmlspecialchars($navCssHref, ENT_QUOTES, 'UTF-8'); ?>" as="style">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" as="style">
    
    <!-- Load jQuery immediately (not deferred) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- Critical CSS first -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($navCssHref, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php $chatWidgetCssPath = __DIR__ . '/../css/chat-widget.css'; $chatWidgetCssV = is_file($chatWidgetCssPath) ? filemtime($chatWidgetCssPath) : time(); ?>
    <link rel="stylesheet" href="<?php echo asset('css/chat-widget.css'); ?>?v=<?php echo (int) $chatWidgetCssV; ?>">
    <?php $globalAiCssPath = __DIR__ . '/../css/global-ai-action.css'; $globalAiCssV = is_file($globalAiCssPath) ? filemtime($globalAiCssPath) : time(); ?>
    <link rel="stylesheet" href="<?php echo asset('css/global-ai-action.css'); ?>?v=<?php echo (int) $globalAiCssV; ?>">
    
    <!-- Page specific CSS -->
    <?php if (isset($pageCss)): ?>
        <?php if (is_array($pageCss)): ?>
            <?php foreach ($pageCss as $css): ?>
                <link rel="stylesheet" href="<?php echo $css; ?>">
            <?php endforeach; ?>
        <?php else: ?>
            <link rel="stylesheet" href="<?php echo $pageCss; ?>">
        <?php endif; ?>
    <?php endif; ?>
    <?php
    $appLayoutPcPath = __DIR__ . '/../css/app-layout-pc.css';
    $appLayoutPcV = is_file($appLayoutPcPath) ? filemtime($appLayoutPcPath) : time();
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset('css/app-layout-pc.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) $appLayoutPcV; ?>">
    
    <!-- Defer non-critical CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- JavaScript Configuration - Passed via data attributes -->
    <?php $ratibControlProBridge = ratib_control_pro_bridge(); ?>
    <div id="app-config" 
         data-base-path="<?php echo htmlspecialchars(getBaseUrl(), ENT_QUOTES, 'UTF-8'); ?>"
         data-base-url="<?php echo htmlspecialchars(getBaseUrl(), ENT_QUOTES, 'UTF-8'); ?>"
         data-api-base="<?php echo htmlspecialchars(getBaseUrl() . '/api', ENT_QUOTES, 'UTF-8'); ?>"
         data-site-url="<?php echo htmlspecialchars(SITE_URL, ENT_QUOTES, 'UTF-8'); ?>"
         data-company-name="<?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>"
         data-control-pro-bridge="<?php echo $ratibControlProBridge ? '1' : '0'; ?>"
         data-agency-id="<?php echo (int) ($_SESSION['agency_id'] ?? 0); ?>"
         data-country-id="<?php echo (int) ($_SESSION['country_id'] ?? 0); ?>"
         data-country-name="<?php echo htmlspecialchars((string) ($_SESSION['country_name'] ?? (defined('COUNTRY_NAME') ? COUNTRY_NAME : '')), ENT_QUOTES, 'UTF-8'); ?>"
         data-country-code="<?php echo htmlspecialchars((string) ($_SESSION['country_code'] ?? (defined('COUNTRY_CODE') ? COUNTRY_CODE : '')), ENT_QUOTES, 'UTF-8'); ?>"
         class="hidden"></div>
    <?php
    $headerConfigJsPath = __DIR__ . '/../js/utils/header-config.js';
    $headerConfigJsV = is_file($headerConfigJsPath) ? filemtime($headerConfigJsPath) : time();
    $globalAiJsPath = __DIR__ . '/../js/utils/global-ai-action.js';
    $globalAiJsV = is_file($globalAiJsPath) ? filemtime($globalAiJsPath) : time();
    ?>
    <script src="<?php echo asset('js/utils/header-config.js'); ?>?v=<?php echo (int)$headerConfigJsV; ?>"></script>
    <script src="<?php echo asset('js/utils/global-ai-action.js'); ?>?v=<?php echo (int)$globalAiJsV; ?>" defer></script>
    
    <!-- Navigation JavaScript - Load early for inline onclick handlers -->
    <script src="<?php echo asset('js/navigation.js'); ?>?v=<?php echo time(); ?>"></script>
    <!-- Overlay handlers for permission overlays -->
    <script src="<?php echo asset('js/overlay-handlers.js'); ?>?v=<?php echo time(); ?>"></script>
    <!-- Permissions enforcement (hides unauthorized UI elements globally) -->
    <script src="<?php echo asset('js/permissions.js'); ?>?v=<?php echo time(); ?>" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet" media="print" data-defer-css>
    
    <!-- Load page-specific JavaScript (not deferred to ensure jQuery is available) -->
    <?php if (isset($pageJs)): ?>
        <?php if (is_array($pageJs)): ?>
            <?php foreach ($pageJs as $js): ?>
                <?php if (is_array($js)): ?>
                    <script src="<?php echo $js['src']; ?>?v=<?php echo time(); ?>" type="<?php echo $js['type']; ?>"></script>
                <?php else: ?>
                    <script src="<?php echo $js; ?>" type="text/javascript"></script>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <script src="<?php echo $pageJs; ?>?v=<?php echo time(); ?>" type="text/javascript"></script>
        <?php endif; ?>
    <?php endif; ?>
    
</head>

<?php
$bodyClassList = ['ratib-app'];
if (basename($_SERVER['PHP_SELF']) == 'agent.php') {
    $bodyClassList[] = 'agent-page';
}
$bodyClassAttr = ' class="' . htmlspecialchars(implode(' ', $bodyClassList), ENT_QUOTES, 'UTF-8') . '"';
?>
<body<?php echo $bodyClassAttr; ?>> <!-- Add trigger area for mouse detection -->
    <div class="nav-trigger-area"></div>

    <!-- Mobile Navigation Toggle -->
    <button class="nav-toggle" id="mobileNavToggle" aria-label="Toggle Navigation Menu">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Mobile Navigation Overlay -->
    <div class="nav-overlay" id="mobileNavOverlay"></div>

    <nav class="main-nav" id="mainNav">
        <div class="nav-brand">
            <!-- Logo - replace with your actual logo file path -->
            <?php
            $logoPath = __DIR__ . '/../assets/logo.png';
            $logoUrl = (file_exists($logoPath)) ? asset('assets/logo.png') : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40'%3E%3Crect width='40' height='40' fill='%235a4a6a'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' fill='%23d4c4e4' font-size='14' font-weight='bold'%3ER%3C/text%3E%3C/svg%3E";
            ?>
            <img src="<?php echo $logoUrl; ?>" alt="Logo" id="mainLogo" class="nav-logo">
            <div class="nav-brand-text">
                <?php if (!empty($companyName)): ?>
                <span class="nav-company-name"><?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="nav-items">
            <a href="<?php echo htmlspecialchars(ratib_nav_url('dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link" data-permission="view_dashboard">
                <i class="nav-icon fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('agent.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link" data-permission="view_agents">
                <i class="nav-icon fas fa-users"></i>
                <span>Agent</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('subagent.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link" data-permission="view_subagents">
                <i class="nav-icon fas fa-user-friends"></i>
                <span>SubAgent</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('Worker.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link" data-permission="view_workers">
                <i class="nav-icon fas fa-tools"></i>
                <span>Workers</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('partner-agencies.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link" data-permission="view_partner_agencies,view_workers">
                <i class="nav-icon fas fa-globe"></i>
                <span>Partner Agencies</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('cases/cases-table.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link" data-permission="view_cases">
                <i class="nav-icon fas fa-clipboard-list"></i>
                <span>Cases</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('accounting.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link" data-permission="view_chart_accounts">
                <i class="nav-icon fas fa-dollar-sign"></i>
                <span>Accounting</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('hr.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link" data-permission="view_hr_dashboard">
                <i class="nav-icon fas fa-user-tie"></i>
                <span>HR</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('reports.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link" data-permission="view_reports">
                <i class="nav-icon fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('contact.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link" data-permission="view_contacts">
                <i class="nav-icon fas fa-phone"></i>
                <span>Contact</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('notifications.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link" data-permission="view_notifications">
                <i class="nav-icon fas fa-bell"></i>
                <span>Notifications</span>
                <span class="notification-badge badge bg-danger ms-1 d-none" id="headerNotificationBadge">0</span>
            </a>
            <a href="<?php echo htmlspecialchars(pageUrl('home.php') . '?open=register', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="nav-item nav-link nav-register-pro">
                <i class="nav-icon fas fa-external-link-alt"></i>
                <span>Register Pro</span>
            </a>
            <?php if (function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user() && isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1): ?>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('system-settings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link" data-permission="manage_settings">
                <i class="nav-icon fas fa-cog"></i>
                <span>System Settings</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('help-center.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link nav-help-center">
                <i class="nav-icon fas fa-question-circle"></i>
                <span>Help & Learning Center</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_logout_url(), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link">
                <i class="nav-icon fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <?php if (class_exists('\App\UI\GlobalAIButton') && method_exists('\App\UI\GlobalAIButton', 'render')): ?>
    <?php echo \App\UI\GlobalAIButton::render(getBaseUrl()); ?>
    <?php endif; ?>
    
    <!-- Notification badge loading is handled by header-config.js -->
    
    <div class="content-wrapper">