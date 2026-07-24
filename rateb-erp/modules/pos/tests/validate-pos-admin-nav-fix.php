<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$sw = (string) file_get_contents($root . '/public/pos-sw.js');
$nav = (string) file_get_contents($root . '/public/assets/js/erp-nav-instant.js');
$side = (string) file_get_contents($root . '/views/partials/sidebar-nav.php');

$checks = [
    'SW_BUILD_ID v128' => str_contains($sw, "SW_BUILD_ID = '20260724-pos-admin-crud-nav-v128'"),
    'isPosAdminCrudPath' => str_contains($sw, 'function isPosAdminCrudPath'),
    'navigatePosAdminCrudDocument' => str_contains($sw, 'function navigatePosAdminCrudDocument'),
    'posAdminConnectionRequiredResponse' => str_contains($sw, 'function posAdminConnectionRequiredResponse'),
    'shellFallback guards CRUD' => (bool) preg_match('/function shellFallback[\s\S]{0,500}isPosAdminCrudPath/', $sw),
    'no CRUD→register rewrite in navigatePosCloud' => !preg_match(
        '/navigatePosCloudWithCacheSafety[\s\S]{0,800}dashboard\|reports\|settings/',
        $sw
    ),
    'POS_RUNTIME_RE' => str_contains($nav, 'POS_RUNTIME_RE'),
    'POS_ADMIN_PAGES_RE' => str_contains($nav, 'POS_ADMIN_PAGES_RE'),
    'Admin→CRUD forceFull' => str_contains($nav, 'POS_ADMIN_PAGES_RE.test(fu.pathname) && !isOnPosPagesShell()'),
    'sidebar pos/ full-nav' => str_contains($side, "str_starts_with(\$resourcePath, 'pos/')"),
    'cert meta untouched' => str_contains($sw, 'REGISTER_CERT_META_PATH') && str_contains($sw, 'serveCertifiedShellOrBioRequired'),
    'biometricRequired kept for runtime' => str_contains($sw, 'function biometricRequiredOfflineResponse'),
];

$fail = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . " {$name}\n";
    if (!$ok) {
        $fail++;
    }
}

echo $fail === 0 ? "\nALL STATIC CHECKS PASSED\n" : "\nFAILED {$fail}\n";
exit($fail > 0 ? 1 : 0);
