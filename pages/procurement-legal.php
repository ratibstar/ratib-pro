<?php
/**
 * Public: Procurement & legal — enterprise buyer and compliance reference.
 * Canonical URL: /procurement-legal/
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
require_once __DIR__ . '/../includes/ratib-procurement-legal-data.php';
require_once __DIR__ . '/../includes/ratib-procurement-legal-sections.php';

$proc = ratib_procurement_legal_config($baseUrl);
$ratibProcPageActive = true;
$ratibHomeNavHrefPrefix = function_exists('ratib_public_nav_marketing_home_prefix')
    ? ratib_public_nav_marketing_home_prefix($baseUrl)
    : rtrim($baseUrl, '/') . '/pages/home.php';

$ratibAboutCssPath = __DIR__ . '/../css/pages/about-enterprise.css';
clearstatcache(true, $ratibAboutCssPath);
$ratibAboutCssQuery = (int) (@filemtime($ratibAboutCssPath) ?: time()) . '-' . $ratibHomeUiRev . '-' . $ratibHomePhpMtime . $ratibHomeAssetExtraQ . '-c' . $ratibChromeBundleHash;

$ratibProcCssPath = __DIR__ . '/../css/pages/procurement-legal.css';
clearstatcache(true, $ratibProcCssPath);
$ratibProcCssQuery = (int) (@filemtime($ratibProcCssPath) ?: time()) . '-' . $ratibHomeUiRev . '-c' . $ratibChromeBundleHash;

$ratibProcJsPath = __DIR__ . '/../js/pages/procurement-legal.js';
clearstatcache(true, $ratibProcJsPath);
$ratibProcJsQuery = (int) (@filemtime($ratibProcJsPath) ?: time()) . '-' . $ratibHomeUiRev . '-c' . $ratibChromeBundleHash;

$metaTitle = (string) ($proc['meta']['title'] ?? 'Procurement & Legal — RATEB');
$metaDesc = (string) ($proc['meta']['description'] ?? '');
$canonicalUrl = rtrim($baseUrl, '/') . '/procurement-legal/';
$root = rtrim($baseUrl, '/');
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
    <?php ratib_home_public_nav_emit_stylesheets($baseUrl); ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/about-enterprise.css?v=<?php echo htmlspecialchars($ratibAboutCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/procurement-legal.css?v=<?php echo htmlspecialchars($ratibProcCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $ratibEntCssPath = __DIR__ . '/../css/pages/enterprise-trust-layer.css';
    clearstatcache(true, $ratibEntCssPath);
    $ratibEntCssQuery = (int) (@filemtime($ratibEntCssPath) ?: time()) . '-' . $ratibHomeUiRev . '-c' . $ratibChromeBundleHash;
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/enterprise-trust-layer.css?v=<?php echo htmlspecialchars($ratibEntCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <script type="application/ld+json"><?php echo json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $metaTitle,
        'description' => $metaDesc,
        'url' => $canonicalUrl,
        'about' => [
            '@type' => 'Organization',
            'name' => 'RATEB Platform',
            'email' => 'info@rateb.sa',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Riyadh',
                'addressCountry' => 'SA',
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
</head>
<body class="ratib-saas-home ratib-proc-page" data-ratib-proc="1" style="background:#0c0f14 !important">

<?php
include __DIR__ . '/../includes/ratib-home-public-chrome-top.php';
$ratibMarketingHomeUrl = function_exists('ratib_public_marketing_home_url')
    ? ratib_public_marketing_home_url($baseUrl)
    : rtrim($baseUrl, '/') . '/pages/home.php';
?>

<div class="ratib-proc-distinct-banner" role="status">
    <div class="ratib-about-container ratib-proc-distinct-banner__inner">
        <span class="ratib-proc-distinct-banner__badge" aria-hidden="true">Procurement</span>
        <p class="ratib-proc-distinct-banner__text">Formal reference for government buyers, enterprise procurement, international partners, and compliance reviewers.</p>
        <div class="ratib-proc-distinct-banner__links">
            <a class="ratib-proc-distinct-banner__link" href="<?php echo htmlspecialchars($root . '/security-compliance/', ENT_QUOTES, 'UTF-8'); ?>">Security</a>
            <a class="ratib-proc-distinct-banner__link" href="<?php echo htmlspecialchars($root . '/architecture/', ENT_QUOTES, 'UTF-8'); ?>">Architecture</a>
            <a class="ratib-proc-distinct-banner__link" href="<?php echo htmlspecialchars($ratibMarketingHomeUrl, ENT_QUOTES, 'UTF-8'); ?>">Home</a>
        </div>
    </div>
</div>

<main class="ratib-proc-main" id="main">
    <nav class="ratib-about-jump" aria-label="On this page">
        <div class="ratib-about-container ratib-about-jump__inner">
            <a href="#top">Overview</a>
            <a href="#company-identity">Identity</a>
            <a href="#enterprise-engagement">Engagement</a>
            <a href="#security-governance">Security</a>
            <a href="#data-tenant-boundaries">Data</a>
            <a href="#legal-operational-notes">Legal</a>
            <a href="#procurement-requests">Requests</a>
            <a href="#contact-escalation">Contact</a>
        </div>
    </nav>
    <?php ratib_procurement_legal_render_sections($proc); ?>
</main>

<?php include __DIR__ . '/../includes/ratib-home-public-footer.php'; ?>

<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/ratib-home-nav-chrome.js?v=<?php echo htmlspecialchars($ratibMegaNavJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/procurement-legal.js?v=<?php echo htmlspecialchars($ratibProcJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php require_once __DIR__ . '/../includes/chat-widget-public-footer.php'; ?>
</body>
</html>
