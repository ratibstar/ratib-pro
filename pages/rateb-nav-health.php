<?php
/**
 * Public nav / bootstrap health probe (no HTML chrome).
 * https://rateb.sa/pages/rateb-nav-health.php
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');

$root = dirname(__DIR__);
$errors = [];

require_once $root . '/includes/config.php';
require_once $root . '/includes/rateb-public-base-url.php';
$baseUrl = rateb_public_site_base_url();

ob_start();
try {
    require_once $root . '/includes/rateb-home-public-nav-bootstrap.php';
} catch (Throwable $e) {
    $errors[] = 'bootstrap_throw=' . $e->getMessage();
}
$bootstrapOutput = (string) ob_get_clean();
if ($bootstrapOutput !== '') {
    $errors[] = 'bootstrap_output=' . preg_replace('/\s+/', ' ', substr($bootstrapOutput, 0, 200));
}

$checks = [
    'ratebHomePublicCssQuery' => isset($ratebHomePublicCssQuery) && (string) $ratebHomePublicCssQuery !== '',
    'ratebMegaNavCssQuery' => isset($ratebMegaNavCssQuery) && (string) $ratebMegaNavCssQuery !== '',
    'ratebPublicNavBrandCssQuery' => isset($ratebPublicNavBrandCssQuery) && (string) $ratebPublicNavBrandCssQuery !== '',
    'ratebEnterpriseCalmCssQuery' => isset($ratebEnterpriseCalmCssQuery) && (string) $ratebEnterpriseCalmCssQuery !== '',
    'ratebMegaNavJsQuery' => isset($ratebMegaNavJsQuery) && (string) $ratebMegaNavJsQuery !== '',
];

$bootstrapPath = $root . '/includes/rateb-home-public-nav-bootstrap.php';
$bootstrapBody = is_file($bootstrapPath) ? (string) file_get_contents($bootstrapPath, false, null, 0, 16000) : '';

echo "rateb-nav-health\n";
echo 'ok=' . (empty($errors) && !in_array(false, $checks, true) ? 'yes' : 'no') . "\n";
echo 'host=' . ($_SERVER['HTTP_HOST'] ?? '') . "\n";
echo 'build=' . (is_file($root . '/public/rateb-build.txt') ? trim((string) file_get_contents($root . '/public/rateb-build.txt')) : 'missing') . "\n";
echo 'bootstrap_preflight=' . (str_contains($bootstrapBody, 'rateb-nav-asset-preflight.php') ? 'yes' : 'no') . "\n";
echo 'bootstrap_early_globals_bug=' . (preg_match('/\\$GLOBALS\\[\'ratebEnterpriseCalmCssQuery\'\\][^;]+;\\s*\\$ratebEnterpriseCalmCssPath/s', $bootstrapBody) === 1 ? 'yes' : 'no') . "\n";

foreach ($checks as $name => $pass) {
    echo $name . '=' . ($pass ? 'ok' : 'missing') . "\n";
}

if ($errors !== []) {
    echo "errors\n";
    foreach ($errors as $err) {
        echo $err . "\n";
    }
}
