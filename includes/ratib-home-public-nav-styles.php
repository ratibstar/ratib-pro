<?php
declare(strict_types=1);

/**
 * One nav stylesheet stack for every page that uses ratib-home-public-chrome-top.php.
 * Requires ratib-home-public-nav-bootstrap.php first.
 */
function ratib_home_public_nav_emit_stylesheets(string $baseUrl): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $q = static fn(string $v): string => $h($v);
    $root = rtrim($baseUrl, '/');

    global $ratibHomePublicCssQuery, $ratibMegaNavCssQuery, $ratibPublicNavBrandCssQuery, $ratibEnterpriseCalmCssQuery;

    $homeQ = (string) ($ratibHomePublicCssQuery ?? $GLOBALS['ratibHomePublicCssQuery'] ?? '');
    if ($homeQ === '') {
        return;
    }

    $megaQ = (string) ($ratibMegaNavCssQuery ?? $GLOBALS['ratibMegaNavCssQuery'] ?? '');
    $brandQ = (string) ($ratibPublicNavBrandCssQuery ?? $GLOBALS['ratibPublicNavBrandCssQuery'] ?? '');
    $calmQ = (string) ($ratibEnterpriseCalmCssQuery ?? $GLOBALS['ratibEnterpriseCalmCssQuery'] ?? '');

    echo '<link rel="stylesheet" href="' . $q($root . '/css/pages/home-public.css?v=' . $homeQ) . '">' . "\n";
    if ($megaQ !== '') {
        echo '<link rel="stylesheet" href="' . $q($root . '/css/pages/ratib-mega-nav.css?v=' . $megaQ) . '">' . "\n";
    }
    if ($brandQ !== '') {
        echo '<link rel="stylesheet" href="' . $q($root . '/css/pages/ratib-public-nav-brand.css?v=' . $brandQ) . '">' . "\n";
    }
    if ($calmQ !== '') {
        echo '<link rel="stylesheet" href="' . $q($root . '/css/pages/home-enterprise-calm.css?v=' . $calmQ) . '">' . "\n";
    }
}
