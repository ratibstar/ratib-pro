<?php
/**
 * Public nav / bootstrap health probe (no HTML chrome).
 * https://out.ratib.sa/pages/ratib-nav-health.php
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');

$root = dirname(__DIR__);
$errors = [];

require_once $root . '/includes/config.php';
require_once $root . '/includes/ratib-public-base-url.php';
$baseUrl = ratib_public_site_base_url();

ob_start();
try {
    require_once $root . '/includes/ratib-home-public-nav-bootstrap.php';
} catch (Throwable $e) {
    $errors[] = 'bootstrap_throw=' . $e->getMessage();
}
$bootstrapOutput = (string) ob_get_clean();
if ($bootstrapOutput !== '') {
    $errors[] = 'bootstrap_output=' . preg_replace('/\s+/', ' ', substr($bootstrapOutput, 0, 200));
}

$checks = [
    'ratibHomePublicCssQuery' => isset($ratibHomePublicCssQuery) && (string) $ratibHomePublicCssQuery !== '',
    'ratibMegaNavCssQuery' => isset($ratibMegaNavCssQuery) && (string) $ratibMegaNavCssQuery !== '',
    'ratibPublicNavBrandCssQuery' => isset($ratibPublicNavBrandCssQuery) && (string) $ratibPublicNavBrandCssQuery !== '',
    'ratibEnterpriseCalmCssQuery' => isset($ratibEnterpriseCalmCssQuery) && (string) $ratibEnterpriseCalmCssQuery !== '',
    'ratibMegaNavJsQuery' => isset($ratibMegaNavJsQuery) && (string) $ratibMegaNavJsQuery !== '',
];

$bootstrapPath = $root . '/includes/ratib-home-public-nav-bootstrap.php';
$bootstrapBody = is_file($bootstrapPath) ? (string) file_get_contents($bootstrapPath, false, null, 0, 16000) : '';

echo "ratib-nav-health\n";
echo 'ok=' . (empty($errors) && !in_array(false, $checks, true) ? 'yes' : 'no') . "\n";
echo 'host=' . ($_SERVER['HTTP_HOST'] ?? '') . "\n";
echo 'build=' . (is_file($root . '/public/ratib-build.txt') ? trim((string) file_get_contents($root . '/public/ratib-build.txt')) : 'missing') . "\n";
echo 'bootstrap_preflight=' . (str_contains($bootstrapBody, 'ratib-nav-asset-preflight.php') ? 'yes' : 'no') . "\n";
echo 'bootstrap_early_globals_bug=' . (preg_match('/\\$GLOBALS\\[\'ratibEnterpriseCalmCssQuery\'\\][^;]+;\\s*\\$ratibEnterpriseCalmCssPath/s', $bootstrapBody) === 1 ? 'yes' : 'no') . "\n";

foreach ($checks as $name => $pass) {
    echo $name . '=' . ($pass ? 'ok' : 'missing') . "\n";
}

if ($errors !== []) {
    echo "errors\n";
    foreach ($errors as $err) {
        echo $err . "\n";
    }
}
