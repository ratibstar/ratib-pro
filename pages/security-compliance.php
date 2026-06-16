<?php
/**
 * Public: Security & compliance trust center — procurement-ready infrastructure posture.
 * Canonical URL: /security-compliance/
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

require_once __DIR__ . '/../includes/rateb-public-base-url.php';
$baseUrl = rateb_public_site_base_url();

require_once __DIR__ . '/../includes/rateb-home-public-nav-bootstrap.php';
require_once __DIR__ . '/../includes/rateb-security-compliance-data.php';
require_once __DIR__ . '/../includes/rateb-security-compliance-sections.php';

$trust = rateb_security_compliance_config($baseUrl);
$ratebTrustPageActive = true;
$ratebHomeNavHrefPrefix = function_exists('rateb_public_nav_marketing_home_prefix')
    ? rateb_public_nav_marketing_home_prefix($baseUrl)
    : rtrim($baseUrl, '/') . '/pages/home.php';

$ratebAboutCssPath = __DIR__ . '/../css/pages/about-enterprise.css';
clearstatcache(true, $ratebAboutCssPath);
$ratebAboutCssQuery = (int) (@filemtime($ratebAboutCssPath) ?: time()) . '-' . $ratebHomeUiRev . '-' . $ratebHomePhpMtime . $ratebHomeAssetExtraQ . '-c' . $ratebChromeBundleHash;

$ratebTrustCssPath = __DIR__ . '/../css/pages/security-compliance.css';
clearstatcache(true, $ratebTrustCssPath);
$ratebTrustCssQuery = (int) (@filemtime($ratebTrustCssPath) ?: time()) . '-' . $ratebHomeUiRev . '-c' . $ratebChromeBundleHash;

$ratebTrustJsPath = __DIR__ . '/../js/pages/security-compliance.js';
clearstatcache(true, $ratebTrustJsPath);
$ratebTrustJsQuery = (int) (@filemtime($ratebTrustJsPath) ?: time()) . '-' . $ratebHomeUiRev . '-c' . $ratebChromeBundleHash;

$metaTitle = (string) ($trust['meta']['title'] ?? 'Security & Compliance — RATEB');
$metaDesc = (string) ($trust['meta']['description'] ?? '');
$canonicalUrl = rtrim($baseUrl, '/') . '/security-compliance/';
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
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title><?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php rateb_home_public_nav_emit_stylesheets($baseUrl); ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/about-enterprise.css?v=<?php echo htmlspecialchars($ratebAboutCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/security-compliance.css?v=<?php echo htmlspecialchars($ratebTrustCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $ratebEntCssPath = __DIR__ . '/../css/pages/enterprise-trust-layer.css';
    clearstatcache(true, $ratebEntCssPath);
    $ratebEntCssQuery = (int) (@filemtime($ratebEntCssPath) ?: time()) . '-' . $ratebHomeUiRev . '-c' . $ratebChromeBundleHash;
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/enterprise-trust-layer.css?v=<?php echo htmlspecialchars($ratebEntCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <script type="application/ld+json"><?php echo json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $metaTitle,
        'description' => $metaDesc,
        'url' => $canonicalUrl,
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => 'RATEB',
            'url' => rtrim($baseUrl, '/') . '/',
        ],
        'about' => [
            '@type' => 'Organization',
            'name' => 'RATEB Platform',
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
</head>
<body class="rateb-saas-home rateb-trust-page" data-rateb-trust="1" style="background:#0a0e1a !important">

<?php
include __DIR__ . '/../includes/rateb-home-public-chrome-top.php';
$ratebMarketingHomeUrl = function_exists('rateb_public_marketing_home_url')
    ? rateb_public_marketing_home_url($baseUrl)
    : rtrim($baseUrl, '/') . '/pages/home.php';
?>

<div class="rateb-trust-distinct-banner" role="status" data-rateb-trust-distinct="1">
    <div class="rateb-about-container rateb-trust-distinct-banner__inner">
        <span class="rateb-trust-distinct-banner__badge" aria-hidden="true">Trust center</span>
        <p class="rateb-trust-distinct-banner__text">Security, compliance governance, and tenant isolation for <strong>RATEB</strong> enterprise workforce program infrastructure.</p>
        <a class="rateb-trust-distinct-banner__link" href="<?php echo htmlspecialchars($ratebMarketingHomeUrl, ENT_QUOTES, 'UTF-8'); ?>">Marketing home →</a>
    </div>
</div>

<main class="rateb-trust-main" id="main">
    <nav class="rateb-about-jump" aria-label="On this page">
        <div class="rateb-about-container rateb-about-jump__inner">
            <a href="#top">Overview</a>
            <a href="#security-overview">Security</a>
            <a href="#compliance-governance">Governance</a>
            <a href="#data-isolation">Isolation</a>
            <a href="#authentication">Access</a>
            <a href="#operational-reliability">Reliability</a>
            <a href="#infrastructure">Infrastructure</a>
            <a href="#procurement">Procurement</a>
        </div>
    </nav>
    <?php rateb_security_compliance_render_sections($trust, $baseUrl); ?>
</main>

<?php include __DIR__ . '/../includes/rateb-home-public-footer.php'; ?>

<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/rateb-home-nav-chrome.js?v=<?php echo htmlspecialchars($ratebMegaNavJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/security-compliance.js?v=<?php echo htmlspecialchars($ratebTrustJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php require_once __DIR__ . '/../includes/chat-widget-public-footer.php'; ?>
</body>
</html>
