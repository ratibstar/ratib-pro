<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$root = dirname(__DIR__);
$out = ['ok' => true];
if (function_exists('opcache_reset')) {
    $out['opcache_reset'] = opcache_reset();
}
foreach ([
    $root . '/app/services/MobileAppConfigService.php',
    $root . '/app/services/HrEssPhaseCService.php',
    $root . '/app/Core/Response.php',
] as $f) {
    if (is_file($f) && function_exists('opcache_invalidate')) {
        opcache_invalidate($f, true);
    }
}
try {
    require_once $root . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init($root);
    $pdo = \Rateb\App\Core\Database::connection();
    $stmt = $pdo->prepare(
        "UPDATE rateb_mobile_app_configs
         SET app_name = :n
         WHERE company_id = 29
           AND (app_name IS NULL OR TRIM(app_name) = '' OR LOWER(TRIM(app_name)) IN ('aaa','test','demo'))"
    );
    $stmt->execute(['n' => 'راتب — الموارد البشرية']);
    $out['config_rows'] = $stmt->rowCount();
    $q = $pdo->prepare('SELECT company_id, app_name, status FROM rateb_mobile_app_configs WHERE company_id = 29 LIMIT 1');
    $q->execute();
    $out['config'] = $q->fetch(\PDO::FETCH_ASSOC) ?: null;
} catch (\Throwable $e) {
    $out['error'] = $e->getMessage();
}
$out['build'] = trim((string) @file_get_contents(__DIR__ . '/ratib-erp-build.txt'));
echo json_encode($out, JSON_UNESCAPED_UNICODE);
