<?php
/**
 * Control panel wrapper for infrastructure module screens.
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
requireControlPermission(CONTROL_PERM_SYSTEM_SETTINGS, 'view_control_system_settings');

if (empty($_SESSION['infra_control_csrf_token']) || !is_string($_SESSION['infra_control_csrf_token'])) {
    try {
        $_SESSION['infra_control_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $_SESSION['infra_control_csrf_token'] = sha1((string) microtime(true) . (string) mt_rand());
    }
}

$siteRootUrl = rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/');
if ($siteRootUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $siteRootUrl = $host !== '' ? ($scheme . '://' . $host) : '';
}

$view = strtolower(trim((string) ($_GET['view'] ?? 'control')));
$allowed = [
    'control' => [
        'title' => cp_t('infra.control_title'),
        'url' => $siteRootUrl . '/modules/infrastructure-marketplace/Views/admin/control.php',
    ],
    'dashboard' => [
        'title' => cp_t('infra.dashboard_title'),
        'url' => $siteRootUrl . '/modules/infrastructure-marketplace/Views/admin/dashboard.php',
    ],
    'providers' => [
        'title' => cp_t('infra.providers_title'),
        'url' => $siteRootUrl . '/modules/infrastructure-marketplace/Views/admin/providers.php',
    ],
];
if (!isset($allowed[$view])) {
    $view = 'control';
}

$pageTitle = $allowed[$view]['title'];
$embedUrl = $allowed[$view]['url'] . '?embed=1&_rt=' . time();
$infraShellBase = control_panel_page_with_control('control/infrastructure.php');
$infraTabs = [
    'control' => ['label' => cp_t('infra.control'), 'icon' => 'fa-sliders-h'],
    'dashboard' => ['label' => cp_t('infra.dashboard'), 'icon' => 'fa-chart-line'],
    'providers' => ['label' => cp_t('infra.providers'), 'icon' => 'fa-plug'],
];
$controlDashboardUrl = pageUrl('control/dashboard.php');
$fullBase = rtrim(
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://'
        . ($_SERVER['HTTP_HOST'] ?? '')
        . preg_replace('#/pages/[^?]*.*$#', '', (string) ($_SERVER['REQUEST_URI'] ?? '')),
    '/'
);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(cp_html_lang(), ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars(cp_html_dir(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(cp_t('infra.title') . ' — ' . ($infraTabs[$view]['label'] ?? $pageTitle), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/control/system.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/control/rtl.css'); ?>?v=<?php echo time(); ?>">
    <?php if (cp_is_rtl()): ?>
    <style id="cp-rtl-layout-fix">
    .control-layout{display:flex!important;flex-direction:row!important;direction:ltr!important}
    .control-layout.cp-layout-rtl>.control-content{order:1!important;flex:1 1 auto!important;min-width:0!important;direction:rtl;padding:2rem 4px 2rem 2rem!important}
    .control-layout.cp-layout-rtl>.control-sidebar{order:2!important;flex:0 0 280px!important;width:280px!important;min-width:280px!important;direction:rtl;text-align:right}
    .sidebar-item.active{box-shadow:inset -4px 0 0 var(--control-accent)!important;border-right-color:var(--control-accent)!important;border-left-color:transparent!important}
    </style>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo asset('css/control/infrastructure-embed.css'); ?>?v=<?php echo time(); ?>">
</head>
<body class="control-system-body<?php echo cp_is_rtl() ? ' cp-rtl' : ''; ?>">
    <div id="app-config" data-base-url="<?php echo htmlspecialchars($fullBase, ENT_QUOTES, 'UTF-8'); ?>" data-api-base="<?php echo htmlspecialchars($fullBase . '/api', ENT_QUOTES, 'UTF-8'); ?>" data-control-api-path="<?php echo htmlspecialchars($fullBase . '/api/control', ENT_QUOTES, 'UTF-8'); ?>" data-control="1" class="hidden"></div>
    <header class="control-header">
        <div class="header-left">
            <h1><i class="fas fa-cog"></i> <?php echo htmlspecialchars(cp_t('meta.control_panel'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <span class="header-subtitle header-subtitle-rateb"><?php echo htmlspecialchars(cp_t('meta.brand_suffix'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="header-right">
            <?php require __DIR__ . '/../../includes/control/lang-switcher.php'; ?>
            <span class="user-info"><?php echo htmlspecialchars((string) ($_SESSION['control_username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            <a href="<?php echo pageUrl('logout.php'); ?>?control=1" class="btn-logout"><?php echo htmlspecialchars(cp_t('nav.logout'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </header>

    <div class="control-layout<?php echo cp_is_rtl() ? ' cp-layout-rtl' : ''; ?>">
        <?php if (control_sidebar_before_main()) { control_render_sidebar(); } ?>

        <main class="control-content">
            <div class="content-header content-header-infra">
                <button class="sidebar-toggle" id="sidebar-toggle" aria-label="<?php echo htmlspecialchars(cp_t('layout.toggle_sidebar'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="content-header-infra__titles">
                    <h2 class="mb-0"><i class="fas fa-network-wired me-2"></i><?php echo htmlspecialchars(cp_t('infra.title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="content-header-infra__subtitle text-muted mb-0 small"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <a href="<?php echo htmlspecialchars($controlDashboardUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-light ms-auto"><?php echo htmlspecialchars(cp_t('infra.back_dashboard'), ENT_QUOTES, 'UTF-8'); ?></a>
            </div>

            <nav class="infra-shell-nav" aria-label="<?php echo htmlspecialchars(cp_t('infra.title'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach ($infraTabs as $tabKey => $tabMeta) :
                    $tabHref = $infraShellBase . '&view=' . rawurlencode($tabKey);
                    $isTabActive = $view === $tabKey;
                    ?>
                <a
                    href="<?php echo htmlspecialchars($tabHref, ENT_QUOTES, 'UTF-8'); ?>"
                    class="infra-shell-tab<?php echo $isTabActive ? ' is-active' : ''; ?>"
                    <?php echo $isTabActive ? 'aria-current="page"' : ''; ?>
                ><i class="fas <?php echo htmlspecialchars($tabMeta['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i><?php echo htmlspecialchars($tabMeta['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endforeach; ?>
            </nav>

            <section class="infra-embed-wrap">
                <iframe
                    class="infra-embed-frame"
                    src="<?php echo htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    title="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>"
                    loading="lazy"
                    referrerpolicy="same-origin"
                ></iframe>
            </section>
        </main>
        <?php if (!control_sidebar_before_main()) { control_render_sidebar(); } ?>
    </div>

    <?php if (function_exists('cp_i18n_inline_script')) { echo cp_i18n_inline_script(); } ?>
    <script src="<?php echo asset('js/control/i18n.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/app-config-init.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/permissions.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/system.js'); ?>?v=<?php echo time(); ?>"></script>
</body>
</html>
