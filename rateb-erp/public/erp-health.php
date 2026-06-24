<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

define('RATEB_ENV_NO_SESSION', true);

$ratebRoot = realpath(dirname(__FILE__, 2));
if ($ratebRoot === false) {
    $ratebRoot = dirname(__FILE__, 2);
}
if (!defined('RATEB_ROOT')) {
    define('RATEB_ROOT', str_replace('\\', '/', $ratebRoot));
}

$probe = isset($_GET['probe']) ? (string) $_GET['probe'] : '';
$dispatchRoute = isset($_GET['dispatch']) ? (string) $_GET['dispatch'] : '';

$steps = [];
try {
    $steps[] = 'PHP ' . PHP_VERSION;
    $steps[] = 'RATEB_ROOT=' . RATEB_ROOT;

    require_once RATEB_ROOT . '/config/app.php';
    $steps[] = 'app.php OK';

    require_once RATEB_ROOT . '/config/database.php';
    $steps[] = 'database.php OK (DB_HOST=' . (defined('DB_HOST') ? DB_HOST : '?') . ', ERP DB=' . (defined('RATEB_DB_NAME') ? RATEB_DB_NAME : '?') . ')';

    require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
    $steps[] = 'Bootstrap OK';

    $pdo = \Rateb\App\Core\Database::connection();
    $steps[] = 'DB connection OK (' . \Rateb\App\Core\Database::resolvedDatabaseName() . ')';

    if ($probe === 'approvals') {
        header('Content-Type: application/json; charset=UTF-8');
        $report = ['db' => \Rateb\App\Core\Database::resolvedDatabaseName(), 'tables' => [], 'pending_test' => null];
        $tables = [
            'rateb_supplier_evaluations' => ['manager_approval', 'approved_by', 'approved_at'],
            'rateb_contracts' => ['approval_status'],
            'rateb_inventory_audits' => ['manager_approval', 'approved_by', 'approved_at'],
            'rateb_asset_assignments' => ['manager_approval', 'approved_by', 'approved_at'],
        ];
        foreach ($tables as $table => $cols) {
            $entry = ['columns' => []];
            foreach ($cols as $col) {
                $stmt = $pdo->query(
                    'SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($col)
                );
                $row = $stmt !== false ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
                if ($stmt instanceof \PDOStatement) {
                    $stmt->closeCursor();
                }
                $entry['columns'][$col] = is_array($row) ? (string) ($row['Type'] ?? 'yes') : false;
            }
            $report['tables'][$table] = $entry;
        }
        $row = $pdo->query(
            "SELECT id, company_id, manager_approval FROM rateb_supplier_evaluations WHERE manager_approval = 'pending' ORDER BY id DESC LIMIT 1"
        )->fetch(\PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $id = (int) ($row['id'] ?? 0);
            try {
                $pdo->beginTransaction();
                $pdo->prepare(
                    'UPDATE rateb_supplier_evaluations SET manager_approval = :st WHERE id = :id AND manager_approval = :pending'
                )->execute(['st' => 'approved', 'id' => $id, 'pending' => 'pending']);
                $pdo->rollBack();
                $report['pending_test'] = ['id' => $id, 'ok' => true];
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $report['pending_test'] = ['id' => $id, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($probe === 'routes' || $probe === 'dispatch' || $dispatchRoute !== '') {
        \Rateb\App\Core\Auth::bootstrapFromSession();
        $router = new \Rateb\App\Core\Router();
        require RATEB_ROOT . '/routes/web.php';
        $steps[] = 'routes/web.php OK';
        require RATEB_ROOT . '/routes/company.php';
        $steps[] = 'routes/company.php OK';
        require RATEB_ROOT . '/routes/api.php';
        $steps[] = 'routes/api.php OK';
    }

    if ($probe === 'dispatch' || $dispatchRoute !== '') {
        require_once RATEB_ROOT . '/app/helpers/Request.php';
        $route = $dispatchRoute !== '' ? $dispatchRoute : 'login';
        $_GET['route'] = ltrim($route, '/');
        $path = \Rateb\App\Helpers\Request::resolvePath();
        $steps[] = 'resolved path=' . $path;

        ob_start();
        $router->dispatch('GET', $path);
        $body = (string) ob_get_clean();
        $steps[] = 'dispatch OK body_len=' . strlen($body);
    }

    echo "RATEB ERP health: OK\n";
    foreach ($steps as $line) {
        echo '- ' . $line . "\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "RATEB ERP health: FAIL\n";
    foreach ($steps as $line) {
        echo '- ' . $line . "\n";
    }
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}
