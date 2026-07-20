<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$out = ['ok' => true, 'build' => trim((string) @file_get_contents(__DIR__ . '/ratib-erp-build.txt'))];
if (function_exists('opcache_reset')) {
    $out['opcache_reset'] = opcache_reset();
}
$root = dirname(__DIR__);
$targets = [
    $root . '/app/services/HrEssEmployeeResolverService.php',
    $root . '/app/services/PlanLimitService.php',
];
foreach ($targets as $f) {
    if (is_file($f) && function_exists('opcache_invalidate')) {
        opcache_invalidate($f, true);
        $out['invalidated'][] = basename($f);
        $out['mtime'][basename($f)] = filemtime($f);
        $out['has_unscoped'][basename($f)] = str_contains((string) file_get_contents($f), 'Database::connection()');
    }
}
try {
    require_once $root . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init($root);
    $pdo = \Rateb\App\Core\Database::connection();
    $email = 'qqqq@qq.qq';
    $u = $pdo->prepare('SELECT id, email, company_id, is_super_admin, status FROM rateb_users WHERE LOWER(TRIM(email)) = :em LIMIT 1');
    $u->execute(['em' => $email]);
    $out['user'] = $u->fetch(\PDO::FETCH_ASSOC) ?: null;
    $e = $pdo->prepare('SELECT id, email, company_id, user_id, status FROM rateb_employees WHERE LOWER(TRIM(email)) = :em LIMIT 5');
    $e->execute(['em' => $email]);
    $out['employees'] = $e->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    $e2 = $pdo->prepare('SELECT id, email, company_id, user_id FROM rateb_employees WHERE email LIKE :em LIMIT 5');
    $e2->execute(['em' => '%qqqq%']);
    $out['employees_like'] = $e2->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    $e3 = $pdo->prepare('SELECT id, email, company_id, user_id FROM rateb_employees WHERE id = 10 LIMIT 1');
    $e3->execute();
    $out['employee_10'] = $e3->fetch(\PDO::FETCH_ASSOC) ?: null;
} catch (\Throwable $ex) {
    $out['probe_error'] = $ex->getMessage();
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
