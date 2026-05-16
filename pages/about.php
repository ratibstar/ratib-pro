<?php
/**
 * Public: Enterprise company profile / About RATIB.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;

require_once __DIR__ . '/../includes/ratib-home-public-nav-bootstrap.php';
require_once __DIR__ . '/../includes/ratib-about-profile-data.php';
require_once __DIR__ . '/../includes/ratib-about-sections.php';

$about = ratib_about_profile_config($baseUrl);
$ratibAboutPageActive = true;
$ratibHomeNavHrefPrefix = $baseUrl . '/pages/home.php';

$ratibAboutCssPath = __DIR__ . '/../css/pages/about-enterprise.css';
clearstatcache(true, $ratibAboutCssPath);
$ratibAboutCssQuery = (int) (@filemtime($ratibAboutCssPath) ?: time()) . '-' . $ratibHomeUiRev . '-' . $ratibHomePhpMtime . $ratibHomeAssetExtraQ . '-c' . $ratibChromeBundleHash;

$ratibAboutJsPath = __DIR__ . '/../js/pages/about-enterprise.js';
clearstatcache(true, $ratibAboutJsPath);
$ratibAboutJsQuery = (int) (@filemtime($ratibAboutJsPath) ?: time()) . '-' . $ratibHomeUiRev . '-c' . $ratibChromeBundleHash;

$metaTitle = (string) ($about['meta']['title'] ?? 'About RATIB');
$metaDesc = (string) ($about['meta']['description'] ?? '');
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <?php ratib_home_nav_emit_sync_guard_style(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($metaDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <link rel="canonical" href="<?php echo htmlspecialchars($baseUrl . '/pages/about.php', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title><?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/home-public.css?v=<?php echo htmlspecialchars($ratibHomePublicCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/ratib-mega-nav.css?v=<?php echo htmlspecialchars($ratibMegaNavCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/about-enterprise.css?v=<?php echo htmlspecialchars($ratibAboutCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <script type="application/ld+json"><?php echo json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'RATIB — Ratib Software Foundation for Information Technology',
        'url' => $baseUrl . '/pages/about.php',
        'logo' => $baseUrl . '/assets/ratib-logo.svg',
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
            'email' => 'ratibstar@gmail.com',
            'availableLanguage' => ['English', 'Arabic'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
</head>
<body class="ratib-saas-home ratib-about-page" data-ratib-about="1">

<?php include __DIR__ . '/../includes/ratib-home-public-chrome-top.php'; ?>

<main class="ratib-about-main" id="main">
    <nav class="ratib-about-jump" aria-label="On this page">
        <div class="ratib-about-container ratib-about-jump__inner">
            <a href="#what-is-ratib">Platform</a>
            <a href="#architecture">Architecture</a>
            <a href="#operations">Operations</a>
            <a href="#telemetry">Telemetry</a>
            <a href="#governance">Governance</a>
            <a href="#finance">Finance</a>
            <a href="#corridors">Corridors</a>
            <a href="#contact-cta">Contact</a>
        </div>
    </nav>
    <?php ratib_about_render_sections($about, $baseUrl); ?>
</main>

<?php include __DIR__ . '/../includes/ratib-home-public-footer.php'; ?>

<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/ratib-home-nav-chrome.js?v=<?php echo htmlspecialchars($ratibMegaNavJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/ratib-mega-nav.js?v=<?php echo htmlspecialchars($ratibMegaNavJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/about-enterprise.js?v=<?php echo htmlspecialchars($ratibAboutJsQuery, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
