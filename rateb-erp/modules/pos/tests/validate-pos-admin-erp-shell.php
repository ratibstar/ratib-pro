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
assert_true('nav soft-nav Admin POS (no POS_ADMIN forceFull)', !str_contains($nav, 'POS_ADMIN_PAGES_RE'));
assert_true('SW soft-nav allows POS admin HTML', str_contains($sw, 'isPosRuntimePath(url.pathname)'));
assert_true('SW build bumped', str_contains($sw, 'pos-admin-passthrough-v130'));
assert_true(
    'online POS admin does not respondWith navigatePosAdminCrudDocument',
    (bool) preg_match('/isPosAdminCrudPath[\s\S]{0,400}isHardBrowserOffline[\s\S]{0,200}releaseBackgroundWarmAfterFirstDocument/', $sw)
);

echo PHP_EOL . ($fail === 0 ? 'ALL PASSED' : "FAILED {$fail}") . PHP_EOL;
exit($fail > 0 ? 1 : 0);
