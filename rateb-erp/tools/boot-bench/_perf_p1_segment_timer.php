<?php
/** PERF-P1 segment timer for Admin /admin request (CLI simulation). */
$root = '/home/admin/domains/rateb.sa/public_html/rateb-erp';
$marks = [];
$tMark = static function (string $name) use (&$marks): void {
    static $last = null;
    $now = hrtime(true);
    if ($last === null) {
        $last = $now;
        $marks[] = ['name' => $name, 'ms' => 0.0, 'cum' => 0.0];
        return;
    }
    $marks[] = [
        'name' => $name,
        'ms' => round(($now - $last) / 1e6, 2),
        'cum' => round(($now - ($GLOBALS['__t0'] ?? $now)) / 1e6, 2),
    ];
    $last = $now;
};

$GLOBALS['__t0'] = hrtime(true);
$tMark('start');

$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin';
$_SERVER['SCRIPT_NAME'] = '/rateb-erp/public/index.php';

require_once $root . '/app/Core/Bootstrap.php';
$tMark('require_bootstrap');
\Rateb\App\Core\Bootstrap::init($root);
$tMark('bootstrap_init');

// Mint session like remote-auth
$pdo = \Rateb\App\Core\Database::connection();
$tMark('db_connect');
$row = $pdo->query("SELECT id, email, company_id, is_super_admin, status FROM rateb_users WHERE email='admin@rateb.sa' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$tMark('user_lookup');
\Rateb\App\Core\SessionManager::start();
\Rateb\App\Core\SessionManager::set('rateb_user_id', (int) $row['id']);
\Rateb\App\Core\SessionManager::set('rateb_company_id', (int) ($row['company_id'] ?: 22));
\Rateb\App\Core\SessionManager::set('rateb_is_super_admin', !empty($row['is_super_admin']));
$tMark('session_set');

ob_start();
$router = new \Rateb\App\Core\Router();
$tMark('router_new');
\Rateb\App\Core\RouteModuleLoader::loadForPath($router, '/admin');
$tMark('routes_selective');

try {
    $router->dispatch('GET', '/admin');
    $tMark('dispatch_admin');
} catch (Throwable $e) {
    $tMark('dispatch_error');
    $marks[] = ['error' => $e->getMessage()];
}
$htmlLen = strlen((string) ob_get_clean());
$tMark('ob_done');

echo json_encode([
    'marks' => $marks,
    'html_len' => $htmlLen,
    'included' => count(get_included_files()),
    'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
    'opcache' => function_exists('opcache_get_status') ? @opcache_get_status(false)['opcache_enabled'] ?? false : false,
], JSON_UNESCAPED_SLASHES) . "\n";
