<?php
/**
 * Quick live check: is RATEB rebrand active on this server?
 * https://out.ratib.sa/pages/ratib-rebrand-status.php
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/ratib-php74-compat.php';
require_once __DIR__ . '/../includes/ratib-public-cms.php';
require_once __DIR__ . '/../includes/site-content.php';
require_once __DIR__ . '/../includes/site-content-home-data.php';
require_once __DIR__ . '/../includes/ratib-site-content-rebrand-sanitize.php';

$build = trim((string) (@file_get_contents(__DIR__ . '/../public/ratib-build.txt') ?: ''));
$sanitizeFile = __DIR__ . '/../includes/ratib-site-content-rebrand-sanitize.php';
$flat = ratib_site_content_home_flat(false);

echo "build={$build}\n";
echo 'sanitize_file=' . (is_file($sanitizeFile) ? 'yes' : 'NO') . "\n";
echo 'brand=' . ($flat['home.brand.name'] ?? '') . "\n";
echo 'page_title=' . ($flat['home.meta.page_title'] ?? '') . "\n";
echo 'hero_eyebrow=' . ($flat['home.hero.eyebrow'] ?? '') . "\n";
echo 'hero_title=' . ($flat['home.hero.title_before'] ?? '') . ' | ' . ($flat['home.hero.title_gradient'] ?? '') . "\n";
echo 'profile_title=' . ($flat['profile.meta.title'] ?? '') . "\n";
echo 'legal=' . ($flat['profile.company.legal_name'] ?? '') . "\n";

$bad = [];
foreach (['Ratib Company', 'Ratib Software Foundation', 'TRACKING INTELLIGENCE', 'Workforce Intelligence'] as $needle) {
    foreach ($flat as $k => $v) {
        if (is_string($v) && str_contains($v, $needle)) {
            $bad[] = "{$k} => {$needle}";
        }
    }
}
echo 'stale_hits=' . (count($bad) === 0 ? '0' : (string) count($bad)) . "\n";
foreach ($bad as $line) {
    echo "  {$line}\n";
}
