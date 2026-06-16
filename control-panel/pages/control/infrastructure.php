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
        'title' => 'Infrastructure Control',
        'url' => $siteRootUrl . '/modules/infrastructure-marketplace/Views/admin/control.php',
    ],
    'dashboard' => [
        'title' => 'Infrastructure Dashboard',
        'url' => $siteRootUrl . '/modules/infrastructure-marketplace/Views/admin/dashboard.php',
    ],
    'providers' => [
        'title' => 'Infrastructure Providers',
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
    'control' => ['label' => 'Control', 'icon' => 'fa-sliders-h'],
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'fa-chart-line'],
    'providers' => ['label' => 'Providers', 'icon' => 'fa-plug'],
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars('Infrastructure — ' . ($infraTabs[$view]['label'] ?? $pageTitle), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/control/system.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/control/infrastructure-embed.css'); ?>?v=<?php echo time(); ?>">
</head>
<body class="control-system-body">
    <div id="app-config" data-base-url="<?php echo htmlspecialchars($fullBase, ENT_QUOTES, 'UTF-8'); ?>" data-api-base="<?php echo htmlspecialchars($fullBase . '/api', ENT_QUOTES, 'UTF-8'); ?>" data-control-api-path="<?php echo htmlspecialchars($fullBase . '/api/control', ENT_QUOTES, 'UTF-8'); ?>" data-control="1" class="hidden"></div>
    <header class="control-header">
        <div class="header-left">
            <h1><i class="fas fa-cog"></i> Control Panel</h1>
            <span class="header-subtitle header-subtitle-rateb">RATEB — Recruitment Automation &amp; Telemetry Enterprise Base</span>
        </div>
        <div class="header-right">
            <span class="user-info"><?php echo htmlspecialchars((string) ($_SESSION['control_username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            <a href="<?php echo pageUrl('logout.php'); ?>?control=1" class="btn-logout">Logout</a>
        </div>
    </header>

    <div class="control-layout">
        <?php include __DIR__ . '/../../includes/control/sidebar.php'; ?>

        <main class="control-content">
            <div class="content-header content-header-infra">
                <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="content-header-infra__titles">
                    <h2 class="mb-0"><i class="fas fa-network-wired me-2"></i>Infrastructure</h2>
                    <p class="content-header-infra__subtitle text-muted mb-0 small"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <a href="<?php echo htmlspecialchars($controlDashboardUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-light ms-auto">Back to Dashboard</a>
            </div>

            <nav class="infra-shell-nav" aria-label="Infrastructure views">
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
    </div>

    <script src="<?php echo asset('js/control/app-config-init.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/permissions.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/system.js'); ?>?v=<?php echo time(); ?>"></script>
</body>
</html>
