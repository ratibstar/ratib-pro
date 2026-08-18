<?php

declare(strict_types=1);

/**
 * POS admin pages use Admin ERP shell (soft-nav); register stays pos-shell full-nav.
 *
 * php modules/pos/tests/validate-pos-admin-erp-shell.php
 */

$root = dirname(__DIR__, 3);
$fail = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
}

$base = (string) file_get_contents($root . '/modules/pos/app/Controllers/PosBaseController.php');
$view = (string) file_get_contents($root . '/modules/pos/app/Support/PosView.php');
$side = (string) file_get_contents($root . '/views/partials/sidebar-nav.php');
$posNav = (string) file_get_contents($root . '/modules/pos/views/partials/sidebar-pos-nav.php');
$dash = (string) file_get_contents($root . '/modules/pos/views/dashboard/index.php');
$gateJs = (string) file_get_contents($root . '/public/assets/pos/js/pos-biometric-gate.js');
$nav = (string) file_get_contents($root . '/public/assets/js/erp-nav-instant.js');
$reg = (string) file_get_contents($root . '/modules/pos/app/Controllers/PosRegisterController.php');
$bio = (string) file_get_contents($root . '/modules/pos/app/Controllers/PosBiometricAuthController.php');
$sw = (string) file_get_contents($root . '/public/pos-sw.js');
$term = (string) file_get_contents($root . '/modules/pos/app/Controllers/PosTerminalsController.php');

assert_true('default posView layout is pos-admin', str_contains($base, "layout = 'pos-admin'"));
assert_true('PosView renders Admin main for pos-admin', str_contains($view, "layout === 'pos-admin'") && str_contains($view, 'layouts/main.php'));
assert_true('terminals no longer forces pos-pages-shell', !str_contains($term, 'pos-pages-shell'));
assert_true('register still uses pos-shell', str_contains($reg, "'pos-shell'"));
assert_true('biometric still uses pos-shell', str_contains($bio, "'pos-shell'"));
assert_true('sidebar full-nav only register/biometric', str_contains($side, "pos/register") && !preg_match("/str_starts_with\(\\\$resourcePath, 'pos\/'\);/", $side));
assert_true(
    'sidebar شاشة البيع opens pos/register',
    str_contains($posNav, "['pos/register', 'pos_register'")
    && !preg_match("/\['pos',\s*'pos_register'/", $posNav)
);
assert_true(
    'شاشة البيع native-opens POS register',
    str_contains($side, 'data-pos-open-register="1"')
    && str_contains($side, "rateb_url('admin/ops/pos/register')")
    && str_contains((string) file_get_contents($root . '/views/layouts/main.php'), '__ratebGoPosRegister')
    && str_contains((string) file_get_contents($root . '/views/layouts/main.php'), "rateb_url('admin/ops/pos/register')")
    && str_contains($nav, "/admin/ops/pos/register")
);
assert_true(
    'dashboard فتح شاشة البيع full-navs to pos/register',
    str_contains($dash, "rateb_url('admin/ops/pos/register')")
    && str_contains($dash, 'data-rateb-full-nav="1"')
    && !str_contains($dash, "rateb_app_url('pos')")
);
assert_true(
    'biometric gate does not fallback to bare /pos/register',
    str_contains($gateJs, 'replace(/\\/biometric\\/?$/i, \'/register\')')
    && !preg_match('#\|\| [\'"]/pos/register[\'"]#', $gateJs)
);
assert_true('nav soft-nav Admin POS (no POS_ADMIN forceFull)', !str_contains($nav, 'POS_ADMIN_PAGES_RE'));
assert_true('SW activate reloads stale Admin tabs', str_contains($sw, 'client.navigate(navUrl)'));
$swBuildOk = (bool) preg_match("/var\s+SW_BUILD_ID\s*=\s*'([^']+)'/", $sw, $swBuildMatch);
$swBuildVer = 0;
if ($swBuildOk && preg_match('/v(\d+)/', (string) ($swBuildMatch[1] ?? ''), $swVerMatch)) {
    $swBuildVer = (int) $swVerMatch[1];
}
assert_true(
    'SW build bumped',
    $swBuildOk && ($swBuildVer >= 161 || str_contains($sw, 'pos-register-url-v161')),
    $swBuildOk ? ('build=' . (string) ($swBuildMatch[1] ?? '')) : 'missing SW_BUILD_ID'
);
assert_true('SW does not bounce POS register to Admin dashboard', str_contains($sw, 'posRegisterDocumentUrl'));
assert_true(
    'online POS admin passthrough (hard-offline only)',
    str_contains($sw, 'Online: never intercept')
    && str_contains($sw, 'posAdminConnectionRequiredResponse')
    && substr_count($sw, 'navigatePosAdminCrudDocument(event.request)') === 0
);

echo PHP_EOL . ($fail === 0 ? 'ALL PASSED' : "FAILED {$fail}") . PHP_EOL;
exit($fail > 0 ? 1 : 0);
