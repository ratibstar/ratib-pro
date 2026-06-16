<?php
/**
 * Public: Enterprise company profile / About RATEB.
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
require_once __DIR__ . '/../includes/rateb-about-profile-data.php';
require_once __DIR__ . '/../includes/rateb-about-sections.php';

$about = rateb_about_profile_config($baseUrl);
$ratebAboutPageActive = true;
// Keep platform pills on /profile/ anchors — not home.php#platform (that felt like "opening home").
$ratebHomeNavHrefPrefix = function_exists('rateb_public_profile_nav_prefix')
    ? rateb_public_profile_nav_prefix($baseUrl)
    : rtrim($baseUrl, '/') . '/profile/';

$ratebAboutCssPath = __DIR__ . '/../css/pages/about-enterprise.css';
clearstatcache(true, $ratebAboutCssPath);
$ratebAboutCssQuery = (int) (@filemtime($ratebAboutCssPath) ?: time()) . '-' . $ratebHomeUiRev . '-' . $ratebHomePhpMtime . $ratebHomeAssetExtraQ . '-c' . $ratebChromeBundleHash;

$ratebAboutJsPath = __DIR__ . '/../js/pages/about-enterprise.js';
clearstatcache(true, $ratebAboutJsPath);
$ratebAboutJsQuery = (int) (@filemtime($ratebAboutJsPath) ?: time()) . '-' . $ratebHomeUiRev . '-c' . $ratebChromeBundleHash;

$ratebOpBuildMarker = '';
$ratebOpBuildPath = __DIR__ . '/../public/rateb-build.txt';
if (is_file($ratebOpBuildPath)) {
    $ratebOpBuildMarker = trim((string) file_get_contents($ratebOpBuildPath));
}

$ratebGalleryLbJsPath = __DIR__ . '/../js/pages/rateb-gallery-lightbox.js';
clearstatcache(true, $ratebGalleryLbJsPath);
$ratebGalleryLbJsQuery = (int) (@filemtime($ratebGalleryLbJsPath) ?: time()) . '-' . $ratebHomeUiRev . ($ratebOpBuildMarker !== '' ? '-' . $ratebOpBuildMarker : '');

$ratebOpCompactJsPath = __DIR__ . '/../js/pages/rateb-op-proof-compact.js';
clearstatcache(true, $ratebOpCompactJsPath);
$ratebOpCompactJsQuery = (int) (@filemtime($ratebOpCompactJsPath) ?: time()) . '-' . $ratebHomeUiRev . ($ratebOpBuildMarker !== '' ? '-' . $ratebOpBuildMarker : '');
$ratebMarketingFocusedJsPath = __DIR__ . '/../js/pages/rateb-marketing-focused.js';
$ratebMarketingFocusedJsQuery = (int) (@filemtime($ratebMarketingFocusedJsPath) ?: time()) . '-' . $ratebHomeUiRev;

$metaTitle = (string) ($about['meta']['title'] ?? 'About RATEB');
$metaDesc = (string) ($about['meta']['description'] ?? '');
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
    <link rel="canonical" href="<?php echo htmlspecialchars($baseUrl . '/profile', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title><?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php rateb_home_public_nav_emit_stylesheets($baseUrl); ?>
    <?php
    $ratebOverlayGuardPath = __DIR__ . '/../includes/rateb-overlay-dismiss-guard.php';
    if (is_file($ratebOverlayGuardPath)) {
        require_once $ratebOverlayGuardPath;
    }
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/about-enterprise.css?v=<?php echo htmlspecialchars($ratebAboutCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $ratebOpCssPath = __DIR__ . '/../css/pages/operational-proof.css';
    $ratebOpCssQuery = (int) (@filemtime($ratebOpCssPath) ?: time()) . '-' . $ratebHomeUiRev . ($ratebOpBuildMarker !== '' ? '-' . $ratebOpBuildMarker : '');
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/operational-proof.css?v=<?php echo htmlspecialchars($ratebOpCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $ratebMarketingFocusedCssPath = __DIR__ . '/../css/pages/home-marketing-focused.css';
    $ratebMarketingFocusedCssQuery = (int) (@filemtime($ratebMarketingFocusedCssPath) ?: time()) . '-' . $ratebHomeUiRev;
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/home-marketing-focused.css?v=<?php echo htmlspecialchars($ratebMarketingFocusedCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/chat-widget.css">
    <?php if (function_exists('rateb_marketing_emit_focused_rescue_css')) {
        rateb_marketing_emit_focused_rescue_css();
    } ?>
    <script type="application/ld+json"><?php echo json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'RATEB — Recruitment Automation & Telemetry Enterprise Base',
        'url' => $baseUrl . '/profile',
        'logo' => $baseUrl . '/assets/rateb-logo.svg',
        'description' => $metaDesc,
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Riyadh',
            'addressCountry' => 'SA',
        ],
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'telephone' => '+966-599-863-868',
            'contactType' => 'sales',
            'email' => 'info@rateb.sa',
            'availableLanguage' => ['English', 'Arabic'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
</head>
<body class="rateb-saas-home rateb-about-page <?php echo htmlspecialchars(rateb_public_marketing_density_body_class(), ENT_QUOTES, 'UTF-8'); ?>" data-rateb-about="1" data-rateb-marketing-density="<?php echo htmlspecialchars(rateb_public_marketing_density(), ENT_QUOTES, 'UTF-8'); ?>">

<?php
include __DIR__ . '/../includes/rateb-home-public-chrome-top.php';
$ratebMarketingHomeUrl = rateb_public_marketing_home_url($baseUrl);
?>

<div class="rateb-profile-distinct-banner" role="status" data-rateb-profile-distinct="1">
    <div class="rateb-about-container rateb-profile-distinct-banner__inner">
        <span class="rateb-profile-distinct-banner__badge" aria-hidden="true">Company profile</span>
        <p class="rateb-profile-distinct-banner__text">Full <strong>RATEB Company</strong> profile — platform identity, contact, mission, and services below.</p>
        <a class="rateb-profile-distinct-banner__link" href="<?php echo htmlspecialchars($ratebMarketingHomeUrl, ENT_QUOTES, 'UTF-8'); ?>">Marketing home →</a>
    </div>
</div>

<main class="rateb-about-main" id="main">
    <nav class="rateb-about-jump" aria-label="On this page">
        <div class="rateb-about-container rateb-about-jump__inner">
            <a href="#company-profile">Company</a>
            <?php if (!rateb_public_marketing_is_focused()) { ?>
            <a href="#platform-overview">Platform</a>
            <a href="#what-is-rateb">Capabilities</a>
            <a href="#architecture">Architecture</a>
            <a href="#government-oversight">Government</a>
            <a href="#operational-proof">Operational proof</a>
            <a href="#operations">Operations</a>
            <a href="#telemetry">Telemetry</a>
            <a href="#governance">Governance</a>
            <a href="#finance">Finance</a>
            <a href="#corridors">Corridors</a>
            <?php } else { ?>
            <a href="<?php echo htmlspecialchars(rateb_public_marketing_home_url($baseUrl, [], '#programs'), ENT_QUOTES, 'UTF-8'); ?>">Pricing</a>
            <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/architecture/', ENT_QUOTES, 'UTF-8'); ?>">Architecture</a>
            <?php } ?>
            <a href="#contact-cta">Contact</a>
        </div>
    </nav>
    <?php rateb_about_render_sections($about, $baseUrl); ?>

    <?php require __DIR__ . '/../includes/rateb-gallery-lightbox-markup.php'; ?>
</main>

<?php include __DIR__ . '/../includes/rateb-home-public-footer.php'; ?>

<?php
$ratebProfileGuardJsAbout = __DIR__ . '/../js/pages/rateb-profile-nav-guard.js';
clearstatcache(true, $ratebProfileGuardJsAbout);
$ratebProfileGuardQAbout = (string) (int) (@filemtime($ratebProfileGuardJsAbout) ?: time());
?>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/rateb-profile-nav-guard.js?v=<?php echo htmlspecialchars($ratebProfileGuardQAbout, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/rateb-home-nav-chrome.js?v=<?php echo htmlspecialchars($ratebMegaNavJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/rateb-gallery-lightbox.js?v=<?php echo htmlspecialchars($ratebGalleryLbJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/rateb-op-proof-compact.js?v=<?php echo htmlspecialchars($ratebOpCompactJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/about-enterprise.js?v=<?php echo htmlspecialchars($ratebAboutJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/rateb-marketing-focused.js?v=<?php echo htmlspecialchars($ratebMarketingFocusedJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php
$ratebPublicChatSkipCss = true;
require_once __DIR__ . '/../includes/chat-widget-public-footer.php';
?>
</body>
</html>
