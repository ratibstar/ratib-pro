<?php
/**
 * Enterprise Trust Hub — procurement-grade trust posture (seven pillars).
 * Canonical: /enterprise-trust/
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('X-LiteSpeed-Cache-Control: no-cache', false);
}

require_once __DIR__ . '/../includes/rateb-public-base-url.php';
$baseUrl = rateb_public_site_base_url();
require_once __DIR__ . '/../includes/rateb-home-public-nav-bootstrap.php';
require_once __DIR__ . '/../includes/rateb-enterprise-trust-hub-data.php';
require_once __DIR__ . '/../includes/rateb-enterprise-trust-hub-sections.php';
require_once __DIR__ . '/../includes/rateb-enterprise-schema.php';

$hub = rateb_enterprise_trust_hub_config($baseUrl);
$ratebTrustPageActive = true;
$ratebHomeNavHrefPrefix = function_exists('rateb_public_nav_marketing_home_prefix')
    ? rateb_public_nav_marketing_home_prefix($baseUrl)
    : rtrim($baseUrl, '/') . '/pages/home.php';

$metaTitle = (string) ($hub['meta']['title'] ?? 'Enterprise Trust Center — RATEB');
$metaDesc = (string) ($hub['meta']['description'] ?? '');
$canonicalUrl = rtrim($baseUrl, '/') . '/enterprise-trust/';

$ratebAboutCssQuery = (int) (@filemtime(__DIR__ . '/../css/pages/about-enterprise.css') ?: time()) . '-' . $ratebHomeUiRev;
$ratebHubCssQuery = (int) (@filemtime(__DIR__ . '/../css/pages/enterprise-trust-hub.css') ?: time()) . '-' . $ratebHomeUiRev;
$ratebTokensCssQuery = (int) (@filemtime(__DIR__ . '/../css/rateb-enterprise-tokens.css') ?: time());
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <?php
    require_once __DIR__ . '/../includes/rateb-profile-force-same-tab.php';
    rateb_emit_profile_force_same_tab($baseUrl);
    rateb_home_nav_emit_sync_guard_style();
    ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($metaDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php rateb_home_public_nav_emit_stylesheets($baseUrl); ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/rateb-enterprise-tokens.css?v=<?php echo $ratebTokensCssQuery; ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/about-enterprise.css?v=<?php echo $ratebAboutCssQuery; ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/enterprise-trust-hub.css?v=<?php echo $ratebHubCssQuery; ?>">
    <?php
    rateb_enterprise_schema_emit([
        rateb_enterprise_schema_organization($baseUrl),
        rateb_enterprise_schema_breadcrumb([
            ['RATEB', rtrim($baseUrl, '/') . '/'],
            ['Enterprise Trust Center', $canonicalUrl],
        ]),
    ]);
    ?>
</head>
<body class="rateb-saas-home rateb-trust-page" data-rateb-trust="1">

<?php include __DIR__ . '/../includes/rateb-home-public-chrome-top.php'; ?>

<main class="rateb-trust-main" id="main">
    <?php rateb_enterprise_trust_hub_render($hub, $baseUrl); ?>
</main>

<?php include __DIR__ . '/../includes/rateb-home-public-footer.php'; ?>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/rateb-home-nav-chrome.js?v=<?php echo htmlspecialchars($ratebMegaNavJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php require_once __DIR__ . '/../includes/chat-widget-public-footer.php'; ?>
</body>
</html>
