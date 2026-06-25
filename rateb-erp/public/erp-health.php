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

    if ($probe === 'admin-dash' || $probe === 'schema') {
        header('Content-Type: application/json; charset=UTF-8');
        $report = [
            'ok' => true,
            'php' => PHP_VERSION,
            'db' => \Rateb\App\Core\Database::resolvedDatabaseName(),
            'migrations' => [],
            'columns' => [],
            'classes' => [],
            'dashboard' => [],
        ];
        foreach (['131_financial_branch_isolation.sql', '132_interbranch_gl_consolidation.sql'] as $mf) {
            $stmt = $pdo->prepare('SELECT id FROM rateb_migrations WHERE filename = :f LIMIT 1');
            $stmt->execute(['f' => $mf]);
            $report['migrations'][$mf] = $stmt->fetch() ? 'applied' : 'missing';
        }
        foreach ([
            'rateb_journal_lines' => 'branch_id',
            'rateb_cost_centers' => 'branch_id',
            'rateb_bank_accounts' => 'branch_id',
            'rateb_journal_entries' => 'branch_id',
        ] as $table => $col) {
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ' . $pdo->quote($col));
            $report['columns'][$table . '.' . $col] = ($stmt !== false && $stmt->fetch()) ? 'yes' : 'no';
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
        }
        foreach ([
            'Rateb\\App\\Controllers\\Company\\BranchFinancialReportsController',
            'Rateb\\App\\Services\\BranchFinancialReportingService',
            'Rateb\\App\\Services\\ConsolidationEliminationService',
            'Rateb\\App\\Services\\AccountingService',
        ] as $class) {
            $report['classes'][$class] = class_exists($class);
        }
        try {
            \Rateb\App\Core\TenantContext::setSuperAdmin(true);
            $dash = new \Rateb\App\Services\DashboardService();
            $report['dashboard']['metrics'] = $dash->adminMetrics();
            $report['dashboard']['charts'] = $dash->adminCharts();
        } catch (\Throwable $e) {
            $report['ok'] = false;
            $report['dashboard']['error'] = $e->getMessage();
            $report['dashboard']['file'] = $e->getFile() . ':' . $e->getLine();
        }
        try {
            ob_start();
            \Rateb\App\Core\View::render('admin/dashboard', [
                'title' => 'Dashboard probe',
                'metrics' => $report['dashboard']['metrics'] ?? [],
                'charts' => $report['dashboard']['charts'] ?? [],
                'csrf' => \Rateb\App\Core\Csrf::token(),
            ], 'main');
            $html = (string) ob_get_clean();
            $report['dashboard']['render_len'] = strlen($html);
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $report['ok'] = false;
            $report['dashboard']['render_error'] = $e->getMessage();
            $report['dashboard']['render_file'] = $e->getFile() . ':' . $e->getLine();
        }
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

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

    if ($probe === 'routes' || $probe === 'dispatch' || $probe === 'all-routes' || $dispatchRoute !== '') {
        \Rateb\App\Core\Auth::bootstrapFromSession();
        $router = new \Rateb\App\Core\Router();
        require RATEB_ROOT . '/routes/web.php';
        $steps[] = 'routes/web.php OK';
        if ($probe === 'all-routes') {
            require RATEB_ROOT . '/routes/marketing.php';
            $steps[] = 'routes/marketing.php OK';
            require RATEB_ROOT . '/routes/cms.php';
            $steps[] = 'routes/cms.php OK';
        }
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
