<?php
declare(strict_types=1);

/**
 * One nav stylesheet stack for every page that uses rateb-home-public-chrome-top.php.
 * Requires rateb-home-public-nav-bootstrap.php first.
 */
function rateb_home_public_nav_emit_stylesheets(string $baseUrl): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $q = static fn(string $v): string => $h($v);
    $root = rtrim($baseUrl, '/');

    global $ratebHomePublicCssQuery, $ratebMegaNavCssQuery, $ratebPublicNavBrandCssQuery, $ratebEnterpriseCalmCssQuery;

    $homeQ = (string) ($ratebHomePublicCssQuery ?? $GLOBALS['ratebHomePublicCssQuery'] ?? '');
    if ($homeQ === '') {
        return;
    }

    $megaQ = (string) ($ratebMegaNavCssQuery ?? $GLOBALS['ratebMegaNavCssQuery'] ?? '');
    $brandQ = (string) ($ratebPublicNavBrandCssQuery ?? $GLOBALS['ratebPublicNavBrandCssQuery'] ?? '');
    $calmQ = (string) ($ratebEnterpriseCalmCssQuery ?? $GLOBALS['ratebEnterpriseCalmCssQuery'] ?? '');

    echo '<link rel="stylesheet" href="' . $q($root . '/css/pages/home-public.css?v=' . $homeQ) . '">' . "\n";
    if ($megaQ !== '') {
        echo '<link rel="stylesheet" href="' . $q($root . '/css/pages/rateb-mega-nav.css?v=' . $megaQ) . '">' . "\n";
    }
    if ($brandQ !== '') {
        echo '<link rel="stylesheet" href="' . $q($root . '/css/pages/rateb-public-nav-brand.css?v=' . $brandQ) . '">' . "\n";
    }
    if ($calmQ !== '') {
        echo '<link rel="stylesheet" href="' . $q($root . '/css/pages/home-enterprise-calm.css?v=' . $calmQ) . '">' . "\n";
    }
}
