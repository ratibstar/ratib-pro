<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$steps = [];

try {
    require_once __DIR__ . '/../includes/config.php';
    $steps[] = 'config=ok';
    require_once __DIR__ . '/../includes/rateb-public-base-url.php';
    $baseUrl = rateb_public_site_base_url();
    $steps[] = 'baseUrl=' . $baseUrl;
    require_once __DIR__ . '/../includes/rateb-home-public-nav-bootstrap.php';
    $steps[] = 'nav-bootstrap=ok';
    require_once __DIR__ . '/../includes/rateb-about-profile-data.php';
    rateb_about_profile_config($baseUrl);
    $steps[] = 'about-profile=ok';
    ob_start();
    include __DIR__ . '/../includes/rateb-home-public-chrome-top.php';
    $html = (string) ob_get_clean();
    $steps[] = 'chrome-top-bytes=' . strlen($html);
    $steps[] = 'chrome-pin=' . (str_contains($html, 'rateb-public-header-pin') ? 'yes' : 'NO');
} catch (Throwable $e) {
    $steps[] = 'FAIL ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
}

echo implode("\n", $steps) . "\n";
