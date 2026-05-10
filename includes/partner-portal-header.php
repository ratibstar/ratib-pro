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
$ppUseHomeChrome = !empty($ratibPartnerPortalHomeChrome);
$ppBodyClass = $ppUseHomeChrome ? 'partner-portal-body ratib-saas-home' : 'partner-portal-body';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($ppTitle, ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Ratib', ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if ($ppUseHomeChrome && isset($ratibHomeUiRev)): ?>
    <meta name="ratib-home-ui-rev" content="<?php echo htmlspecialchars((string) $ratibHomeUiRev, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
    <?php if (!empty($ratibPartnerPortalNavFallbackCss)): ?>
    <style id="ratib-nav-css-fallback">
      /* Layout-only rescue if main CSS is stale; matches pages/home.php */
      #ratibNavMenu .ratib-nav__link{display:inline-flex!important;align-items:center!important;gap:.5rem!important}
      #ratibNavMenu .ratib-nav__icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;flex-shrink:0!important;width:2.5rem!important;height:2.5rem!important}
      #ratibNavMenu .ratib-nav__glyph{width:1.35rem!important;height:1.35rem!important;display:block!important}
      .ratib-nav__partner-login{display:inline-flex!important;align-items:center!important;gap:.45rem!important}
      .ratib-nav__partner-icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;flex-shrink:0!important;width:2.2rem!important;height:2.2rem!important}
    </style>
    <?php endif; ?>
</head>
<body class="<?php echo htmlspecialchars($ppBodyClass, ENT_QUOTES, 'UTF-8'); ?>"<?php
if ($ppUseHomeChrome && isset($ratibHomeUiRev) && isset($ratibHomePhpMtime)) {
    echo ' data-ratib-home-layout="partner-portal-login" data-ratib-home-ui-rev="' . htmlspecialchars((string) $ratibHomeUiRev, ENT_QUOTES, 'UTF-8') . '" data-ratib-deploy="' . htmlspecialchars((string) $ratibHomePhpMtime . '-' . (string) $ratibHomeUiRev, ENT_QUOTES, 'UTF-8') . '"';
}
?>>
<div class="partner-portal-shell">
