<?php
/**
 * Government & workforce program operations — public enterprise positioning.
 * Canonical: /government-workforce-operations/
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
require_once __DIR__ . '/../includes/ratib-public-cms.php';
require_once __DIR__ . '/../includes/ratib-about-profile-data.php';
require_once __DIR__ . '/../includes/ratib-enterprise-schema.php';

$about = ratib_about_profile_config($baseUrl);
$gov = $about['sections']['gov'] ?? [];
$govPoints = ratib_public_cms_lines('profile.gov.points', []);

$metaTitle = 'Government & Workforce Program Operations — RATEB';
$metaDesc = 'Oversight, inspections, GPS tracking, policy enforcement, and audit replay for ministries and labor programs on RATEB infrastructure.';
$canonicalUrl = rtrim($baseUrl, '/') . '/government-workforce-operations/';

$ratibAboutCssQuery = (int) (@filemtime(__DIR__ . '/../css/pages/about-enterprise.css') ?: time()) . '-' . $ratibHomeUiRev;
$ratibTokensCssQuery = (int) (@filemtime(__DIR__ . '/../css/rateb-enterprise-tokens.css') ?: time());
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../includes/ratib-profile-force-same-tab.php'; ratib_emit_profile_force_same_tab($baseUrl); ratib_home_nav_emit_sync_guard_style(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($metaDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/home-public.css?v=<?php echo htmlspecialchars($ratibHomePublicCssQuery, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/rateb-enterprise-tokens.css?v=<?php echo $ratibTokensCssQuery; ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/about-enterprise.css?v=<?php echo $ratibAboutCssQuery; ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/enterprise-trust-hub.css?v=<?php echo $ratibAboutCssQuery; ?>">
    <?php ratib_enterprise_schema_emit([ratib_enterprise_schema_organization($baseUrl)]); ?>
</head>
<body class="ratib-saas-home ratib-trust-page">
<?php include __DIR__ . '/../includes/ratib-home-public-chrome-top.php'; ?>
<main class="ratib-trust-main" id="main">
    <section class="ratib-eth-hero" id="top">
        <div class="ratib-about-container">
            <p class="ratib-eyebrow ratib-eyebrow--enterprise"><?php echo htmlspecialchars((string) ($gov['eyebrow'] ?? 'Government & labor oversight'), ENT_QUOTES, 'UTF-8'); ?></p>
            <h1 class="ratib-eth-hero__title">Government &amp; Workforce Program Operations</h1>
            <p class="ratib-eth-hero__lead"><?php echo htmlspecialchars((string) ($gov['sub'] ?? 'Oversight, inspections, GPS tracking, and policy enforcement for ministries and labor programs on RATEB infrastructure.'), ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="rateb-sample-data-tag">Program governance · not a consumer recruitment site</p>
        </div>
    </section>
    <section class="ratib-eth-pillar rateb-glass-panel">
        <div class="ratib-about-container">
            <h2 class="ratib-eth-pillar__title">Oversight-aligned execution</h2>
            <ul class="ratib-eth-pillar__list">
                <?php foreach ($govPoints as $line) { ?>
                <li><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php } ?>
                <li>GPS tracking with geofence and route replay for field programs</li>
                <li>Blacklist handling and deploy blocks tied to inspection outcomes</li>
                <li>Immutable workflow history for audit replay and ministry review</li>
            </ul>
            <p><a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/enterprise-trust/', ENT_QUOTES, 'UTF-8'); ?>">Enterprise trust center →</a> · <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/profile/#governance', ENT_QUOTES, 'UTF-8'); ?>">Full profile →</a></p>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/ratib-home-public-footer.php'; ?>
<?php require_once __DIR__ . '/../includes/chat-widget-public-footer.php'; ?>
</body>
</html>
