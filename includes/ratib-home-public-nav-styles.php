<?php
declare(strict_types=1);

/**
 * Public marketing nav stylesheets — single include for all pages using home chrome.
 * Requires ratib-home-public-nav-bootstrap.php first.
 */
function ratib_home_public_nav_emit_stylesheets(string $baseUrl): void
{
    $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $q = static fn(string $v): string => $h($v);

    if (!isset($GLOBALS['ratibHomePublicCssQuery'])) {
        return;
    }

    echo '<link rel="stylesheet" href="' . $q(rtrim($baseUrl, '/') . '/css/pages/home-public.css?v=' . (string) $GLOBALS['ratibHomePublicCssQuery']) . '">' . "\n";

    if (isset($GLOBALS['ratibMegaNavCssQuery'])) {
        echo '<link rel="stylesheet" href="' . $q(rtrim($baseUrl, '/') . '/css/pages/ratib-mega-nav.css?v=' . (string) $GLOBALS['ratibMegaNavCssQuery']) . '">' . "\n";
    }

    if (isset($GLOBALS['ratibPublicNavBrandCssQuery'])) {
        echo '<link rel="stylesheet" href="' . $q(rtrim($baseUrl, '/') . '/css/pages/ratib-public-nav-brand.css?v=' . (string) $GLOBALS['ratibPublicNavBrandCssQuery']) . '">' . "\n";
    }

    if (isset($GLOBALS['ratibEnterpriseCalmCssQuery'])) {
        echo '<link rel="stylesheet" href="' . $q(rtrim($baseUrl, '/') . '/css/pages/home-enterprise-calm.css?v=' . (string) $GLOBALS['ratibEnterpriseCalmCssQuery']) . '">' . "\n";
    }
}
