<?php
/**
 * Public: Enterprise platform architecture — layered orchestration infrastructure.
 * Canonical URL: /architecture/
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('X-LiteSpeed-Cache-Control: no-cache', false);
    header('Surrogate-Control: no-store');
    header('CDN-Cache-Control: no-store');
}

require_once __DIR__ . '/../includes/ratib-public-base-url.php';
$baseUrl = ratib_public_site_base_url();

require_once __DIR__ . '/../includes/ratib-home-public-nav-bootstrap.php';
$ratibArchDataPath = __DIR__ . '/../includes/ratib-architecture-data.php';
$ratibArchSectionsPath = __DIR__ . '/../includes/ratib-architecture-sections.php';
if (!is_file($ratibArchDataPath) || !is_file($ratibArchSectionsPath)) {
    header('Location: ' . ratib_public_marketing_home_url($baseUrl, ['density' => 'full'], '#enterprise-infrastructure'), true, 302);
    exit;
}
require_once $ratibArchDataPath;
require_once $ratibArchSectionsPath;

$arch = ratib_architecture_config($baseUrl);
$ratibArchPageActive = true;
$ratibHomeNavHrefPrefix = function_exists('ratib_public_nav_marketing_home_prefix')
    ? ratib_public_nav_marketing_home_prefix($baseUrl)
    : rtrim($baseUrl, '/') . '/pages/home.php';

$ratibAboutCssPath = __DIR__ . '/../css/pages/about-enterprise.css';
clearstatcache(true, $ratibAboutCssPath);
$ratibAboutCssQuery = (int) (@filemtime($ratibAboutCssPath) ?: time()) . '-' . $ratibHomeUiRev . '-' . $ratibHomePhpMtime . $ratibHomeAssetExtraQ . '-c' . $ratibChromeBundleHash;

$ratibArchCssPath = __DIR__ . '/../css/pages/architecture.css';
clearstatcache(true, $ratibArchCssPath);
$ratibArchCssQuery = (int) (@filemtime($ratibArchCssPath) ?: time()) . '-' . $ratibHomeUiRev . '-c' . $ratibChromeBundleHash;

$ratibArchJsPath = __DIR__ . '/../js/pages/architecture.js';
clearstatcache(true, $ratibArchJsPath);
$ratibArchJsQuery = (int) (@filemtime($ratibArchJsPath) ?: time()) . '-' . $ratibHomeUiRev . '-c' . $ratibChromeBundleHash;

$metaTitle = (string) ($arch['meta']['title'] ?? 'Platform Architecture — RATIB');
$metaDesc = (string) ($arch['meta']['description'] ?? '');
$canonicalUrl = rtrim($baseUrl, '/') . '/architecture/';
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
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title><?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/home-public.css?v=<?php echo htmlspecialchars($ratibHomePublicCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/ratib-mega-nav.css?v=<?php echo htmlspecialchars($ratibMegaNavCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/about-enterprise.css?v=<?php echo htmlspecialchars($ratibAboutCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/architecture.css?v=<?php echo htmlspecialchars($ratibArchCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $ratibOpCssPath = __DIR__ . '/../css/pages/operational-proof.css';
    $ratibOpCssQuery = (int) (@filemtime($ratibOpCssPath) ?: time()) . '-' . $ratibHomeUiRev;
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/operational-proof.css?v=<?php echo htmlspecialchars($ratibOpCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $ratibEntCssPath = __DIR__ . '/../css/pages/enterprise-trust-layer.css';
    clearstatcache(true, $ratibEntCssPath);
    $ratibEntCssQuery = (int) (@filemtime($ratibEntCssPath) ?: time()) . '-' . $ratibHomeUiRev . '-c' . $ratibChromeBundleHash;
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/enterprise-trust-layer.css?v=<?php echo htmlspecialchars($ratibEntCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <script type="application/ld+json"><?php echo json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'TechArticle',
        'headline' => $metaTitle,
        'description' => $metaDesc,
        'url' => $canonicalUrl,
        'author' => [
            '@type' => 'Organization',
            'name' => 'Ratib Software Foundation for Information Technology',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'RATIB',
        ],
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => 'RATIB',
            'url' => rtrim($baseUrl, '/') . '/',
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
</head>
<body class="ratib-saas-home ratib-arch-page" data-ratib-arch="1" style="background:#080c14 !important">

<?php
include __DIR__ . '/../includes/ratib-home-public-chrome-top.php';
$ratibMarketingHomeUrl = function_exists('ratib_public_marketing_home_url')
    ? ratib_public_marketing_home_url($baseUrl)
    : rtrim($baseUrl, '/') . '/pages/home.php';
$ratibSecurityUrl = rtrim($baseUrl, '/') . '/security-compliance/';
?>

<div class="ratib-arch-distinct-banner" role="status">
    <div class="ratib-about-container ratib-arch-distinct-banner__inner">
        <span class="ratib-arch-distinct-banner__badge" aria-hidden="true">Architecture</span>
        <p class="ratib-arch-distinct-banner__text">Platform architecture documentation for <strong>RATIB</strong> workforce program operations — technical briefing, not a product landing page.</p>
        <a class="ratib-arch-distinct-banner__link" href="<?php echo htmlspecialchars($ratibSecurityUrl, ENT_QUOTES, 'UTF-8'); ?>">Security center</a>
        <a class="ratib-arch-distinct-banner__link" href="<?php echo htmlspecialchars($ratibMarketingHomeUrl, ENT_QUOTES, 'UTF-8'); ?>">Marketing home</a>
    </div>
</div>

<main class="ratib-arch-main" id="main">
    <nav class="ratib-about-jump" aria-label="On this page">
        <div class="ratib-about-container ratib-about-jump__inner">
            <a href="#top">Overview</a>
            <a href="#architecture-overview">Context</a>
            <a href="#layered-control-plane">Layers</a>
            <a href="#multi-tenant-isolation">Isolation</a>
            <a href="#event-driven">Events</a>
            <a href="#telemetry-intelligence">Telemetry</a>
            <a href="#finance-infrastructure">Finance</a>
            <a href="#operational-governance">Governance</a>
            <a href="#deployment-model">Deployment</a>
            <a href="#operational-proof">Diagrams</a>
        </div>
    </nav>
    <?php ratib_architecture_render_sections($arch, $baseUrl); ?>
    <?php
    ratib_operational_proof_render($baseUrl, [
        'title' => 'Reference diagrams & workflows',
        'sub' => 'Illustrative models for technical review—complement the sections above.',
    ], ['screenshots' => false]);
    ?>
</main>

<?php include __DIR__ . '/../includes/ratib-home-public-footer.php'; ?>

<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/ratib-home-nav-chrome.js?v=<?php echo htmlspecialchars($ratibMegaNavJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/ratib-mega-nav.js?v=<?php echo htmlspecialchars($ratibMegaNavJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/architecture.js?v=<?php echo htmlspecialchars($ratibArchJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php
require_once __DIR__ . '/../includes/ratib-page-stamp.php';
ratib_emit_page_stamp('architecture');
?>
</body>
</html>
