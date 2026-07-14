<?php
declare(strict_types=1);

/**
 * Render ERP main-layout shell HTML for browser benchmarks.
 * Stubs sidebar/nav includes so benchmarks run without MySQL/sqlite.
 *
 * php render-shell.php > shell.html
 */
$root = getenv('RATEB_BENCH_ROOT') ?: dirname(__DIR__, 2);
$root = str_replace('\\', '/', rtrim($root, '/\\'));
$host = getenv('RATEB_BENCH_HOST') ?: '127.0.0.1:8765';
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['REQUEST_URI'] = '/admin/ops';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['HTTPS'] = 'off';
$_GET = [];

require $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

$offlineModule = $root . '/offline/OfflineModule.php';
if (is_file($offlineModule)) {
    require_once $offlineModule;
    \Rateb\App\Offline\OfflineModule::init();
}

// Enable shell offline path so before/after compare the real SDK boot sequence.
foreach ([
    'RATEB_OFFLINE_ENABLED' => '1',
    'RATEB_OFFLINE_READ_CACHE' => '1',
] as $k => $v) {
    putenv($k . '=' . $v);
    $_ENV[$k] = $v;
}
if (class_exists(\Rateb\App\Offline\Services\OfflineFeatureFlagService::class)) {
    \Rateb\App\Offline\Services\OfflineFeatureFlagService::resetConfigCache();
}
$_GET['rateb_offline'] = '1';

\Rateb\App\Core\SessionManager::set('rateb_user_id', 1);
\Rateb\App\Core\SessionManager::set('rateb_company_id', 1);
\Rateb\App\Core\SessionManager::set('rateb_is_super_admin', false);
\Rateb\App\Core\SessionManager::set('rateb_user_name', 'Bench');

$src = (string) file_get_contents($root . '/views/layouts/main.php');

// Drop sidebar partials (DB-backed nav) — keep script/CSS boot path intact.
$src = preg_replace(
    '/<\?php\s+require\s+RATEB_ROOT\s*\.\s*\'\/views\/partials\/[^\'\"]+\'\s*;\s*\?>/',
    '<?php /* bench: sidebar partial stub */ ?>',
    $src
) ?? $src;
$src = preg_replace(
    '/if\s*\(\s*is_file\s*\(\s*\$platformCatalogNavPartial\s*\)\s*\)\s*\{\s*require\s+\$platformCatalogNavPartial\s*;\s*\}/',
    '/* bench: platform catalog stub */',
    $src
) ?? $src;
$src = preg_replace('/rateb_nav_can\s*\((?:[^()]|\([^()]*\))*\)/', 'true', $src) ?? $src;
$src = preg_replace('/rateb_is_super_admin\s*\(\s*\)/', 'false', $src) ?? $src;
$src = preg_replace('/rateb_is_platform_oversight_host\s*\(\s*\)/', 'false', $src) ?? $src;
$src = preg_replace('/rateb_is_local_appliance_host\s*\(\s*\)/', 'false', $src) ?? $src;
$src = preg_replace('/rateb_oversight_menu_counts\s*\(\s*\)/', "['total'=>0]", $src) ?? $src;
$src = preg_replace('/rateb_module_page_metrics\s*\((?:[^()]|\([^()]*\))*\)/', '[]', $src) ?? $src;
$src = preg_replace('/Rateb\\\\App\\\\Core\\\\View::partial\s*\([^;]+;/', '/* bench: partial stub */; ', $src) ?? $src;

$tmp = sys_get_temp_dir() . '/rateb_boot_bench_layout_' . md5($root . filemtime($root . '/views/layouts/main.php')) . '.php';
file_put_contents($tmp, $src);

$title = 'Boot Bench';
$hideModulePageStats = true;
$pageContent = '<div class="container-fluid py-4" id="rateb-boot-bench-root">'
    . '<h1>RATIB ERP Boot Bench</h1>'
    . '<p>Dashboard shell for startup metrics.</p>'
    . '<button type="button" class="btn btn-primary" id="rateb-bench-btn">OK</button>'
    . '</div>';

ob_start();
include $tmp;
$html = (string) ob_get_clean();

$base = 'http://' . $host;
$html = str_replace($base . '/', '/', $html);
$html = str_replace($base, '', $html);

if (!str_contains($html, '</html>')) {
    fwrite(STDERR, "ERROR: incomplete HTML\n");
    exit(1);
}

echo $html;
