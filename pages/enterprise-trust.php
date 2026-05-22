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

require_once __DIR__ . '/../includes/ratib-public-base-url.php';
$baseUrl = ratib_public_site_base_url();
require_once __DIR__ . '/../includes/ratib-home-public-nav-bootstrap.php';
require_once __DIR__ . '/../includes/ratib-enterprise-trust-hub-data.php';
require_once __DIR__ . '/../includes/ratib-enterprise-trust-hub-sections.php';
require_once __DIR__ . '/../includes/ratib-enterprise-schema.php';

$hub = ratib_enterprise_trust_hub_config($baseUrl);
$ratibTrustPageActive = true;
$ratibHomeNavHrefPrefix = function_exists('ratib_public_nav_marketing_home_prefix')
    ? ratib_public_nav_marketing_home_prefix($baseUrl)
    : rtrim($baseUrl, '/') . '/pages/home.php';

$metaTitle = (string) ($hub['meta']['title'] ?? 'Enterprise Trust Center — RATEB');
$metaDesc = (string) ($hub['meta']['description'] ?? '');
$canonicalUrl = rtrim($baseUrl, '/') . '/enterprise-trust/';

$ratibAboutCssQuery = (int) (@filemtime(__DIR__ . '/../css/pages/about-enterprise.css') ?: time()) . '-' . $ratibHomeUiRev;
$ratibHubCssQuery = (int) (@filemtime(__DIR__ . '/../css/pages/enterprise-trust-hub.css') ?: time()) . '-' . $ratibHomeUiRev;
$ratibTokensCssQuery = (int) (@filemtime(__DIR__ . '/../css/rateb-enterprise-tokens.css') ?: time());
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <?php
    require_once __DIR__ . '/../includes/ratib-profile-force-same-tab.php';
    ratib_emit_profile_force_same_tab($baseUrl);
    ratib_home_nav_emit_sync_guard_style();
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
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/home-public.css?v=<?php echo htmlspecialchars($ratibHomePublicCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/rateb-enterprise-tokens.css?v=<?php echo $ratibTokensCssQuery; ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/about-enterprise.css?v=<?php echo $ratibAboutCssQuery; ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/enterprise-trust-hub.css?v=<?php echo $ratibHubCssQuery; ?>">
    <?php
    ratib_enterprise_schema_emit([
        ratib_enterprise_schema_organization($baseUrl),
        ratib_enterprise_schema_breadcrumb([
            ['RATEB', rtrim($baseUrl, '/') . '/'],
            ['Enterprise Trust Center', $canonicalUrl],
        ]),
    ]);
    ?>
</head>
<body class="ratib-saas-home ratib-trust-page" data-ratib-trust="1">

<?php include __DIR__ . '/../includes/ratib-home-public-chrome-top.php'; ?>

<main class="ratib-trust-main" id="main">
    <?php ratib_enterprise_trust_hub_render($hub, $baseUrl); ?>
</main>

<?php include __DIR__ . '/../includes/ratib-home-public-footer.php'; ?>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/ratib-home-nav-chrome.js?v=<?php echo htmlspecialchars($ratibMegaNavJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/ratib-mega-nav.js?v=<?php echo htmlspecialchars($ratibMegaNavJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php require_once __DIR__ . '/../includes/ratib-page-stamp.php'; ratib_emit_page_stamp('enterprise-trust'); ?>
</body>
</html>
