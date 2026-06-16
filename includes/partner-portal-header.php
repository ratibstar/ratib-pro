<?php
/**
 * Minimal shell for partner portal (no main app nav).
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/config.php';
}
$ppTitle = isset($pageTitle) ? (string) $pageTitle : 'Partner portal';
$ppUseHomeChrome = !empty($ratebPartnerPortalHomeChrome);
$ppBodyClass = $ppUseHomeChrome ? 'partner-portal-body rateb-saas-home' : 'partner-portal-body';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($ppTitle, ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'RATEB', ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if ($ppUseHomeChrome && isset($ratebHomeUiRev)): ?>
    <?php if (function_exists('rateb_home_nav_emit_sync_guard_style')) {
        rateb_home_nav_emit_sync_guard_style();
    } ?>
    <meta name="rateb-home-ui-rev" content="<?php echo htmlspecialchars((string) $ratebHomeUiRev, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php
    if (function_exists('rateb_home_public_nav_emit_stylesheets') && isset($baseUrl) && is_string($baseUrl) && $baseUrl !== '') {
        rateb_home_public_nav_emit_stylesheets($baseUrl);
    }
    ?>
    <?php endif; ?>
    <?php if (!$ppUseHomeChrome): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <?php endif; ?>
    <?php
    if (!empty($pageCss) && is_array($pageCss)) {
        foreach ($pageCss as $css) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars((string) $css, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }
    ?>
    <?php if (!empty($ratebPartnerPortalNavFallbackCss)): ?>
    <style id="rateb-nav-css-fallback">
      /* Layout-only rescue — platform pills only; no fixed icon sizes (!important blocked compact nav CSS). */
      #ratebNavMenu .rateb-nav__platform-links .rateb-nav__link{display:inline-flex!important;align-items:center!important;gap:.5rem!important}
      #ratebNavMenu .rateb-nav__platform-links .rateb-nav__icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;flex-shrink:0!important}
      #ratebNavMenu .rateb-nav__platform-links .rateb-nav__glyph{display:block!important}
      .rateb-nav__partner-login{display:inline-flex!important;align-items:center!important;gap:.45rem!important}
      .rateb-nav__partner-icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;flex-shrink:0!important;width:2.2rem!important;height:2.2rem!important}
    </style>
    <?php endif; ?>
</head>
<body class="<?php echo htmlspecialchars($ppBodyClass, ENT_QUOTES, 'UTF-8'); ?>"<?php
if ($ppUseHomeChrome && isset($ratebHomeUiRev) && isset($ratebHomePhpMtime)) {
    echo ' data-rateb-home-layout="partner-portal-login" data-rateb-home-ui-rev="' . htmlspecialchars((string) $ratebHomeUiRev, ENT_QUOTES, 'UTF-8') . '" data-rateb-deploy="' . htmlspecialchars((string) $ratebHomePhpMtime . '-' . (string) $ratebHomeUiRev, ENT_QUOTES, 'UTF-8') . '"';
}
?>>
<div class="partner-portal-shell">
